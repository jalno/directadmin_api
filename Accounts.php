<?php
namespace packages\directadmin_api;

use packages\base\{Date, Exception, IO\File, Log};

class Accounts {

	/** @var API */
	private $api;
	/** @var HTTPSocket */
	private $socket;

	public function __construct(API $api) {
		$this->api = $api;
		$this->socket = $this->api->getSocket();
	}
	/**
	 * @return array<Account>|array<null>
	 * @throws FailedException
	 * @throws NotFoundAccountException
	 */
	public function all(): array {
		$accounts = [];
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_ALL_USER_USAGE");
		$rawBody = $this->socket->fetch_body();
		$result = $this->socket->fetch_parsed_body();
		if(isset($result["error"]) and $result["error"] == 1){
			throw new FailedException($result);
		}
		$lines = explode("\n", $rawBody);
		foreach ($lines as $line) {
			if($username = strtok($line, "=")){
				$accounts[] = Account::importByUsername($this->api, $username);
			}
		}
		return $accounts;
	}
	/**
	 * @return array<Account>|array<null>
	 * @throws FailedException
	 */
	public function summeryList(): array {
		$accounts = [];
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_ALL_USER_USAGE");
		$rawBody = $this->socket->fetch_body();
		if (stripos($rawBody, "error") !== false) {
			$results = $this->socket->fetch_parsed_body();
			if(isset($results["error"]) and $results["error"] == 1){
				throw new FailedException($results);
			}
		}
		$lines = explode("\n", $rawBody);
		foreach ($lines as $line) {
			if ($username = strtok($line, "=")) {
				$result = null;
				parse_str(substr($line, strlen($username) + 1), $result);
				if (!isset($result["default"]) or $result["default"] == "") {
					continue;
				}
				$account = new Account($this->api, $username, $result["default"]);
				list($quota, $maxQuota) = explode("/", $result["quota"], 2);
				$quota = trim($quota);
				$maxQuota = trim($maxQuota);
				$account->setMaxQuota($maxQuota == "unlimited" ? Account::unlimited : $maxQuota);
				$account->setQuato($quota);
				if (isset($result["package"])) {
					$account->setPackage($result["package"]);
				}
				list($bandwidth, $maxBandwidth) = explode("/", $result["bandwidth"], 2);
				$bandwidth = trim($bandwidth);
				$maxBandwidth = trim($maxBandwidth);
				$account->setMaxBandwidth($maxBandwidth == "unlimited" ? Account::unlimited : $maxBandwidth);
				$account->setBandwidth(intval($bandwidth));
				$account->setSuspended($result["suspended"] != "No");
				list($addonDomains, $maxAddonDomains) = explode("/", $result["vdomains"], 2);
				$addonDomains = trim($addonDomains);
				$maxAddonDomains = trim($maxAddonDomains);
				$account->setMaxAddonDomains($maxAddonDomains == "unlimited" ? Account::unlimited : $maxAddonDomains);
				$account->setAddonDomains($addonDomains);
				$account->setEmails($result["email_deliveries_outgoing"]);
				if (isset($result["email_daily_limit"])) {
					$account->setMaxEmails($result["email_daily_limit"] == "unlimited" ? Account::unlimited : $result["email_daily_limit"]);
				}
				$accounts[] = $account;
			}
		}
		return $accounts;
	}
	/**
	 * @param string $username
	 * @return Account|null
	 * @throws FailedException
	 * @throws NotFoundAccountException
	 */
	public function byUsername(string $username): ?Account {
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_ALL_USER_USAGE");
		$rawBody = $this->socket->fetch_body();
		if (stripos($rawBody, "error") !== false) {
			$result = $this->socket->fetch_parsed_body();
			if(isset($result["error"]) and $result["error"] == 1){
				throw new FailedException($result);
			}
		}
		$lines = explode("\n", $rawBody);
		foreach ($lines as $line) {
			$user = strtok($line, "=");
			if($username == $user){
				return Account::importByUsername($this->api, $username);
			}
		}

		return null;
	}
	
