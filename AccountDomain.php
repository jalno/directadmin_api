<?php
namespace packages\directadmin_api;
class AccountDomain {	
	private $api;
	private $account;

	/**
	 * @var string
	 */
	private $domain;

	/**
	 * @var bool
	 */
	private $active;

	/**
	 * @var bool
	 */
	private $default;

	/**
	 * @var bool
	 */
	private $localMail;

	/**
	 * @var bool
	 */
	private $cgi;

	/**
	 * @var bool
	 */
	private $openBasedir;

	/**
	 * @var bool
	 */
	private $php;

	/**
	 * @var bool
	 */
	private $safemode;

	/**
	 * @var bool
	 */
	private $ssl;

	/**
	 * @var bool
	 */
	private $suspended;


	public function __construct(Account $account, string $domain) {
		$this->account = $account;
		$this->api = $this->account->getAPI();
		$this->domain = $domain;
	}

	public function modify() {
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
	public function changePrivateHTMLPolicy(string $mode) {
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
	 * Get the value of domain
	 */ 
	public function getDomain() {
		return $this->domain;
	}

	/**
	 * Set the value of domain
	 *
	 * @param  string  $domain
	 */ 
	public function setDomain(string $domain) {
		$this->domain = $domain;
	}

	/**
	 * Get the value of active
	 *
	 * @return  bool
	 */ 
	public function getActive() {
		return $this->active;
	}

	/**
	 * Set the value of active
	 *
	 * @param  bool  $active
	 */ 
	public function setActive(bool $active) {
		$this->active = $active;
	}

	/**
	 * Get the value of default
	 *
	 * @return  bool
	 */ 
	public function getDefault() {
		return $this->default;
	}

	/**
	 * Set the value of default
	 *
	 * @param  bool  $default
	 */ 
	public function setDefault(bool $default) {
		$this->default = $default;
	}

	/**
	 * Get the value of localMail
	 *
	 * @return  bool
	 */ 
	public function getLocalMail() {
		return $this->localMail;
	}

	/**
	 * Set the value of localMail
	 *
	 * @param  bool  $localMail
	 */ 
	public function setLocalMail(bool $localMail) {
		$this->localMail = $localMail;
	}

	/**
	 * Get the value of cgi
	 *
	 * @return  bool
	 */ 
	public function getCGI() {
		return $this->cgi;
	}

	/**
	 * Set the value of cgi
	 *
	 * @param  bool  $cgi
	 */ 
	public function setCGI(bool $cgi) {
		$this->cgi = $cgi;
	}

	/**
	 * Get the value of openBasedir
	 *
	 * @return  bool
	 */ 
	public function getOpenBasedir() {
		return $this->openBasedir;
	}

	/**
	 * Set the value of openBasedir
	 *
	 * @param  bool  $openBasedir
	 */ 
	public function setOpenBasedir(bool $openBasedir) {
		$this->openBasedir = $openBasedir;
	}

	/**
	 * Get the value of php
	 *
	 * @return  bool
	 */ 
	public function getPHP() {
		return $this->php;
	}

	/**
	 * Set the value of php
	 *
	 * @param  bool  $php
	 */ 
	public function setPHP(bool $php) {
		$this->php = $php;
	}

	/**
	 * Get the value of safemode
	 *
	 * @return  bool
	 */ 
	public function getSafeMode() {
		return $this->safemode;
	}

	/**
	 * Set the value of safemode
	 *
	 * @param  bool  $safemode
	 */ 
	public function setSafeMode(bool $safemode) {
		$this->safemode = $safemode;
	}

	/**
	 * Get the value of ssl
	 *
	 * @return  bool
	 */ 
	public function getSSL() {
		return $this->ssl;
	}

	/**
	 * Set the value of ssl
	 *
	 * @param  bool  $ssl
	 *
	 * @return  self
	 */ 
	public function setSSL(bool $ssl) {
		$this->ssl = $ssl;
	}

	/**
	 * Get the value of suspended
	 *
	 * @return  bool
	 */ 
	public function getSuspended() {
		return $this->suspended;
	}

	/**
	 * Set the value of suspended
	 *
	 * @param  bool  $suspended
	 *
	 * @return  self
	 */ 
	public function setSuspended(bool $suspended) {
		$this->suspended = $suspended;
	}

}