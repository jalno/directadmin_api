<?php
namespace packages\directadmin_api;
class API {

	const Admin = 1;
	const Reseller = 2;
	const User = 3;

	/** @var string */
	private $host;
	/** @var bool */
	private $ssl = false;
	/** @var int */
	private $port = 2222;
	/** @var string */
	private $username;
	/** @var string */
	private $password;
	/** @var HTTPSocket|null */
	private $socket;
	/** @var int */
	private $level;

	public function __construct(string $host, int $port = 2222, bool $ssl = false){
		$this->host = $host;
		$this->ssl = $ssl;
		$this->port = $port;
	}

	/**
	 * @param string $username
	 * @param int $level
	 * @param bool $impersonate
	 * @return void
	 */
	public function setUsername(string $username, int $level, bool $impersonate = false): void {
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

	/**
	 * @param string $password
	 * @return void
	 */
	public function setPassword(string $password): void {
		$this->password = $password;
		if ($this->socket and $this->username) {
			$this->socket->set_login($this->username,$this->password);
		}
	}

	/**
	 * @return Accounts
	 * @throws Exception
	 */
	public function getAccounts() {
		if (!$this->username or !$this->password) {
			throw new Exception("username or password is empty");
		}
		return new Accounts($this);
	}

	/**
	 * @return HTTPSocket
	 */
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
	
	/**
	 * @return Account
	 * @throws FailedException
	 * @throws NotFoundAccountException
	 */
	public function getAccount(): Account {
		$splitor = strpos($this->username, "|");
		return Account::importByUsername($this, $splitor !== false ? substr($this->username, $splitor + 1) : $this->username);
	}
	public function getHost(): string {
		return ($this->ssl ? "https" : "http") . "://{$this->host}:{$this->port}";
	}
	public function getUsername(): string {
		return $this->username;
	}
	public function getPassword(): string {
		return $this->password;
	}

	public function getDNS(): DNSManager {
		return new DNSManager($this);
	}
	public function getBackupManager(): BackupManager {
		return new BackupManager($this);
	}
}
	