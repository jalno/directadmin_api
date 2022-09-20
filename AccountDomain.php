<?php
namespace packages\directadmin_api;

class AccountDomain {	
	private API $api;
	private Account $account;
	private string $domain;
	private bool $active = false;
	private bool $default = false;
	private bool $localMail = false;
	private bool $cgi = false;
	private bool $openBasedir = false;
	private bool $php = false;
	private bool $safemode = false;
	private bool $ssl = false;
	private bool $forceSSL = false;
	private bool $suspended = false;


	public function __construct(Account $account, string $domain) {
		$this->account = $account;
		$this->api = $this->account->getAPI();
		$this->domain = $domain;
	}

	public function modify(): void {
		$username = $this->api->getUsername();
		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$socket = $this->api->getSocket();
		$params = array(
			'action' => 'modify',
			'domain' => $this->domain,
			'modify' => 'Save',
			'php' => $this->php ? "ON" : "OFF",
			'ssl' => $this->ssl ? "ON" : "OFF",
			'ubandwidth' => 'unlimited',
			'uquota' => 'unlimited',
		);
		$socket->set_method("POST");
		$socket->query("/CMD_API_DOMAIN", $params);
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

	/**
	 * @param string $mode cab be one of ["directory", "symlink"]
	 */
	public function changePrivateHTMLPolicy(string $mode): void {
		$username = $this->api->getUsername();
		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$socket = $this->api->getSocket();
		$params = array(
			'action' => 'private_html',
			'domain' => $this->domain,
			'val' => $mode,
			'force_ssl' => $this->forceSSL ? 'yes' : 'no',
		);

		$socket->set_method("POST");
		$socket->query("/CMD_API_DOMAIN", $params);
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

	public function modifyForceSSL(): void
	{
		$username = $this->api->getUsername();
		$level = $this->api->getLevel();

		$accountUsername = $this->account->getUsername();
		$impersonate = $username != $accountUsername;

		if ($impersonate) {
			$this->api->setUsername($accountUsername, API::User, true);
		}

		$params = array(
			'action' => 'private_html',
			'domain' => $this->domain,
			'force_ssl' => $this->forceSSL ? 'yes' : 'no',
		);

		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$socket->query("/CMD_API_DOMAIN", $params);

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

	public function getDomain(): string {
		return $this->domain;
	}

	public function setDomain(string $domain): void {
		$this->domain = $domain;
	}

	public function getActive(): bool {
		return $this->active;
	}

	public function setActive(bool $active): void {
		$this->active = $active;
	}

	public function getDefault(): bool {
		return $this->default;
	}

	public function setDefault(bool $default): void {
		$this->default = $default;
	}

	public function getLocalMail(): bool {
		return $this->localMail;
	}

	public function setLocalMail(bool $localMail): void {
		$this->localMail = $localMail;
	}

	public function getCGI(): bool {
		return $this->cgi;
	}

	public function setCGI(bool $cgi): void {
		$this->cgi = $cgi;
	}

	public function getOpenBasedir(): bool {
		return $this->openBasedir;
	}

	public function setOpenBasedir(bool $openBasedir): void {
		$this->openBasedir = $openBasedir;
	}

	public function getPHP(): bool {
		return $this->php;
	}

	public function setPHP(bool $php): void {
		$this->php = $php;
	}

	public function getSafeMode(): bool {
		return $this->safemode;
	}

	public function setSafeMode(bool $safemode): void {
		$this->safemode = $safemode;
	}

	public function getSSL(): bool {
		return $this->ssl;
	}

	public function setSSL(bool $ssl): void {
		$this->ssl = $ssl;
	}

	public function getForceSSL(): bool {
		return $this->forceSSL;
	}

	public function setForceSSL(bool $forceSSL): void {
		$this->forceSSL = $forceSSL;
	}

	public function getSuspended(): bool {
		return $this->suspended;
	}

	public function setSuspended(bool $suspended): void {
		$this->suspended = $suspended;
	}

}