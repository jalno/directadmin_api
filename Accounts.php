<?php

namespace packages\directadmin_api;

use packages\base\Date;
use packages\base\Exception;
use packages\base\IO\File;
use packages\base\Log;

class Accounts
{
    /** @var API */
    private $api;
    /** @var HTTPSocket */
    private $socket;

    public function __construct(API $api)
    {
        $this->api = $api;
        $this->socket = $this->api->getSocket();
    }

    /**
     * @return array<Account>|array<null>
     *
     * @throws FailedException
     * @throws NotFoundAccountException
     */
    public function all(): array
    {
        $accounts = [];
        $this->socket->set_method('GET');
        $this->socket->query('/CMD_API_ALL_USER_USAGE');
        $rawBody = $this->socket->fetch_body();
        $result = $this->socket->fetch_parsed_body();
        if (isset($result['error']) and 1 == $result['error']) {
            throw new FailedException($result);
        }
        $lines = explode("\n", $rawBody);
        foreach ($lines as $line) {
            if ($username = strtok($line, '=')) {
                $accounts[] = Account::importByUsername($this->api, $username);
            }
        }

        return $accounts;
    }

    /**
     * @return array<Account>|array<null>
     *
     * @throws FailedException
     */
    public function summeryList(): array
    {
        $accounts = [];
        $this->socket->set_method('GET');
        $this->socket->query('/CMD_API_ALL_USER_USAGE');
        $rawBody = $this->socket->fetch_body();
        if (false !== stripos($rawBody, 'error')) {
            $results = $this->socket->fetch_parsed_body();
            if (isset($results['error']) and 1 == $results['error']) {
                throw new FailedException($results);
            }
        }
        $lines = explode("\n", $rawBody);
        foreach ($lines as $line) {
            if ($username = strtok($line, '=')) {
                $result = null;
                parse_str(substr($line, strlen($username) + 1), $result);
                if (!isset($result['default']) or '' == $result['default']) {
                    continue;
                }
                $account = new Account($this->api, $username, $result['default']);
                list($quota, $maxQuota) = explode('/', $result['quota'], 2);
                $quota = trim($quota);
                $maxQuota = trim($maxQuota);
                $account->setMaxQuota('unlimited' == $maxQuota ? Account::unlimited : $maxQuota);
                $account->setQuato($quota);
                if (isset($result['package'])) {
                    $account->setPackage($result['package']);
                }
                list($bandwidth, $maxBandwidth) = explode('/', $result['bandwidth'], 2);
                $bandwidth = trim($bandwidth);
                $maxBandwidth = trim($maxBandwidth);
                $account->setMaxBandwidth('unlimited' == $maxBandwidth ? Account::unlimited : $maxBandwidth);
                $account->setBandwidth(intval($bandwidth));
                $account->setSuspended('No' != $result['suspended']);
                list($addonDomains, $maxAddonDomains) = explode('/', $result['vdomains'], 2);
                $addonDomains = trim($addonDomains);
                $maxAddonDomains = trim($maxAddonDomains);
                $account->setMaxAddonDomains('unlimited' == $maxAddonDomains ? Account::unlimited : $maxAddonDomains);
                $account->setAddonDomains($addonDomains);
                $account->setEmails($result['email_deliveries_outgoing']);
                if (isset($result['email_daily_limit'])) {
                    $account->setMaxEmails('unlimited' == $result['email_daily_limit'] ? Account::unlimited : $result['email_daily_limit']);
                }
                $accounts[] = $account;
            }
        }

        return $accounts;
    }

    /**
     * @throws FailedException
     * @throws NotFoundAccountException
     */
    public function byUsername(string $username): ?Account
    {
        $this->socket->set_method('GET');
        $this->socket->query('/CMD_API_ALL_USER_USAGE');
        $rawBody = $this->socket->fetch_body();
        if (false !== stripos($rawBody, 'error')) {
            $result = $this->socket->fetch_parsed_body();
            if (isset($result['error']) and 1 == $result['error']) {
                throw new FailedException($result);
            }
        }
        $lines = explode("\n", $rawBody);
        foreach ($lines as $line) {
            $user = strtok($line, '=');
            if ($username == $user) {
                return Account::importByUsername($this->api, $username);
            }
        }

        return null;
    }

