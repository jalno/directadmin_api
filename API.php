<?php
namespace packages\directadmin_api;
class API {
	const Admin = 1;
	const Reseller = 2;
	const User = 3;
	private $host;
	private $ssl = false;
	private $port = 2222;
	private $username;
	private $password;
	private $socket;
	private $accounts;
	private $level;
	public function __construct(string $host, int $port = 2222, bool $ssl = false){
		$this->host = $host;
		$this->ssl = $ssl;
		$this->port = $port;
	}
	public function setUsername(string $username, int $level, bool $impersonate = false) {
		$this->level = $level;
		if ($impersonate) {
			$this->username = $this->username . "|" . $username;
		} else {
			$this->username = $username;
		}
		if ($this->socket and $this->password) {
			$this->socket->set_login($this->username, $this->password);
		}
	}
	public function setPassword(string $password) {
		$this->password = $password;
		if ($this->socket and $this->username) {
			$this->socket->set_login($this->username,$this->password);
		}
	}
	public function getAccounts() {
		if (!$this->username or !$this->password) {
			throw new Exception("username or password is empty");
		}
		return new Accounts($this);
	}
	public function getSocket(): HTTPSocket {
		if (!$this->socket) {
			$this->socket = new HTTPSocket;
			if ($this->ssl) {
				$this->socket->connect("ssl://".$this->host, $this->port);
			} else {
				$this->socket->connect($this->host, $this->port);
			}
			if ($this->username and $this->password) {
				$this->socket->set_login($this->username, $this->password);
			}
		}
		return $this->socket;
	}
	public function getLevel(): int {
		return $this->level;
	}
	public function getAccount() {
		$splitor = strpos($this->username, "|");
		return Account::importByUsername($this, $splitor !== false ? substr($this->username, $splitor + 1) : $this->username);
	}
	public function getHost(): string {
		return ($this->ssl ? "https" : "http") . "://{$this->host}:{$this->port}";
	}
	public function getUsername() {
		return $this->username;
	}
	public function getPassword() {
		return $this->password;
	}
}
	