<?php

namespace packages\directadmin_api;

use packages\base\Log;

class DomainManager
{
    protected $api;
    protected $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->api = $this->account->getAPI();
    }

    /**
     * @return AccountDomain[] list of domains
     *
     * @throws FailedException if faileds or find "error" token in raw response, so maybe throws it by mistake
     */
    public function getList()
    {
        $username = $this->api->getUsername();
        $accountUsername = $this->account->getUsername();
        $impersonate = $username != $accountUsername;
        if ($impersonate) {
            $level = $this->api->getLevel();
            $this->api->setUsername($accountUsername, API::User, true);
        }

        $socket = $this->api->getSocket();
        $socket->set_method('GET');
        $socket->query('/CMD_API_ADDITIONAL_DOMAINS');
        $rawBody = $socket->fetch_body();
        if ($impersonate) {
            $this->api->setUsername($username, $level, false);
        }

        if (false !== stripos($rawBody, 'error')) {
            $result = $socket->fetch_parsed_body();
            $FailedException = new FailedException();
            $FailedException->setResponse($result);
            throw $FailedException;
        }
        $domains = [];
        $lines = explode("\n", $rawBody);
        foreach ($lines as $line) {
            $line = urldecode($line);
            if ($domain = strtok($line, '=')) {
                $result = null;
                parse_str(substr($line, strlen($domain) + 1), $result);
                $obj = new AccountDomain($this->account, $domain);
                $obj->setActive(isset($result['active']) and 'yes' == $result['active']);
                $obj->setDefault(isset($result['defaultdomain']) and 'yes' == $result['defaultdomain']);
                $obj->setLocalMail(isset($result['local_mail']) and 'yes' == $result['local_mail']);
                $obj->setCGI(isset($result['cgi']) and 'ON' == $result['cgi']);
                $obj->setOpenBasedir(isset($result['open_basedir']) and 'ON' == $result['open_basedir']);
                $obj->setPHP(isset($result['php']) and 'ON' == $result['php']);
                $obj->setSafeMode(isset($result['safemode']) and 'ON' == $result['safemode']);
                $obj->setSSL(isset($result['ssl']) and 'ON' == $result['ssl']);
                $obj->setForceSSL(isset($result['force_ssl']) and 'yes' == strtolower($result['force_ssl']));
                $domains[] = $obj;
            }
        }

        return $domains;
    }

    public function byDomain(string $domain)
    {
        foreach ($this->getList() as $item) {
            if ($domain == $item->getDomain()) {
                return $item;
            }
        }

        return null;
    }

    public function changeDomain(string $newDomain): void
    {
        $log = Log::getInstance();
        $log->info('change Domain, old domain:', $this->account->getDomain(), 'new domain:', $newDomain);
        $params = [
            'old_domain' => $this->account->getDomain(),
            'new_domain' => $newDomain,
        ];
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $socket->query('/CMD_API_CHANGE_DOMAIN', $params);
        $result = $socket->fetch_parsed_body();
        if (isset($result['error']) and 1 == $result['error']) {
            $exception = new FailedException();
            $exception->setRequest($params);
            $exception->setResponse($result);
            throw $exception;
        }
    }
}
