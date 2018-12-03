<?php
namespace packages\directadmin_api;
use packages\base\{IO, IO\file, log, http, http\clientException, http\serverException};

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
		var_dump($rawBody);
		$lines = explode("\n", $rawBody);
		foreach ($lines as $line) {
			if ($domain = strtok($line, "=")) {
				$result = null;
				parse_str(substr($line, strlen($domain) + 1), $result);
				$domain = urldecode($domain);
				$obj = new AccountDomain($this->account, $domain);
				$obj->setActive(isset($result['active']) and $result['active'] == 'yes');
				$obj->setDefault(isset($result['defaultdomain']) and $result['defaultdomain']== 'yes');
				$obj->setLocalMail(isset($result['local_mail']) and $result['local_mail']== 'yes');
				$obj->setCGI(isset($result['cgi']) and $result['cgi'] == 'ON');
				$obj->setOpenBasedir(isset($result['open_basedir']) and $result['open_basedir'] == 'ON');
				$obj->setPHP(isset($result['php']) and $result['php'] == 'ON');
				$obj->setSafeMode(isset($result['safemode']) and $result['safemode'] == 'ON');
				$obj->setSSL(isset($result['ssl']) and $result['ssl'] == 'ON');
				$domains[] = $obj;
			}
		}
		print_R($domains);
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
}