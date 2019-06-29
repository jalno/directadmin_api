<?php
namespace packages\directadmin_api;
use packages\base\{log, http, http\clientException, http\serverException};

class DNSManager {
	protected $api;
	protected $account;
	public function __construct($api) {
		if ($api instanceof Account) {
			$this->account = $api;
			$api = $this->account->getAPI();
		}
		$this->api = $api;
	}
	public function add(string $domain, string $type, string $value, ?string $name = null): void {
		if (!$name) {
			$name = $domain . '.';
		}
		
		if ($this->account) {
			$username = $this->api->getUsername();
			$accountUsername = $this->account->getUsername();
			$impersonate = $username != $accountUsername;
			if ($impersonate) {
				$level = $this->api->getLevel();
				$this->api->setUsername($accountUsername, API::User, true);
			}
		} else {
			$impersonate = false;
		}

		$params = array(
			"domain" => $domain,
			"action" => "add",
			"name" => $name,
			"type" => $type,
			"value" => $value,
		);
		$socket = $this->api->getSocket();
		$socket->set_method("GET");
		$socket->query("/CMD_API_DNS_CONTROL", $params);
		$result = $socket->fetch_parsed_body();

		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}

		if (isset($result["error"]) and $result["error"]) {
			$FailedException = new FailedException();
			$FailedException->setRequest($params);
			$FailedException->setResponse($result);
			throw $FailedException;
		}
	}
	public function delete(string $domain, string $type, string $value, ?string $name = null): void {
		if (!$name) {
			$name = $domain . '.';
		}
		
		if ($this->account) {
			$username = $this->api->getUsername();
			$accountUsername = $this->account->getUsername();
			$impersonate = $username != $accountUsername;
			if ($impersonate) {
				$level = $this->api->getLevel();
				$this->api->setUsername($accountUsername, API::User, true);
			}
		} else {
			$impersonate = false;
		}

		$params = array(
			"domain" => $domain,
			"action" => "select",
			strtolower($type) . "recs0"  => "name=" . $name . "&value=" . $value,
		);
		$socket = $this->api->getSocket();
		$socket->set_method("GET");
		$socket->query("/CMD_API_DNS_CONTROL", $params);
		$result = $socket->fetch_parsed_body();

		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}

		if (isset($result["error"]) and $result["error"]) {
			$FailedException = new FailedException();
			$FailedException->setRequest($params);
			$FailedException->setResponse($result);
			throw $FailedException;
		}
	}
}