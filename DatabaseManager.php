<?php
namespace packages\directadmin_api;
use packages\base\{IO, IO\File, Log, HTTP, HTTP\ClientException, HTTP\ServerException};

class DatabaseManager {
	protected $api;
	protected $account;
	public function __construct(Account $account) {
		$this->account = $account;
		$this->api = $this->account->getAPI();
	}
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
		$socket->query("/CMD_API_DATABASES");
		$result = $socket->fetch_parsed_body();

		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}

		if (isset($result["error"]) and $result["error"]) {
			$FailedException = new FailedException();
			$FailedException->setResponse($result);
			throw $FailedException;
		}
		return $result["list"];
	}
	public function create(string $name, string $user, string $password) {
		$username = $this->api->getUsername();
		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$socket = $this->api->getSocket();
		$params = array(
			'action' => 'create',
			'name' => $name,
			'user' => $user,
			'passwd' => $password,
			'passwd2' => $password
		);
		$socket->set_method("POST");
		$socket->query("/CMD_API_DATABASES", $params);
		$result = $socket->fetch_parsed_body();

		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}

		if (!isset($result["error"]) or $result["error"]) {
			$FailedException = new FailedException();
			$FailedException->setRequest($params);
			$FailedException->setResponse($result);
			throw $FailedException;
		}
	}
	public function delete($name) {
		$username = $this->api->getUsername();
		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$socket = $this->api->getSocket();
		$params = array(
			'action' => 'delete'
		);
		if (is_array($name)) {
			foreach(array_values($name) as $key => $db) {
				$params['select'.$key] = $db;
			}
		} else {
			$params['select0'] = $name;
		}
		$socket->set_method("POST");
		$socket->query("/CMD_API_DATABASES", $params);
		$result = $socket->fetch_parsed_body();

		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}

		if ((isset($result["error"]) and $result["error"])) {
			$FailedException = new FailedException();
			$FailedException->setRequest($params);
			$FailedException->setResponse($result);
			throw $FailedException;
		}
	}
	public function setPassword(string $name, string $user, string $password, string $domain = "") {
		if (!$domain) {
			$domain = $this->account->getDomain();
		}
		$username = $this->api->getUsername();
		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$socket = $this->api->getSocket();
		$params = array(
			'action' => 'modifyuser',
			'domain' => $domain,
			'name' => $name,
			'user' => $user,
			'passwd' => $password,
			'passwd2' => $password
		);
		$socket->set_method("POST");
		$socket->query("/CMD_API_DATABASES", $params);
		$result = $socket->fetch_parsed_body();

		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}

		if ((isset($result["error"]) and $result["error"])) {
			$FailedException = new FailedException();
			$FailedException->setRequest($params);
			$FailedException->setResponse($result);
			throw $FailedException;
		}
	}
}