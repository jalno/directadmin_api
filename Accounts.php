<?php
namespace packages\directadmin_api;
use packages\base\{date, IO\file, log};
class Accounts {
	private $api;
	private $socket;
	public function __construct(API $api) {
		$this->api = $api;
		$this->socket = $this->api->getSocket();
	}
	public function all() {
		$accounts = [];
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_ALL_USER_USAGE");
		$rawBody = $this->socket->fetch_body();
		$results = $this->socket->fetch_parsed_body();
		if(isset($results["error"]) and $results["error"] == 1){
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
	public function summeryList() {
		$accounts = [];
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_ALL_USER_USAGE");
		$rawBody = $this->socket->fetch_body();
		if (stripos($rawBody, "error") !== false) {
			$results = $this->socket->fetch_parsed_body();
			if(isset($results["error"]) and $results["error"] == 1){
				throw new FailedException($result);
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
	public function byUsername(string $username) {
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_ALL_USER_USAGE");
		$rawBody = $this->socket->fetch_body();
		if (stripos($rawBody, "error") !== false) {
			$results = $this->socket->fetch_parsed_body();
			if(isset($results["error"]) and $results["error"] == 1){
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
	}
	public function byDomain(string $domain) {
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_ALL_USER_USAGE");
		$rawBody = $this->socket->fetch_body();
		if (stripos($rawBody, "error") !== false) {
			$results = $this->socket->fetch_parsed_body();
			if(isset($results["error"]) and $results["error"] == 1){
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
	}
	public function backups() {
		$files = [];
		if ($this->api->getLevel() == API::Admin) {
			$this->socket->set_method("GET");
			$this->socket->query("/CMD_API_ADMIN_BACKUP");
			$rawBody = $this->socket->fetch_body();
			$results = $this->socket->fetch_parsed_body();
			if(isset($results["error"]) and $results["error"] == 1){
				throw new FailedException($result);
			}
			foreach($results as $key => $value) {
				if (substr($key, 0, 4) == "file") {
					$files[] = $results["location"] . "/" . $value;
				}
			}
		} else if ($this->api->getLevel() == API::Reseller) {
			$this->socket->set_method("GET");
			$this->socket->query("/CMD_API_USER_BACKUP");
			$rawBody = $this->socket->fetch_body();
			$results = $this->socket->fetch_parsed_body();
			if(isset($results["error"]) and $results["error"] == 1){
				throw new FailedException($result);
			}
			foreach($results as $key => $value) {
				if (substr($key, 0, 4) == "file") {
					$files[] = $results["USER_BACKUPS_DIR"] . "/" . $value;
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
	 */
	public function backup(array $users, int $timeout = 1200, ?array $location = array(), ?array $what = []) {
		$files = $this->backups();
		$userBackups = [];
		$result = [];
		foreach ($users as $user) {
			$userBackups[$user] = [];
			foreach ($files as $file) {
				$basename = substr($file, strrpos($file, "/") + 1);
				if (strpos($basename, $user) !== false) {
					$userBackups[$user][] = $file;
				}
			}
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
		$countUsers = count($users);
		while (count($result) != $countUsers and ($timeout == 0 or date::time() - $startAt < $timeout)) {
			if ($params["where"] == "local") {
				$files = $this->backups();
				foreach ($users as $user) {
					if (!isset($result[$user])) {
						foreach ($files as $file) {
							$basename = substr($file, strrpos($file, "/") + 1);
							if (strpos($basename, $user) !== false && !in_array($file, $userBackups[$user])) {
								$result[$user] = $file;
							}
						}
					}
				}
			} else if ($params["where"] == "ftp") {
				$file = new file\ftp();
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
			sleep(1);
		}
		return $result;
	}
	public function restore(array $files, string $ip = null, int $timeout = 1200, array $location = array()) {
		$log = log::getInstance();
		if ($this->api->getLevel() == API::Admin) {
			$log->info("init restore params");
			$params = array(
				"action" => "restore",
				"who" => "selected",
			);	
			$basenames = array();
			foreach($files as $file) {
				$slashpos = strrpos($file, "/");
				if ($slashpos !== false) {
					$basenames[] = substr($file, strrpos($file, "/") + 1);
				} else {
					$basenames[] = $file;
				}
			}
			if ($location) {
				$params["where"] = $location["where"];
				if ($location["where"] == "ftp") {
					$params["ftp_ip"] = $location["hostname"];
					$params["ftp_username"] = $location["username"];
					$params["ftp_password"] = $location["password"];
					$params["ftp_port"] = $location["port"];
					$params["ftp_path"] = $location["directory"];
				} else {
					throw new Exception("unknown location for restore backup");
				}
			} else {
				$dir = "";
				$params["where"] = "local";
				foreach($files as $file) {
					$slashpos = strrpos($file, "/");
					if ($slashpos !== false) {
						$fileDir = substr($file, 0, $slashpos);
						if ($dir and $fileDir != $dir) {
							throw new Exception("multiplte directories");
						}
						$dir = $fileDir;
					}
				}
				$params["local_path"] = $dir ? $dir : "/home/admin/admin_backups";
			}
			if ($ip) {
				$params["ip_choice"] = "select";
				$params["ip"] = $ip;
			}
			foreach($basenames as $key => $file) {
				$params["select" . $key] = $file;
			}
			$log->reply($params);
			$log->info("try to restore");
			$this->socket->set_method("POST");
			$this->socket->query("/CMD_API_ADMIN_BACKUP", $params);
			$result = $this->socket->fetch_parsed_body();
			if(isset($result["error"]) and $result["error"] or empty($result)){
				$log->reply()->fatal("failed");
				$FailedExeption = new FailedException();
				$FailedExeption->setRequest($params);
				$FailedExeption->setResponse($result);
				throw $FailedExeption;
			}
			$log->reply("no problem");
			$log->info("looking files for get username");
			$startAt = date::time();
			$founds = [];
			$usernames = [];
			foreach($basenames as $file) {
				if (preg_match("/\.(\w+)\.tar\.gz$/", $file, $matches)) {
					$usernames[] = $matches[1];
				}
			}
			$userCounts = count($usernames);
			$log->reply($userCounts, "found");
			$log->info("looking in accouns for needed usernames");
			while(($timeout == 0 or date::time() - $startAt < $timeout) and count($founds) != $userCounts) {
				$all = $this->all();
				$log->info(count($all), "user found, check it on my usernames");
				foreach($usernames as $user) {
					$log->info("looking for ", $user);
					if (in_array($user, $founds)) {
						$log->reply("oh, i found it before");
						continue;
					}
					foreach($all as $account) {
						if ($account->getUsername() == $user) {
							$log->reply("found it, save it on mind");
							$founds[] = $user;
						}
					}
				}
				sleep(1);
			}
			return $founds;
		}
	}
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
	public function getNewAccount(string $username, string $domain, string $email): Account {
		return new Account($this->api, $username, $domain, $email);
	}
}