<?php

namespace packages\directadmin_api;

use packages\base\Utility\Password;

class EmailManager
{
    public const UTF = 'UTF-8';
    public const ISO = 'iso-8859-1';
    public const PLAIN = 'text/plain';
    public const HTML = 'text/html';

    protected $api;
    protected $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->api = $this->account->getAPI();
    }

    /**
     * export an array like this:
     * Array
     * (
     * 	[domain] => domain.com
     * 	[list] => array()
     * )
     * each index of list array should something like this:
     * Array
     *	(
     *		[account] => info
     *		[login] => info@domain.com
     *		[usage] => Array
     *			(
     *				[apparent_usage] => 0.0857
     *				[imap_bytes] => 147456
     *				[quota] => 50 or unlimited
     *				[usage] => 0.1406
     *				[webmail_bytes] => 0.000000
     *			)
     *		[sent] => Array
     *			(
     *				[send_limit] => 200
     *				[sent] => 0
     *			)
     *		[suspended] => no
     *	).
     */
    public function getEmails(string $domain = ''): array
    {
        $domain = ($domain ? $domain : $this->account->getDomain());
        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('GET');
        $params = [
            'json' => 'yes',
            'domain' => $domain,
        ];
        $socket->query('/CMD_EMAIL_POP', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }
        $list = [];
        foreach ($result['emails'] as $key => $item) {
            if (is_numeric($key) and 0 != $key) {
                $list[$item['account']] = $item;
            }
        }

        return [
            'domain' => $domain,
            'list' => $list,
        ];
    }

    public function createEmail(array $data): array
    {
        if (!isset($data['username']) or !$data['username']) {
            throw new \InvalidArgumentException("'username' is required to create new email account");
        }
        foreach (['quota', 'limit'] as $item) {
            if (isset($data[$item]) and !is_numeric($data[$item])) {
                throw new \InvalidArgumentException($item.' must pass as int (zero is unlimited)');
            }
        }
        if (!isset($data['password'])) {
            $data['password'] = Password::generate();
        }
        $data['quota'] = (isset($data['quota'])) ? $data['quota'] : 50;
        $data['limit'] = (isset($data['limit']) and $data['limit'] > 0) ? $data['limit'] : 200;
        $data['domain'] = (isset($data['domain']) and $data['domain']) ? $data['domain'] : $this->account->getDomain();

        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'create',
            'domain' => $data['domain'],
            'user' => $data['username'],
            'passwd' => $data['password'],
            'passwd2' => $data['password'],
            'quota' => $data['quota'],
            'limit' => $data['limit'],
        ];
        $socket->query('/CMD_API_POP', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            if (isset($result['details'])) {
                if (false !== stripos($result['details'], 'That user already exists')) {
                    throw new EmailAlreadyExistException();
                } elseif (false !== stripos($result['details'], 'You have already reached your assigned limit')) {
                    throw new ReachedLimitException();
                }
            } else {
                $exception = new FailedException();
                $exception->setRequest($params);
                $exception->setResponse($result);
                throw $exception;
            }
        }

        return $data;
    }

    public function modifyEmail(array $data): array
    {
        if (!isset($data['username']) or !$data['username']) {
            throw new \InvalidArgumentException("give 'username' index to modify email account");
        }
        foreach (['quota', 'limit'] as $item) {
            if (isset($data[$item]) and !is_numeric($data[$item])) {
                throw new \InvalidArgumentException($item.' must pass as int (zero is unlimited)');
            }
        }
        $data['domain'] = (isset($data['domain']) and $data['domain']) ? $data['domain'] : $this->account->getDomain();
        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'modify',
            'domain' => $data['domain'],
            'user' => $data['username'],
        ];
        if (isset($data['newUsername']) and $data['newUsername']) {
            $params['newuser'] = $data['newUsername'];
        }
        if (isset($data['password']) and $data['password']) {
            $params['passwd'] = $data['password'];
            $params['passwd2'] = $data['password'];
        }
        foreach (['quota', 'limit'] as $item) {
            if (isset($data[$item])) {
                $params[$item] = $data[$item];
            }
        }
        $socket->query('/CMD_API_POP', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }

        return $data;
    }

    public function deleteEmail(array $data): ?bool
    {
        if (!isset($data['username']) or !$data['username']) {
            throw new \InvalidArgumentException("give 'username' index to delete email account");
        }
        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'delete',
            'domain' => (isset($data['domain']) and $data['domain']) ? $data['domain'] : $this->account->getDomain(),
            'user' => $data['username'],
        ];
        $socket->query('/CMD_API_POP', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            if (isset($result['details']) and false !== stripos($result['details'], 'does not exist')) {
                throw new EmailNotExistException();
            } else {
                $exception = new FailedException();
                $exception->setRequest($params);
                $exception->setResponse($result);
                throw $exception;
            }
        }

        return true;
    }

    /**
     * export an array like this:
     * Array
     * (
     * 	[domain] => domain.com
     * 	[list] => array()
     * )
     * the [list] array is something like this
     * Array
     *	(
     *		[info] => folan@jeyserver.com
     *	)
     */
    public function getEmailForwarders(string $domain = ''): array
    {
        $domain = ($domain ? $domain : $this->account->getDomain());
        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'list',
            'domain' => $domain,
        ];
        $socket->query('/CMD_API_EMAIL_FORWARDERS', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }
        $list = [];
        foreach ($result as $username => $forwarded) {
            $list[$username] = explode(',', $forwarded);
        }

        return [
            'domain' => $domain,
            'list' => $list,
        ];
    }

    public function createEmailForwarder(array $data): array
    {
        if (!isset($data['username']) or !$data['username'] or !is_string($data['username'])) {
            throw new \InvalidArgumentException("you should pass: 'username' and it must be as string");
        }
        if (!isset($data['forward']) or (!is_array($data['forward']) and !is_string($data['forward']))) {
            throw new \InvalidArgumentException("you should pass: 'forward' and it must be as string or array of string");
        }
        $domain = (isset($data['domain']) and $data['domain']) ? $data['domain'] : $this->account->getDomain();
        $forward = (is_array($data['forward']) ? implode(',', $data['forward']) : $data['forward']);
        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'create',
            'domain' => $domain,
            'user' => $data['username'],
            'email' => $forward,
        ];
        $socket->query('/CMD_API_EMAIL_FORWARDERS', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            if (isset($result['details'])) {
                if (false !== stripos($result['details'], 'already exists')) {
                    throw new EmailAlreadyExistException();
                } elseif (false !== stripos($result['details'], 'You have already reached your assigned limit')) {
                    throw new ReachedLimitException();
                }
            } else {
                $exception = new FailedException();
                $exception->setRequest($params);
                $exception->setResponse($result);
                throw $exception;
            }
        }

        return [
            'domain' => $domain,
            'username' => $data['username'],
            'forward' => $data['forward'],
        ];
    }

    public function modifyEmailForwarder(array $data): array
    {
        if (!isset($data['username']) or !$data['username'] or !is_string($data['username'])) {
            throw new \InvalidArgumentException("you should pass: 'username' and it must be as string");
        }
        if (!isset($data['forward']) or (!is_array($data['forward']) and !is_string($data['forward']))) {
            throw new \InvalidArgumentException("you should pass: 'forward' and it must be as string or array of string");
        }
        $data['domain'] = (isset($data['domain']) and $data['domain']) ? $data['domain'] : $this->account->getDomain();
        $forward = (is_array($data['forward']) ? implode(',', $data['forward']) : $data['forward']);
        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'modify',
            'json' => 'yes',
            'domain' => $data['domain'],
            'user' => $data['username'],
            'email' => $forward, // forward
        ];
        $socket->query('/CMD_EMAIL_FORWARDER', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }

        return $data;
    }

    public function deleteEmailForwarder(array $data): ?bool
    {
        if (!isset($data['username']) or !$data['username'] or !is_string($data['username'])) {
            throw new \InvalidArgumentException("you should pass: 'username' index and it must be as string");
        }
        $data['domain'] = (isset($data['domain']) and $data['domain']) ? $data['domain'] : $this->account->getDomain();
        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'delete',
            'json' => 'yes',
            'delete' => 'yes',
            'domain' => $data['domain'],
            'select0' => $data['username'],
        ];
        $socket->query('/CMD_EMAIL_FORWARDER', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            if (isset($result['details']) and false !== stripos($result['details'], 'does not exist')) {
                throw new EmailNotExistException();
            } else {
                $exception = new FailedException();
                $exception->setRequest($params);
                $exception->setResponse($result);
                throw $exception;
            }
        }

        return true;
    }

    public function getAutoResponders(string $domain = ''): array
    {
        $domain = ($domain ? $domain : $this->account->getDomain());
        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'list',
            'domain' => $domain,
        ];
        $socket->query('/CMD_API_EMAIL_AUTORESPONDER', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }
        $list = [];
        foreach ($result as $key => $item) {
            $list[$key] = [
                'cc' => $item,
            ];
        }

        return [
            'domain' => $domain,
            'list' => $list,
        ];
    }

    public function createAutoResponder(array $data)
    {
        if (!isset($data['username']) or !$data['username'] or !is_string($data['username'])) {
            throw new \InvalidArgumentException("you should pass: 'username' index and it must be as string");
        }
        if (!isset($data['subject']) or !$data['subject']) {
            $data['subject'] = 'Autoreply';
        }
        if (isset($data['encoding']) and $data['encoding']) {
            if (in_array($data['encoding'], [self::UTF, self::ISO])) {
                throw new \InvalidArgumentException("'encoding' index should be EmailManager::UTF or EmailManager::ISO");
            }
        } else {
            $data['encoding'] = self::UTF;
        }
        if (isset($data['content_type']) and $data['content_type']) {
            if (!in_array($data['content_type'], [self::HTML, self::PLAIN])) {
                throw new \InvalidArgumentException("'content_type' index should be EmailManager::HTML or EmailManager::PLAIN");
            }
        } else {
            $data['content_type'] = self::PLAIN;
        }
        if (!isset($data['reply_time']) or $data['reply_time']) {
            $data['reply_time'] = '1h';
        }
        $data['domain'] = (isset($data['domain']) and $data['domain']) ? $data['domain'] : $this->account->getDomain();
        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'create',
            'domain' => $data['domain'],
            'user' => $data['username'],
            'subject' => $data['subject'],
            'reply_encoding' => $data['encoding'],
            'reply_content_type' => $data['content_type'],
            'reply_once_time' => $data['reply_time'],
            'text' => $data['text'],
            'email' => '',
            'create' => 'Create',
        ];
        if (isset($data['cc']) and $data['cc']) {
            $params['cc'] = 'ON';
            $params['email'] = $data['cc'];
        }
        $socket->query('/CMD_API_EMAIL_AUTORESPONDER', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            if (isset($result['details']) and false !== stripos($result['details'], 'An autoresponder with that name already exists')) {
                throw new EmailAlreadyExistException();
            } else {
                $exception = new FailedException();
                $exception->setRequest($params);
                $exception->setResponse($result);
                throw $exception;
            }
        }

        return $data;
    }

    public function modifyAutoResponder(array $data)
    {
        if (!isset($data['username']) or !$data['username'] or !is_string($data['username'])) {
            throw new \InvalidArgumentException("you should pass: 'username' index and it must be as string");
        }
        $data['domain'] = (isset($data['domain']) and $data['domain']) ? $data['domain'] : $this->account->getDomain();

        $username = $level = null;
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('GET');
        $params = [
            'json' => 'yes',
            'domain' => $data['domain'],
            'user' => $data['username'],
        ];
        $socket->query('/CMD_API_EMAIL_AUTORESPONDER_MODIFY', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }
        $params = [
            'action' => 'modify',
            'json' => 'yes',
            'domain' => $data['domain'],
            'user' => $data['username'],
        ];
        $params['email'] = $result['email'];
        $params['text'] = $result['text'];
        foreach ($result['headers'] as $key => $item) {
            if (is_array($item)) {
                foreach ($item as $param) {
                    if (isset($param['selected'])) {
                        if ('reply_once_select' == $key) {
                            $params['reply_once_time'] = $param['value'];
                        } elseif ('reply_content_types' == $key) {
                            $params['reply_content_type'] = $param['value'];
                        } elseif ('reply_encodings' == $key) {
                            $params['reply_encoding'] = $param['value'];
                        } else {
                            $params[$key] = $param['value'];
                        }
                    }
                }
            } elseif ('subject_prefix' == $key) {
                $params['subject'] = $item;
            }
        }
        if (isset($data['encoding']) and $data['encoding']) {
            if (!in_array($data['encoding'], [self::UTF, self::ISO])) {
                throw new \InvalidArgumentException("'encoding' index should be EmailManager::UTF or EmailManager::ISO");
            }
            $params['reply_encoding'] = $data['encoding'];
        }
        if (isset($data['content_type']) and $data['content_type']) {
            if (!in_array($data['content_type'], [self::HTML, self::PLAIN])) {
                throw new \InvalidArgumentException("'content_type' index should be EmailManager::HTML or EmailManager::PLAIN");
            }
            $params['reply_content_type'] = $data['content_type'];
        }
        if (isset($data['reply_time']) and $data['reply_time']) {
            $params['reply_once_time'] = $data['reply_time'];
        }
        if (isset($data['text'])) {
            $params['text'] = $data['text'];
        }
        if (isset($data['subject']) and $data['subject']) {
            $params['subject'] = $data['subject'];
        }
        if (isset($data['cc']) and $data['cc']) {
            $params['cc'] = 'ON';
            $params['email'] = $data['cc'];
        }
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket->set_method('POST');
        $socket->query('/CMD_API_EMAIL_AUTORESPONDER', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }
        if (isset($params['email'])) {
            $params['cc'] = $params['email'];
        }
        unset($params['action'], $params['json'], $params['email']);

        return $params;
    }

    public function deleteAutoResponder(array $data): ?bool
    {
        if (!isset($data['username']) or !$data['username'] or !is_string($data['username'])) {
            throw new \InvalidArgumentException("you should pass: 'username' index and it must be as string");
        }
        $domain = (isset($data['domain']) and $data['domain']) ? $data['domain'] : $this->account->getDomain();
        $impersonate = $this->preQuery($username, $level); // $username, $level passed by refrence
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'delete',
            'domain' => $domain,
            'select1' => $data['username'],
        ];
        $socket->query('/CMD_API_EMAIL_AUTORESPONDER', $params);
        $result = $socket->fetch_parsed_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }
        if (isset($result['error']) and $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }

        return true;
    }

    private function preQuery(&$username, &$level): bool
    {
        $username = $this->api->getUsername();
        $accountUsername = $this->account->getUsername();
        $impersonate = $username != $accountUsername;
        if ($impersonate) {
            $level = $this->api->getLevel();
            $this->api->setUsername($accountUsername, API::User, true);
        }

        return $impersonate;
    }
}
