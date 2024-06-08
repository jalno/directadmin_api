<?php

namespace packages\directadmin_api;

class BackupManager
{
    /**
     * @var API
     */
    protected $api;

    public function __construct(API $api)
    {
        $this->api = $api;
    }

    /**
     * Get list of current backuping cronjob.
     *
     * @return array[]
     */
    public function getCronjobs(): array
    {
        $socket = $this->api->getSocket();
        $socket->set_method('GET');
        $params = [
            'json' => 'yes',
            'tab' => 'scheduled',
        ];
        $socket->query('/CMD_ADMIN_BACKUP', $params);
        $result = $socket->fetch_parsed_body();
        $crons = [];
        foreach ($result['crons'] as $key => $data) {
            if (!is_numeric($key)) {
                continue;
            }
            $crons[] = $data;
        }

        return $crons;
    }

    /**
     * Remove a backup cronjob.
     */
    public function removeCronjob(int $id): void
    {
        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $params = [
            'action' => 'delete',
            'select0' => $id,
        ];
        $socket->query('/CMD_API_ADMIN_BACKUP', $params);
        $result = $socket->fetch_parsed_body();
        if (!isset($result['error']) or $result['error']) {
            $FailedException = new FailedException();
            $FailedException->setRequest($params);
            $FailedException->setResponse($result);
            throw $FailedException;
        }
    }

    /**
     * Save a new backup cronjob or run a backup.
     *
     * @param string[]|string $who          "all" or list of usernames
     * @param string|array    $when         "now" or cron settings, an array with these keys:
     *                                      "dayofmonth": int (1-31) or "*"
     *                                      "dayofweek": int (0-7) or "*" (7 is Sunday)
     *                                      "hour": int (0-23) or "*"
     *                                      "minute": int (0-59) or "*"
     *                                      "month": int (1-12) or "*"
     * @param string|array    $where        a local location as string or an array for remote ftp with these keys:
     *                                      "ip": string
     *                                      "username": string
     *                                      "password": string
     *                                      "port": uint16
     *                                      "path": string
     *                                      "secure": bool
     * @param string|array    $what         "all" or list of options:
     *                                      "domain" (Domains Directory)
     *                                      "subdomain" (Subdomain Lists)
     *                                      "ftp" (Ftp Accounts)
     *                                      "ftpsettings" (Ftp Settings)
     *                                      "database" (Database Settings)
     *                                      "database_data" (Database Data)
     *                                      "email" (E-Mail Accounts)
     *                                      "email_data" (E-Mail Data)
     *                                      "emailsettings" (E-Mail Settings)
     *                                      "vacation" (Vacation Messages)
     *                                      "autoresponder" (Autoresponders)
     *                                      "list" (Mailing Lists)
     *                                      "forwarder" (Forwarders)
     * @param bool            $skipSuspends whether skip suspended users or not
     * @param string|null     $appendPath   null for nothing or one of these:
     *                                      "dayofweek" (Day of Week: /Friday)
     *                                      "dayofmonth" (Day of Month: /20)
     *                                      "weekofmonth" (Week of Month: /week-3)
     *                                      "month" (Month: /Sep)
     *                                      "date" (Full Date: /2019-09-20)
     *                                      or an custom append {@see https://help.directadmin.com/item.php?id=539}
     */
    public function create($who, $when, $where, $what, bool $skipSuspends = false, ?string $appendPath = null)
    {
        $params = [
            'action' => 'create',
        ];
        if ($skipSuspends) {
            $params['skip_suspended'] = 'yes';
        }
        if ($appendPath) {
            if (in_array($appendPath, ['dayofweek', 'dayofmonth', 'weekofmonth', 'month', 'date'])) {
                $params['append_path'] = $appendPath;
            } else {
                $params['append_path'] = 'custom';
                $params['custom_append'] = $appendPath;
            }
        } else {
            $params['append_path'] = 'nothing';
        }

        if (is_string($who)) {
            if ('all' != $who) {
                throw new Exception('wrong passed argument to $who');
            }
            $params['who'] = 'all';
        } elseif (is_array($who)) {
            $params['who'] = 'selected';
            $x = 0;
            foreach ($who as $user) {
                $params['select'.($x++)] = $user;
            }
        } else {
            throw new Exception('wrong passed argument to $who');
        }

        if (is_string($when)) {
            if ('now' != $when) {
                throw new Exception('wrong passed argument to $when');
            }
            $params['when'] = 'now';
        } elseif (is_array($when)) {
            $params['when'] = 'cron';
            foreach ($when as $key => $value) {
                $params[$key] = $value;
            }
        } else {
            throw new Exception('wrong passed argument to $when');
        }

        if (is_string($where)) {
            $params['where'] = 'local';
            $params['local_path'] = $where;
        } elseif (is_array($where)) {
            $params['where'] = 'ftp';
            foreach ($where as $key => $value) {
                $params['ftp_'.$key] = $value;
            }
            if (isset($params['ftp_secure'])) {
                $params['ftp_secure'] = $params['ftp_secure'] ? 'ftps' : 'no';
            }
        } else {
            throw new Exception('wrong passed argument to $where');
        }

        if (is_string($what)) {
            if ('all' != $what) {
                throw new Exception('wrong passed argument to $what');
            }
            $params['what'] = 'all';
        } elseif (is_array($what)) {
            $params['what'] = 'select';
            $x = 0;
            foreach ($what as $option) {
                $params['option'.($x++)] = $option;
            }
        } else {
            throw new Exception('wrong passed argument to $what');
        }

        $socket = $this->api->getSocket();
        $socket->set_method('POST');
        $query = '';
        switch ($this->api->getLevel()) {
            case API::Admin: $query = 'CMD_API_ADMIN_BACKUP';
                break;
            case API::Reseller: $query = 'CMD_API_USER_BACKUP';
                break;
        }
        $socket->query('/'.$query, $params);
        $results = $socket->fetch_parsed_body();
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
    }
}