	/**
	 * @param string $domain
	 * @return Account|null
	 * @throws FailedException
	 * @throws NotFoundAccountException
	 */
	public function byDomain(string $domain): ?Account {
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_ALL_USER_USAGE");
		$rawBody = $this->socket->fetch_body();
		if (stripos($rawBody, "error") !== false) {
			$result = $this->socket->fetch_parsed_body();
			if(isset($result["error"]) and $result["error"] == 1){
				throw new FailedException($result);
			}
		}
		$lines = explode("\n", $rawBody);
		foreach ($lines as $line) {
			$firstEq = strpos($line, "=");
			if ($firstEq > 0) {
				$username = substr($line, 0, $firstEq);
				foreach(explode("&", substr($line, $firstEq + 1)) as $part) {
					$firstEq = strpos($part, "=");
					$key = substr($part, 0, $firstEq);
					if ($key == "list") {
						$value = substr($part, $firstEq + 1);
						$value = explode("<br>", $value);
						if (in_array($domain, $value)) {
							return Account::importByUsername($this->api, $username);
						}
					}
				}

			}
		}

		return null;
	}

	/**
	 * @return array<string>|array<null>
	 * @throws FailedException
	 */
	public function backups(): array {
		$files = [];
		if ($this->api->getLevel() == API::Admin) {
			$this->socket->set_method("GET");
			$this->socket->query("/CMD_API_ADMIN_BACKUP");
			$rawBody = $this->socket->fetch_body();
			$result = $this->socket->fetch_parsed_body();
			if(isset($result["error"]) and $result["error"] == 1){
				throw new FailedException($result);
			}
			foreach($result as $key => $value) {
				if (substr($key, 0, 4) == "file") {
					$files[] = $result["location"] . "/" . $value;
				}
			}
		} else if ($this->api->getLevel() == API::Reseller) {
			$this->socket->set_method("GET");
			$this->socket->query("/CMD_API_USER_BACKUP");
			$rawBody = $this->socket->fetch_body();
			$result = $this->socket->fetch_parsed_body();
			if(isset($result["error"]) and $result["error"] == 1){
				throw new FailedException($result);
			}
			foreach($result as $key => $value) {
				if (substr($key, 0, 4) == "file") {
					$files[] = $result["USER_BACKUPS_DIR"] . "/" . $value;
				}
			}
		}
		return $files;
	}
	/**
	 * @param string[] $users
	 * @param int $timeout
	 * @param array|null $location "where"(string = ftp), "hostname"(string), "username"(string), "password"(string), "port"(int), "directory"(string), "secure" (ftps|ftp)
	 * @param string[]|null $what "domain", "subdomain", "ftp", "ftpsettings", "database", "database_data", "email", "email_data", "emailsettings", "vacation", "autoresponder", "list", "forwarder"
	 * @return array<string,string>
	 * @throws FailedException
	 */
	public function backup(array $users, int $timeout = 1200, ?array $location = array(), ?array $what = []): array {
		
		$log = Log::getInstance();

		$apiAccount = $this->api->getAccount();

		$log->info("get system last message");
		$tickets = $apiAccount->getTickets(array(
			"ipp" => 1,
		));
		$lastTicket = reset($tickets);
		if ($lastTicket) {
			$log->reply("sent in: ", Date::format("Y/m/d H-i-s", $lastTicket["last_message"]));
		} else {
			$log->reply("NotFound");
		}

		$params = array(
			"action" => "create",
			"who" => "selected",
			"when" => "now",
		);
		if ($location) {
			$params["where"] = $location["where"];
			if ($location["where"] == "ftp") {
				$params["ftp_ip"] = $location["hostname"];
				$params["ftp_username"] = $location["username"];
				$params["ftp_password"] = $location["password"];
				$params["ftp_port"] = $location["port"];
				$params["ftp_path"] = $location["directory"];
				$params["ftp_secure"] = isset($location["secure"]) ? $location["secure"] : "ftp";
			} else {
				throw new Exception("unknown location for create backup");
			}
		} else {
			$params["where"] = "local";
			$params["local_path"] = "/home/admin/admin_backups";
		}

		if ($what) {
			$params['what'] = "select";
			foreach($what as $x => $option) {
				$params['option' . $x] = $option;
			}
		} else {
			$params['what'] = "all";
		}

		foreach ($users as $key => $user) {
			$params["select" . $key] = $user;
		}

		$this->socket->set_method("POST");

		$query = "";
		switch ($this->api->getLevel()) {
			case(API::Admin): $query = "CMD_API_ADMIN_BACKUP";break;
			case(API::Reseller): $query = "CMD_API_USER_BACKUP";break;
		}

		$this->socket->query("/" . $query, $params);
		$results = $this->socket->fetch_parsed_body();

		if (!$results) {
			$exception = new FailedException();
			$exception->setRequest($params);
			throw $exception;
		}

		if (isset($results["error"]) and $results["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($results);
			throw $exception;
		}

		$startAt = date::time();

		$checkUsersInTicket = function(string $message) use (&$users): bool {
			$foundedUsers = 0;
			foreach ($users as $user) {
				if (stripos($message, "User {$user} has been backed up") !== false or stripos($message, "{$user}.tar.gz") !== false) {
					$foundedUsers++;
				}
			}
			return $foundedUsers == count($users);
		};
		while ($timeout == 0 or date::time() - $startAt < $timeout) {
			$log->info("get system tickets for checking new ticket");

			$tickets = $apiAccount->getTickets();
			foreach ($tickets as $ticket) {
				if (!$lastTicket or $ticket["last_message"] > $lastTicket["last_message"]) {
					$lastTicket = $ticket;
					$log->info("the new ticket found, sent in: ", Date::format("Y/m/d H-i-s", $ticket["last_message"]));
					$subject = strtolower($ticket["subject"]);
					if (
						substr($subject, 0, strlen("your backups are now ready")) == "your backups are now ready" and
						strtolower($ticket["new"]) == "yes"
					) {
						$content = $apiAccount->getTicket($ticket["message"]);
						if ($content) {
							if ($checkUsersInTicket($content["message"])) {
								$log->reply("found ticket that was looking for");
								break 2;
							} else {
								$log->reply("Sorry. Maybe next time");
							}
						} else {
							$log->reply()->warn("Unable to get message content");
						}
					} else if (
						substr($subject, 0, strlen("an error occurred during the backup")) == "an error occurred during the backup" and
						strtolower($ticket["new"]) == "yes"
					) {
						$log->reply("Oh it's seems an error occurred, Let's check it");
						$content = $apiAccount->getTicket($ticket["message"]);
						if ($content) {
							if ($checkUsersInTicket($content["message"])) {
								$log->reply()->fatal("Sorry.., the create backup process has faield");
								$e = new FailedException();
								$e->setRequest($params);
								$e->setResponse($results);
								throw $e;
							} else {
								$log->reply("Be Happy, It's not our request");
							}
						} else {
							$log->reply()->warn("Unable to get message content");
						}
					}
				}
			}
			sleep(1);
		}
		$result = array();
		if ($params["where"] == "local") {
			$files = $this->backups();
			foreach ($users as $user) {
				foreach ($files as $file) {
					$basename = substr($file, strrpos($file, "/") + 1);
					if (stripos($basename, "{$user}.tar.gz") !== false) {
						$result[$user] = $file;
					}
				}
			}

		} else {
			$file = new file\FTP();
			$file->directory = $location["directory"];
			$file->hostname = $location["hostname"];
			$file->port = $location["port"];
			$file->username = $location["username"];
			$file->password = $location["password"];
			$files = $file->getDirectory()->files();
			$found = false;
			foreach ($files as $f) {
				foreach ($users as $user) {
					if (isset($result[$user])) {
						continue;
					}
					if (preg_match("/^(?:user|reseller|admin)\.(?:\w+)\.(\w+)\.tar\.gz$/", $f->basename, $matches)) {
						if ($matches[1] == $user) {
							$result[$user] = $f;
							$found = true;
						}
					}
				}
			}
			if (!$found) {
				$file->getDriver()->close();
			}
		}
		return $result;
	}

	/**
	 * @param string[] $files
	 * @param string $ip
	 * @param int $timeout
	 * @param array|null $location "where"(string = ftp), "hostname"(string), "username"(string), "password"(string), "port"(int), "directory"(string), "secure" (ftps|ftp)
	 * @return void
	 * @throws Exception
	 * @throws FailedException
	 */
	public function restore(array $files, string $ip = null, int $timeout = 1200, array $location = array()): void {
		$log = Log::getInstance();

		if ($this->api->getLevel() != API::Admin) {
			$log->error("can not restore backup without non-admin level!");
			return;
		}
		$apiAccount = $this->api->getAccount();

		$log->info("get system last message");
		$tickets = $apiAccount->getTickets(array(
			"ipp" => 1,
		));
		$lastTicket = reset($tickets);
		if ($lastTicket) {
			$log->reply("sent in: ", Date::format("Y/m/d H-i-s", $lastTicket["last_message"]));
		} else {
			$log->reply("not found");
		}
		$startAt = Date::time();


		$log->info("init restore params");
		$params = array(
			"action" => "restore",
			"who" => "selected",
		);
		if ($location) {
			if ($location["where"] == "ftp") {
				$params["where"] = $location["where"];
				$params["ftp_ip"] = $location["hostname"];
				$params["ftp_username"] = $location["username"];
				$params["ftp_password"] = $location["password"];
				$params["ftp_port"] = $location["port"];
				$params["ftp_path"] = $location["directory"];
			} else {
				throw new Exception("unknown location for restore backup:" . $location["where"]);
			}
		} else {
			$params["where"] = "local";

			$commonDir = "";
			foreach ($files as $file) {
				$lastSlashPos = strrpos($file, "/");
				if ($lastSlashPos !== false) {
					$fileDir = substr($file, 0, $lastSlashPos);
					if ($commonDir and $fileDir != $commonDir) {
						throw new Exception("multiplte directories");
					}
					if (!$commonDir) {
						$commonDir = $fileDir;
					}
				}
			}
			$params["local_path"] = $commonDir ? $commonDir : "/home/admin/admin_backups";
		}
		if ($ip) {
			$params["ip_choice"] = "select";
			$params["ip"] = $ip;
		}

		$basenames = array();
		$length = count($files);
		for ($x = 0; $x < $length; $x++) {
			$lastSlashPos = strrpos($file, "/");
			$basename = ($lastSlashPos !== false) ? substr($file, $lastSlashPos + 1) : $file;
			$params["select{$x}"] = $basename;
			$basenames[] = $basename;
		}
		$log->reply($params);

		$log->info("try to restore");
		$this->socket->set_method("POST");
		$this->socket->query("/CMD_API_ADMIN_BACKUP", $params);
		$result = $this->socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] or empty($result)) {
			$log->reply()->fatal("failed");
			$FailedExeption = new FailedException();
			$FailedExeption->setRequest($params);
			$FailedExeption->setResponse($result);
			throw $FailedExeption;
		}
		$log->reply("no problem");

