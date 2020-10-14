<?php
namespace packages\directadmin_api;

class DomainPointerManager {
	protected $api;
	protected $account;
	public function __construct(Account $account) {
		$this->account = $account;
		$this->api = $this->account->getAPI();
	}
	/**
	 * @throws FailedException
	 * @return array list of domains alongside their type
	 */
	public function getList(string $domain) {
		$username = $this->api->getUsername();
		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$params = array(
			"domain" => $domain,
		);

		$socket = $this->api->getSocket();
		$socket->set_method("GET");
		$socket->query("/CMD_API_DOMAIN_POINTER", $params);
		$result = $socket->fetch_parsed_body();
		
		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}
	
		if (isset($result["error"]) and $result["error"]) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$pointers = [];
		foreach ($result as $domain => $type) {
			$domain = str_replace("_", ".", $domain);
			$pointers[$domain] = array(
				'type' => $type,
			);
		}
		return $pointers;
	}

	public function add(string $domain, string $pointer, bool $alias = true): void {
		$username = $this->api->getUsername();
		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$params = array(
			"domain" => $domain,
			"action" => "add",
			"from" => $pointer,
		);
		if ($alias) {
			$params['alias'] = "yes";
		}
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$socket->query("/CMD_API_DOMAIN_POINTER", $params);
		$result = $socket->fetch_parsed_body();

		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}

		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
	}

	public function delete(string $domain, string $pointer): void {
		$username = $this->api->getUsername();
		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$params = array(
			"domain" => $domain,
			"action" => "delete",
			"select0" => $pointer,
		);
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$socket->query("/CMD_API_DOMAIN_POINTER", $params);
		$result = $socket->fetch_parsed_body();

		if ($impersonate) {
			$this->api->setUsername($username, $level, false);
		}

		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
	}
}