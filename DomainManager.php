<?php
namespace packages\directadmin_api;
use packages\base\{IO, IO\file, Log, http, http\clientException, http\serverException};

class DomainManager {
	protected $api;
	protected $account;
	public function __construct(Account $account) {
		$this->account = $account;
		$this->api = $this->account->getAPI();
	}
	/**
	 * @throws FailedException if faileds or find "error" token in raw response, so maybe throws it by mistake.
	 * @return AccountDomain[] list of domains
	 */
	public function getList() {
		$username = $this->api->getUsername();
		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$socket = $this->api->getSocket();
		$socket->set_method("GET");
		$socket->query("/CMD_API_ADDITIONAL_DOMAINS");
		$rawBody = $socket->fetch_body();
		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}

		if (stripos($rawBody, "error") !== false) {
			$result = $socket->fetch_parsed_body();
			$FailedException = new FailedException();
			$FailedException->setResponse($result);
			throw $FailedException;
		}
		$domains = [];
		$lines = explode("\n", $rawBody);
		foreach ($lines as $line) {
			$line = urldecode($line);
			if ($domain = strtok($line, "=")) {
				$result = null;
				parse_str(substr($line, strlen($domain) + 1), $result);
				$obj = new AccountDomain($this->account, $domain);
				$obj->setActive(isset($result['active']) and $result['active'] == 'yes');
				$obj->setDefault(isset($result['defaultdomain']) and $result['defaultdomain']== 'yes');
				$obj->setLocalMail(isset($result['local_mail']) and $result['local_mail']== 'yes');
				$obj->setCGI(isset($result['cgi']) and $result['cgi'] == 'ON');
				$obj->setOpenBasedir(isset($result['open_basedir']) and $result['open_basedir'] == 'ON');
				$obj->setPHP(isset($result['php']) and $result['php'] == 'ON');
				$obj->setSafeMode(isset($result['safemode']) and $result['safemode'] == 'ON');
				$obj->setSSL(isset($result['ssl']) and $result['ssl'] == 'ON');
				$obj->setForceSSL(isset($result['force_ssl']) and strtolower($result['force_ssl']) == 'yes');
				$domains[] = $obj;
			}
		}
		return $domains;
	}
	public function byDomain(string $domain) {
		foreach($this->getList() as $item) {
			if ($domain == $item->getDomain()) {
				return $item;
			}
		}
		return null;
	}
	public function changeDomain(string $newDomain): void {
		$log = Log::getInstance();
		$log->info("change Domain, old domain:", $this->account->getDomain(), "new domain:", $newDomain);
		$params = array(
			"old_domain" => $this->account->getDomain(),
			"new_domain" => $newDomain,
		);
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$socket->query("/CMD_API_CHANGE_DOMAIN", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
	}
}