		$checkUsersInTicket = function(string $message) use (&$basenames): bool {
			$foundedUsers = 0;
			foreach ($basenames as $basename) {
				if (stripos($message, "User {$basename} has been restored") !== false or stripos($message, $basename) !== false) {
					$foundedUsers++;
				}
			}
			return $foundedUsers == count($foundedUsers);
		};
		$log->info("get system tickets for checking new ticket, timeout: {$timeout} sec");
		while ($timeout === 0 or Date::time() - $startAt < $timeout) {
			$log->info("get tickets...");
			$tickets = $apiAccount->getTickets();
			$log->reply("done, proccess them...");
			foreach ($tickets as $ticket) {
				if (!$lastTicket or $ticket["last_message"] > $lastTicket["last_message"]) {
					$lastTicket = $ticket;
					$log->info("the new ticket found, sent in: ", Date::format("Y/m/d H-i-s", $ticket["last_message"]));
					$subject = trim(strtolower($ticket["subject"]));
					if (
						substr($subject, 0, strlen("your user files have been restored")) == "your user files have been restored" and
						strtolower($ticket["new"]) == "yes"
					) {
						$content = $apiAccount->getTicket($ticket["message"]);
						if ($content) {
							if ($checkUsersInTicket($content["message"])) {
								$log->reply("found ticket that was looking for");
								break 2;
							} else {
								$log->reply("sorry. maybe next time");
							}
						} else {
							$log->reply()->warn("unable to get message content");
						}
					} else if (
						substr($subject, 0, strlen("an error occurred during the restore")) == "an error occurred during the restore" and
						strtolower($ticket["new"]) == "yes"
					) {
						$log->reply("oh it's seems an error occurred, let's check it");
						$content = $apiAccount->getTicket($ticket["message"]);
						if ($content) {
							if ($checkUsersInTicket($content["message"])) {
								$log->reply()->fatal("sorry.., the restore backup process has faield");
								$e = new FailedException();
								$e->setRequest($params);
								$e->setResponse($content);
								throw $e;
							} else {
								$log->reply("be happy, It's not our request");
							}
						} else {
							$log->reply()->warn("unable to get message content");
						}
					}
				}
			}
			sleep(1);
		}
	}

	/**
	 * @param string $host
	 * @param string $username
	 * @param int $level
	 * @param string $password
	 * @param string $domain
	 * @param string $localIO
	 * @param int $port
	 * @param bool $ssl
	 * @return Account|null
	 * @throws FailedException
	 */
	public function transferFrom(string $host, string $username, int $level, string $password, string $domain, string $localIP, int $port = 2222, bool $ssl = false) {
		$other = new API($host, $port, $ssl);
		$other->setUsername($username, $level);
		$other->setPassword($password);

		$localBackup = new file\tmp();
		if ($level == API::Admin || $level == API::Reseller) {
			$accounts = $other->getAccounts();
			$account = $accounts->byDomain($domain);
			$filePath = $accounts->backup($account->getUsername());
			$adminAccount = $other->getAccount();
			$adminAccount->getFiles()->download($filePath, $localBackup);
		} else {
			$account = $other->getAccount();
			$filePath = $account->backup();
			$account->getFiles()->download($filePath, $localBackup);
		}
		$localPath = "/admin_backups";
		$localBackup->rename("user.admin.{$account->getUsername()}.tar.gz");
		$this->api->getAccount()->getFiles()->upload($localPath, $localBackup);
		$this->restore(array($localBackup->basename), $localIP);
		return $this->byUsername($account->getUsername());
	}
	/**
	 * Delete multiple accounts.
	 * 
	 * @param string[] $users
	 * @return void
	 * @throws FailedException
	 */
	public function delete(array $users): void {
		if (empty($users)) {
			return;
		}
		$this->socket->set_method("POST");
		$params = array(
			"confirmed" => "Confirm",
			"delete" => "yes",
		);
		foreach ($users as $x => $user) {
			$params['select' . $x] = $user;
		}
		$this->socket->query("/CMD_API_SELECT_USERS", $params);
		$result = $this->socket->fetch_parsed_body();
		if (!$result) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
	}

	/**
	 * @param string $username
	 * @param string $domain
	 * @param string $email
	 * @return Account
	 */
	public function getNewAccount(string $username, string $domain, string $email): Account {
		return new Account($this->api, $username, $domain, $email);
	}
}