    /**
     * @throws FailedException
     * @throws NotFoundAccountException
     */
    public function byDomain(string $domain): ?Account
    {
        $this->socket->set_method('GET');
        $this->socket->query('/CMD_API_ALL_USER_USAGE');
        $rawBody = $this->socket->fetch_body();
        if (false !== stripos($rawBody, 'error')) {
            $result = $this->socket->fetch_parsed_body();
            if (isset($result['error']) and 1 == $result['error']) {
                throw new FailedException($result);
            }
        }
        $lines = explode("\n", $rawBody);
        foreach ($lines as $line) {
            $firstEq = strpos($line, '=');
            if ($firstEq > 0) {
                $username = substr($line, 0, $firstEq);
                foreach (explode('&', substr($line, $firstEq + 1)) as $part) {
                    $firstEq = strpos($part, '=');
                    $key = substr($part, 0, $firstEq);
                    if ('list' == $key) {
                        $value = substr($part, $firstEq + 1);
                        $value = explode('<br>', $value);
                        if (in_array($domain, $value)) {
                            return Account::importByUsername($this->api, $username);
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array<string>|array<null>
     *
     * @throws FailedException
     */
    public function backups(): array
    {
        $files = [];
        if (API::Admin == $this->api->getLevel()) {
            $this->socket->set_method('GET');
            $this->socket->query('/CMD_API_ADMIN_BACKUP');
            $rawBody = $this->socket->fetch_body();
            $result = $this->socket->fetch_parsed_body();
            if (isset($result['error']) and 1 == $result['error']) {
                throw new FailedException($result);
            }
            foreach ($result as $key => $value) {
                if ('file' == substr($key, 0, 4)) {
                    $files[] = $result['location'].'/'.$value;
                }
            }
        } elseif (API::Reseller == $this->api->getLevel()) {
            $this->socket->set_method('GET');
            $this->socket->query('/CMD_API_USER_BACKUP');
            $rawBody = $this->socket->fetch_body();
            $result = $this->socket->fetch_parsed_body();
            if (isset($result['error']) and 1 == $result['error']) {
                throw new FailedException($result);
            }
            foreach ($result as $key => $value) {
                if ('file' == substr($key, 0, 4)) {
                    $files[] = $result['USER_BACKUPS_DIR'].'/'.$value;
                }
            }
        }

        return $files;
    }

    /**
     * @param string[]      $users
     * @param array|null    $location "where"(string = ftp), "hostname"(string), "username"(string), "password"(string), "port"(int), "directory"(string), "secure" (ftps|ftp)
     * @param string[]|null $what     "domain", "subdomain", "ftp", "ftpsettings", "database", "database_data", "email", "email_data", "emailsettings", "vacation", "autoresponder", "list", "forwarder"
     *
     * @return array<string,string>
     *
     * @throws FailedException
     */
    public function backup(array $users, int $timeout = 1200, ?array $location = [], ?array $what = []): array
    {
        $log = Log::getInstance();

        $apiAccount = $this->api->getAccount();

        $log->info('get system last message');
        $tickets = $apiAccount->getTickets([
            'ipp' => 1,
        ]);
        $lastTicket = reset($tickets);
        if ($lastTicket) {
            $log->reply('sent in: ', Date\Gregorian::format('Y/m/d H-i-s', $lastTicket['last_message']));
        } else {
            $log->reply('NotFound');
            $lastTicket['last_message'] = Date::time();
        }

        $params = [
            'action' => 'create',
            'who' => 'selected',
            'when' => 'now',
        ];
        if ($location) {
            $params['where'] = $location['where'];
            if ('ftp' == $location['where']) {
                $params['ftp_ip'] = $location['hostname'];
                $params['ftp_username'] = $location['username'];
                $params['ftp_password'] = $location['password'];
                $params['ftp_port'] = $location['port'];
                $params['ftp_path'] = $location['directory'];
                $params['ftp_secure'] = isset($location['secure']) ? $location['secure'] : 'ftp';
            } elseif ('local' == $location['where']) {
                $params['where'] = $location['where'];
                $params['local_path'] = $location['local_path'];
            } else {
                throw new Exception('unknown location for create backup');
            }
        } else {
            $params['where'] = 'local';
            $params['local_path'] = '/home/admin/admin_backups';
        }

        if ($what) {
            $params['what'] = 'select';
            foreach ($what as $x => $option) {
                $params['option'.$x] = $option;
            }
        } else {
            $params['what'] = 'all';
        }

        foreach ($users as $key => $user) {
            $params['select'.$key] = $user;
        }

        $this->socket->set_method('POST');

        $query = '';
        switch ($this->api->getLevel()) {
            case API::Admin: $query = 'CMD_API_ADMIN_BACKUP';
                break;
            case API::Reseller: $query = 'CMD_API_USER_BACKUP';
                break;
        }

        $this->socket->query('/'.$query, $params);
        $results = $this->socket->fetch_parsed_body();

        if (!$results) {
            $exception = new FailedException();
            $exception->setRequest($params);
            throw $exception;
        }

        if (isset($results['error']) and 1 == $results['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($results);
            throw $exception;
        }

        $startAt = Date::time();

        $checkUsersInTicket = function (string $message) use (&$users): bool {
            $foundedUsers = 0;
            foreach ($users as $user) {
                if (false !== stripos($message, "User {$user} has been backed up")
                    or false !== stripos($message, "{$user}.tar.gz")
                    or false !== stripos($message, "{$user}.tar.zst")
                ) {
                    ++$foundedUsers;
                }
            }

            return $foundedUsers == count($users);
        };

        while (0 == $timeout or Date::time() - $startAt < $timeout) {
            $log->info('Get tickets to check backup is completed or not');
            $tickets = $apiAccount->getTickets();
            $log->reply(count($tickets), ' tickets get. Check them for new tickets.');

            foreach ($tickets as $ticket) {
                if (!$lastTicket or $ticket['last_message'] > $lastTicket['last_message']) {
                    $lastTicket = $ticket;
                    $log->info('the new ticket found, sent in: ', Date\Gregorian::format('Y/m/d H-i-s', $ticket['last_message']));
                    $subject = strtolower($ticket['subject']);
                    if ('your backups are now ready' == substr($subject, 0, strlen('your backups are now ready'))) {
                        $content = $apiAccount->getTicket($ticket['message']);
                        if ($content) {
                            if ($checkUsersInTicket($content['message'])) {
                                $log->reply('found ticket that was looking for');
                                break 2;
                            } else {
                                $log->reply('Sorry. Maybe next time');
                            }
                        } else {
                            $log->reply()->warn('Unable to get message content');
                        }
                    } elseif ('an error occurred during the backup' == substr($subject, 0, strlen('an error occurred during the backup'))) {
                        $log->reply("Oh it's seems an error occurred, Let's check it");
                        $content = $apiAccount->getTicket($ticket['message']);
                        if ($content) {
                            if ($checkUsersInTicket($content['message'])) {
                                $log->reply()->fatal('Sorry.., the create backup process has faield');
                                $e = new FailedException();
                                $e->setRequest($params);
                                $e->setResponse($results);
                                throw $e;
                            } else {
                                $log->reply("Be Happy, It's not our request");
                            }
                        } else {
                            $log->reply()->warn('Unable to get message content');
                        }
                    }
                }
            }
            sleep(1);
        }
        $result = [];
        if ('local' == $params['where']) {
            $files = $this->backups();
            foreach ($users as $user) {
                foreach ($files as $file) {
                    $basename = substr($file, strrpos($file, '/') + 1);
                    if (false !== stripos($basename, "{$user}.tar.gz") or false !== stripos($basename, "{$user}.tar.zst")) {
                        $result[$user] = $file;
                    }
                }
            }
        } else {
            $file = new File\FTP();
            $file->directory = $location['directory'];
            $file->hostname = $location['hostname'];
            $file->port = $location['port'];
            $file->username = $location['username'];
            $file->password = $location['password'];
            $files = $file->getDirectory()->files();
            $found = false;
            foreach ($files as $f) {
                foreach ($users as $user) {
                    if (isset($result[$user])) {
                        continue;
                    }
                    if (preg_match("/^(?:user|reseller|admin)\.(?:\w+)\.(\w+)\.tar\.(gz|zst)$/", $f->basename, $matches)) {
                        if ($matches[1] == $user) {
                            $result[$user] = $f;
                            $found = true;
                        }
                    }
                }
            }
            if (!$found) {
                $file->getDriver()->close();
            }
        }

        return $result;
    }

    /**
     * @param string[]   $files
     * @param array|null $location "where"(string = ftp), "hostname"(string), "username"(string), "password"(string), "port"(int), "directory"(string), "secure" (ftps|ftp)
     *
     * @throws Exception
     * @throws FailedException
     */
    public function restore(array $files, ?string $ip = null, int $timeout = 1200, array $location = []): void
    {
        $log = Log::getInstance();

        if (API::Admin != $this->api->getLevel()) {
            $log->error('can not restore backup without non-admin level!');

            return;
        }
        $apiAccount = $this->api->getAccount();

        $log->info('get system last message');
        $tickets = $apiAccount->getTickets([
            'ipp' => 1,
        ]);
        $lastTicket = reset($tickets);
        if ($lastTicket) {
            $log->reply('sent in: ', Date\Gregorian::format('Y/m/d H-i-s', $lastTicket['last_message']));
        } else {
            $log->reply('not found');
            $lastTicket['last_message'] = Date::time();
        }
        $startAt = Date::time();

        $log->info('init restore params');
        $params = [
            'action' => 'restore',
            'who' => 'selected',
        ];
        if ($location) {
            if ('ftp' == $location['where']) {
                $params['where'] = $location['where'];
                $params['ftp_ip'] = $location['hostname'];
                $params['ftp_username'] = $location['username'];
                $params['ftp_password'] = $location['password'];
                $params['ftp_port'] = $location['port'];
                $params['ftp_path'] = $location['directory'];
            } else {
                throw new Exception('unknown location for restore backup:'.$location['where']);
            }
        } else {
            $params['where'] = 'local';

            $commonDir = '';
            foreach ($files as $file) {
                $lastSlashPos = strrpos($file, '/');
                if (false !== $lastSlashPos) {
                    $fileDir = substr($file, 0, $lastSlashPos);
                    if ($commonDir and $fileDir != $commonDir) {
                        throw new Exception('multiplte directories');
                    }
                    if (!$commonDir) {
                        $commonDir = $fileDir;
                    }
                }
            }
            $params['local_path'] = $commonDir ? $commonDir : '/home/admin/admin_backups';
        }
        if ($ip) {
            $params['ip_choice'] = 'select';
            $params['ip'] = $ip;
        }

        $basenames = [];
        $length = count($files);
        for ($x = 0; $x < $length; ++$x) {
            $file = $files[0];
            $lastSlashPos = strrpos($file, '/');
            $basename = (false !== $lastSlashPos ? substr($file, $lastSlashPos + 1) : $file);
            $params["select{$x}"] = $basename;
            $basenames[] = $basename;
        }
        $log->reply($params);

        $log->info('try to restore');
        $this->socket->set_method('POST');
        $this->socket->query('/CMD_API_ADMIN_BACKUP', $params);
        $result = $this->socket->fetch_parsed_body();
        if (isset($result['error']) and $result['error'] or empty($result)) {
            $log->reply()->fatal('failed');
            $FailedExeption = new FailedException();
            $FailedExeption->setRequest($params);
            $FailedExeption->setResponse($result);
            throw $FailedExeption;
        }
        $log->reply('no problem');

        $checkUsersInTicket = function (string $message) use (&$basenames): bool {
            $foundedUsers = 0;
            foreach ($basenames as $basename) {
                if (false !== stripos($message, "User {$basename} has been restored") or false !== stripos($message, $basename)) {
                    ++$foundedUsers;
                }
            }

            return $foundedUsers == count($basenames);
        };
        $log->info("get system tickets for checking new ticket, timeout: {$timeout} sec");
        while (0 === $timeout or Date::time() - $startAt < $timeout) {
            $log->info('Get tickets to check restore is completed or not');
            $tickets = $apiAccount->getTickets();
            $log->reply(count($tickets), ' tickets get. Check it for new tickets.');
            foreach ($tickets as $ticket) {
                if (!$lastTicket or $ticket['last_message'] > $lastTicket['last_message']) {
                    $lastTicket = $ticket;
                    $log->info('the new ticket found, sent in: ', Date\Gregorian::format('Y/m/d H-i-s', $ticket['last_message']));
                    $subject = trim(strtolower($ticket['subject']));
                    if ('your user files have been restored' == substr($subject, 0, strlen('your user files have been restored'))) {
                        $content = $apiAccount->getTicket($ticket['message']);
                        if ($content) {
                            if ($checkUsersInTicket($content['message'])) {
                                $log->reply('found ticket that was looking for');
                                break 2;
                            } else {
                                $log->reply('sorry. maybe next time');
                            }
                        } else {
                            $log->reply()->warn('unable to get message content');
                        }
                    } elseif ('an error occurred during the restore' == substr($subject, 0, strlen('an error occurred during the restore'))) {
                        $log->reply("oh it's seems an error occurred, let's check it");
                        $content = $apiAccount->getTicket($ticket['message']);
                        if ($content) {
                            if ($checkUsersInTicket($content['message'])) {
                                $log->reply()->fatal('sorry.., the restore backup process has been faield.');
                                $e = new FailedException();
                                $e->setRequest($params);
                                $e->setResponse($content);
                                throw $e;
                            } else {
                                $log->reply("Be Happy, It's not our request");
                            }
                        } else {
                            $log->reply()->warn('unable to get message content');
                        }
                    }
                }
            }
            sleep(1);
        }
    }

    /**
     * @return Account|null
     *
     * @throws FailedException
     */
    public function transferFrom(string $host, string $username, int $level, string $password, string $domain, string $localIP, int $port = 2222, bool $ssl = false)
    {
        $other = new API($host, $port, $ssl);
        $other->setUsername($username, $level);
        $other->setPassword($password);

        $localBackup = new File\TMP();
        if (API::Admin == $level || API::Reseller == $level) {
            $accounts = $other->getAccounts();
            $account = $accounts->byDomain($domain);
            $filePath = $accounts->backup($account->getUsername());
            $adminAccount = $other->getAccount();
            $adminAccount->getFiles()->download($filePath, $localBackup);
        } else {
            $account = $other->getAccount();
            $filePath = $account->backup();
            $account->getFiles()->download($filePath, $localBackup);
        }
        $localPath = '/admin_backups';
        $localBackup->rename("user.admin.{$account->getUsername()}.tar.gz");
        $this->api->getAccount()->getFiles()->upload($localPath, $localBackup);
        $this->restore([$localBackup->basename], $localIP);

        return $this->byUsername($account->getUsername());
    }

    /**
     * Delete multiple accounts.
     *
     * @param string[] $users
     *
     * @throws FailedException
     */
    public function delete(array $users): void
    {
        if (empty($users)) {
            return;
        }
        $this->socket->set_method('POST');
        $params = [
            'confirmed' => 'Confirm',
            'delete' => 'yes',
        ];
        foreach ($users as $x => $user) {
            $params['select'.$x] = $user;
        }
        $this->socket->query('/CMD_API_SELECT_USERS', $params);
        $result = $this->socket->fetch_parsed_body();
        if (!$result) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }
        if (isset($result['error']) and 1 == $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }
    }

    public function getNewAccount(string $username, string $domain, string $email): Account
    {
        return new Account($this->api, $username, $domain, $email);
    }
}
