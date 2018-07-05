<?php
namespace packages\directadmin_api;
use packages\base\{date, log, utility};

class Account {
	const unlimited = -1;
	const skinEnhanced = "enhanced";
	const skinDefault = "default";
	const skinPowerUser = "power_user";
	public static function importByUsername(API $api, string $username) {
		$account = new static($api);
		$account->username = $username;
		$account->reload();
		return $account;
	}
	protected $api;
	protected $socket;
	protected $domain;
	protected $username;
	protected $password;
	protected $email;
	protected $quota;
	protected $maxQuota = self::unlimited;
	protected $bandwidth;
	protected $maxBandwidth = self::unlimited;
	protected $ftps;
	protected $maxFtps = self::unlimited;
	protected $emails;
	protected $maxEmails = self::unlimited;
	protected $mailingLists;
	protected $maxMailingLists = self::unlimited;
	protected $sqls;
	protected $maxSqls = self::unlimited;
	protected $subDomains;
	protected $maxSubDomains = self::unlimited;
	protected $parkDomains;
	protected $maxParkDomains = self::unlimited;
	protected $addonDomains;
	protected $maxAddonDomains = self::unlimited;
	protected $php = true;
	protected $reseller = false;
	protected $ip;
	protected $anonymousFtp = false;
	protected $cgi = false;
	protected $spam = true;
	protected $cron = true;
	protected $ssl = true;
	protected $sysinfo = false;
	protected $shell = false;
	protected $skin = self::skinEnhanced;
	protected $notify = true;
	protected $dnscontrol = false;
	protected $maxEmailForwarders = self::unlimited;
	protected $emailForwarders;
	protected $maxEmailResponders = self::unlimited;
	protected $emailResponders;
	protected $nameservers = [];

	public function __construct(API $api) {
		$this->api = $api;
		$this->socket = $api->getSocket();
	}
	public function reload() {
		$this->socket->set_method("GET");
		$params = ["user" => $this->username];
		$this->socket->query("/CMD_API_SHOW_USER_CONFIG", $params);
		$result = $this->socket->fetch_parsed_body();
		if (!$result) {
			$exception = new FailedException();
			$exception->setRequest($params);
			throw $exception;
		}
		if (isset($result["error"]) and $result["error"] == 1) {
			if ($result["text"] == "Unable to show user") {
				$exception = new NotFoundAccountException($this->username);
			} else {
				$exception = new FailedException();
			}
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_SHOW_USER_USAGE", $params);
		$usage = $this->socket->fetch_parsed_body();
		if (!$usage) {
			$exception = new FailedException();
			$exception->setRequest($params);
			throw $exception;
		}
		if (isset($usage["error"]) and $usage["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($usage);
			throw $exception;
		}
		$this->username = $result["username"];
		$this->domain = $result["domain"];
		$this->maxQuota = $result["quota"] != "unlimited" ? $result["quota"] : self::unlimited;
		$this->maxBandwidth = $result["bandwidth"] != "unlimited" ? $result["bandwidth"] : self::unlimited;
		$this->maxEmails = $result["nemails"] != "unlimited" ? $result["nemails"] : self::unlimited;
		$this->maxFtps = $result["ftp"] != "unlimited" ? $result["ftp"] : self::unlimited;
		$this->maxAddonDomains = $result["vdomains"] != "unlimited" ? $result["vdomains"] : self::unlimited;
		$this->maxParkDomains = $result["domainptr"] != "unlimited" ? $result["domainptr"] : self::unlimited;
		$this->maxSubDomains = $result["nsubdomains"] != "unlimited" ? $result["nsubdomains"] : self::unlimited;
		$this->maxSqls = $result["mysql"] != "unlimited" ? $result["mysql"] : self::unlimited;
		$this->maxEmailForwarders = $result["nemailf"] != "unlimited" ? $result["nemailf"] : self::unlimited;
		$this->maxEmailResponders = $result["nemailr"] != "unlimited" ? $result["nemailr"] : self::unlimited;
		$this->reseller = ($result["account"] != "ON");
		$this->anonymousFtp = ($result["aftp"] == "ON");
		$this->php = ($result["php"] == "ON");
		$this->spam = ($result["spam"] == "ON");
		$this->cron = ($result["cron"] == "ON");
		$this->sysinfo = ($result["sysinfo"] == "ON");
		$this->ssh = ($result["ssh"] == "ON");
		$this->ssl = ($result["ssl"] == "ON");
		$this->dnscontrol = ($result["dnscontrol"] == "ON");
		$this->cgi = ($result["cgi"] == "ON");
		$this->skin = $result["skin"];
		$this->package = $result["package"];
		$this->suspended = ($result["suspended"] == "yes") ;
		$this->ip = $result["ip"];
		$this->quota = intval($usage["quota"]);
		$this->bandwidth = intval($usage["bandwidth"]);
		$this->ftps = intval($usage["ftp"]);
		$this->emails = intval($usage["nemails"]);
		$this->mailingLists = intval($usage["nemailml"]);
		$this->sqls = intval($usage["mysql"]);
		$this->subDomains = intval($usage["nsubdomains"]);
		$this->parkDomains = intval($usage["domainptr"]);
		$this->addonDomains = intval($usage["vdomains"]);
		$this->emailForwarders = intval($usage["nemailf"]);
		$this->emailResponders = intval($usage["nemailr"]);
	}
	public function create() {
		if ($this->reseller) {
			$query = "CMD_API_ACCOUNT_RESELLER";
		} else {
			$query = "CMD_API_ACCOUNT_USER";
		}
		$params = array(
			"action" => "create",
			"add" => "Submit",
			"email" => $this->email,
			"domain" => $this->domain,
			"aftp" => $this->anonymousFtp ? "ON" : "OFF",
			"cgi" => $this->cgi ? "ON" : "OFF",
			"php" => $this->php ? "ON" : "OFF",
			"spam" => $this->spam ? "ON" : "OFF",
			"cron" => $this->cron ? "ON" : "OFF",
			"ssl" => $this->ssl ? "ON" : "OFF",
			"sysinfo" => $this->sysinfo ? "ON" : "OFF",
			"ssh" => $this->shell ? "ON" : "OFF",
			"dnscontrol" => $this->dnscontrol ? "ON" : "OFF"
		);
		$params["username"] = $this->username ? $this->username : $this->createUsername($this->domain);
		$params["passwd"] = $this->password ? $this->password : utility\password::generate();
		$params["passwd2"] = $params["passwd"];
		$params["notify"] = $this->notify ? "yes" : "no";
		if ($this->reseller) {
			$params["ips"] = 0;
			$params["dns"] = "OFF";
			$params["serverip"] = "ON";
			$params["ip"] = "shared";
		} else {
			$params["ip"] = $this->ip;
		}
		$params["uquota"] = $this->maxQuota == self::unlimited ? "ON" : $this->maxQuota;
		$params["bandwidth"] = $this->maxBandwidth == self::unlimited ? "ON" : $this->maxBandwidth;
		$params["vdomains"] = $this->maxAddonDomains == self::unlimited ? "ON" : $this->maxAddonDomains;
		$params["vdomains"] = $this->maxAddonDomains == self::unlimited ? "ON" : $this->maxAddonDomains;
		$params["nsubdomains"] = $this->maxSubDomains == self::unlimited ? "ON" : $this->maxSubDomains;
		$params["nemails"] = $this->maxEmails == self::unlimited ? "ON" : $this->maxEmails;
		$params["nemailml"] = $this->maxMailingLists == self::unlimited ? "ON" : $this->maxMailingLists;
		$params["nemailr"] = $this->maxEmailResponders == self::unlimited ? "ON" : $this->maxEmailResponders;
		$params["unemailf"] = $this->maxEmailForwarders == self::unlimited ? "ON" : $this->maxEmailForwarders;
		$params["mysql"] = $this->maxSqls == self::unlimited ? "ON" : $this->maxSqls;
		$params["domainptr"] = $this->maxParkDomains == self::unlimited ? "ON" : $this->maxParkDomains;
		$params["ftp"] = $this->maxFtps == self::unlimited ? "ON" : $this->maxFtps;

		$this->socket->query("/" . $query, $params);
		$result = $this->socket->fetch_parsed_body();
		if (!$result) {
			$exception = new FailedException();
			$exception->setRequest($params);
			throw $exception;
		}
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}

		$this->username = $params["username"];
		$this->password = $params["passwd"];
		$this->email = $params["email"];
	}
	public function delete() {
		$this->socket->set_method("POST");
		$params = array(
			"confirmed" => "Confirm",
			"delete" => "yes",
			"select0" => $this->username
		);
		$this->socket->query("/CMD_API_SELECT_USERS", $params);
		$result = $this->socket->fetch_parsed_body();
		if (!$result) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		if (isset($result["error"]) and $result["error"] == 1) {
			if ($result["text"] == "Error while deleting Users") {
				$exception = new NotFoundAccountException($this->username);
			} else {
				$exception = new FailedException();
			}
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
	}
	public function restore(string $file, string $ip = null, int $timeout = 1200): Account {
		$params = array(
			"action" =>	"restore",
			"domain" =>	$this->domain,
			"file" => substr($file, strrpos($file, "/") + 1),
			"form_version" => 3,
			"select0" => "domain",
			"select1" => "subdomain",
			"select12" => "email_data",
			"select13" => "dns",
			"select2" => "email",
			"select3" => "forwarder",
			"select4" => "autoresponder",
			"select5" => "vacation",
			"select7" => "emailsettings",
			"select8" => "ftp",
			"select9" => "ftpsettings",
		);
		$params = array_merge($params, $detailsParam);
		try {
			$this->delete();
		} catch (NotFoundAccountException $e) {
			
		}
		$this->socket->set_method("POST");
		$this->socket->query("/CMD_API_SITE_BACKUP", $params);
		$result = $this->socket->fetch_parsed_body();
		if (!$result) {
			$exception = new FailedException();
			$exception->setRequest($params);
			throw $exception;
		}
		if(isset($result["error"]) and $result["error"]){
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$startAt = date::time();
		$found = false;
		$countTickets = count($this->getTickets());
		while(($timeout == 0 or date::time() - $startAt < $timeout) and !$found){
			$tickets = $this->getTickets();
			$newcountTickets = count($tickets);
			if ($newcountTickets > $countTickets) {
				$ticket = $tickets[0];
				if (
					strtolower($ticket["subject"]) == "your User files have been restored" and
					strtolower($ticket["new"]) == "yes"
				) {
					$found = true;
				}
			}
			sleep(2);
		}
		if (!$found) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$account->reload();
		return $account;
	}
	public function backup(int $timeout = 600) {
		$log = log::getInstance();
		$log->info("init backup params");
		$params = array(
			"action" => "backup",
			"domain" => $this->domain,
			"form_version" => 3,
			"select0" => "domain",
			"select1" => "subdomain",
			"select10" => "database",
			"select11" => "database_data",
			"select12" => "email_data",
			"select2" => "email",
			"select3" => "forwarder",
			"select4" => "autoresponder",
			"select5" => "vacation",
			"select6" => "list",
			"select7" => "emailsettings",
			"select8" => "ftp",
			"select9" => "ftpsettings",
		);
		$log->info("send query to create backup");
		$this->socket->set_method("POST");
		$this->socket->query("/CMD_API_SITE_BACKUP", $params);
		$result = $this->socket->fetch_parsed_body();
		if ((isset($result["error"]) and $result["error"]) or empty($result)) {
			$log->reply()->fatal("create backup has failed");
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$log->reply("no problem");
		$startAt = date::time();
		$backupIsReady = false;
		$log->info("get system tickets");
		$countTickets = count($this->getTickets());
		$log->reply($countTickets, " ticket found");
		$found = false;
		$log->info("listening for add new ticket in ", $timeout, " Second");
		while(($timeout == 0 or date::time() - $startAt < $timeout) and !$found){
			$log->info("get system tickets for checking new ticket");
			$tickets = $this->getTickets();
			$count = count($tickets);
			$log->reply($count, " ticket found");
			if ($count > $countTickets) {
				$log->info("the new ticket found, check it for sure");
				$ticket = $tickets[0];
				if (
					strtolower($ticket["subject"]) == "your backups are now ready" and
					strtolower($ticket["new"]) == "yes"
				) {
					$log->reply("found ticket that I was looking for");
					$found = true;
					break;
				} else if (
					strtolower($ticket["subject"]) == "an error occurred during the backup" and
					strtolower($ticket["new"]) == "yes"
				) {
					$log->reply()->fatal("Oh.., the create backup process has faield");
					throw new FailedToCreateBackup();
				}
			}
			sleep(2);
		}
		$log->info("looking in backup files for found them");
		$month = date::format("M");
		$year = date::format("Y");
		$day = date::format("j");
		$backupRegexName = "/^backup\-{$month}\-{$day}\-{$year}(\-(\d+))?\.tar\.gz$/i";
		$log->info("the backup name must throw in ", $backupRegexName, " regex");
		$backups = array();
		$log->info("get current backups list");
		$currentBackups = $this->getCurrentBackups();
		$log->reply(count($currentBackups), " backups found");
		$log->info("searchin on for found my lost");
		foreach ($currentBackups as $backup) {
			$log->info("checking ", $backup, " for sure");
			if (preg_match($backupRegexName, $backup, $matches)) {
				$log->info("its seems that I looking for. save it for now");
				$backups[] = $backup;
			}
		}
		if (empty($backups)) {
			$exception = new NotFoundBackupException();
			$exception->setBackups($currentBackups);
			$exception->setBackupRegexName($backupRegexName);
			throw $exception;
		}
		$backup = end($backups);
		$path = "/backups/{$backup}";
		$log->info("backup path:", $path);
		return $path;
	}
	public function changePassword(string $password) {
		$params = array(
			"username" => $this->username,
			"passwd" => $password,
			"passwd2" => $password
		);
		$this->socket->set_method("POST");
		$this->socket->query("/CMD_API_USER_PASSWD", $params);
		$result = $this->socket->fetch_parsed_body();
		if (!$result) {
			$exception = new FailedException();
			$exception->setRequest($params);
			throw $exception;
		}
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
	}
	public function modify(array $modifiedData) {
		if (empty($modifiedData)) {
			throw new Exception("modify data is empty");
		}
		if (isset($modifiedData["password"])) {
			$this->changePassword($modifiedData["password"]);
			unset($modifiedData["password"]);
		}
		$params = array(
			"action" => "customize",
			"user" => $this->username
		);
		foreach ($modifiedData as $key => $value) {
			if ($key == "maxQuota") {
				$params["quota"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "maxBandwidth") {
				$params["bandwidth"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "maxAddonDomains") {
				$params["vdomains"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "maxParkDomains") {
				$params["domainptr"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "maxSubDomains") {
				$params["nsubdomains"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "maxEmails") {
				$params["nemails"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "maxEmailForwarders") {
				$params["nemailf"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "maxEmailResponders") {
				$params["nemailr"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "maxMailingLists") {
				$params["nemailml"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "maxSqls") {
				$params["mysql"] = $value == self::unlimited ? "ON" : $value;
			} else if ($key == "shell") {
				$params["ssh"] = $value ? "ON" : "OFF";
			} else if ($key == "anonymousFtp") {
				$params["aftp"] = $value ? "ON" : "OFF";
			} else if (in_array($key, ["cgi", "php", "spam", "cron", "ssl", "sysinfo", "dnscontrol"])) {
				$params[$key] = $value ? "ON" : "OFF";
			}
		}
		$this->socket->set_method("POST");
		$this->socket->query("/CMD_API_MODIFY_USER", $params);
		$result = $this->socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$this->reload();
	}
	public function suspend() {
		$params = array(
			"suspend" => "Suspend",
			"select0" => $this->username,
		);
		$this->socket->set_method("POST");
		$this->socket->query("/CMD_API_SELECT_USERS", $params);
		$result = $this->socket->fetch_parsed_body();
		if (!$result) {
			$exception = new FailedException();
			$exception->setRequest($params);
			throw $exception;
		}
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
	}
	public function unsuspend() {
		$params = array(
			"suspend" => "Unsuspend",
			"select0" => $this->username,
		);
		$this->socket->set_method("POST");
		$this->socket->query("/CMD_API_SELECT_USERS", $params);
		$result = $this->socket->fetch_parsed_body();
		if (!$result) {
			$exception = new FailedException();
			$exception->setRequest($params);
			throw $exception;
		}
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
	}
	public function setAnonymousFTP(bool $enable) {
		$this->anonymousFtp = $enable;
	}
	public function disableAnonymousFTP() {
		$this->setAnonymousFTP(false);
	}
	public function getAnonymousFtp() {
		return $this->anonymousFtp;
	}
	public function setCGI(bool $enable) {
		$this->cgi = $enable;
	}
	public function disableCGI() {
		$this->setCGI(false);
	}
	public function getCgi() {
		return $this->cgi;
	}
	public function setSpam(bool $enable) {
		$this->spam = $enable;
	}
	public function disableSpam() {
		$this->setSpam(false);
	}
	public function getSpam() {
		return $this->spam;
	}
	public function setCron(bool $enable) {
		$this->cron = $enable;
	}
	public function disableCron() {
		$this->setCron(false);
	}
	public function getCron() {
		return $this->cron;
	}
	public function setSSL(bool $enable) {
		$this->ssl = $enable;
	}
	public function disableSSL() {
		$this->setSSL(false);
	}
	public function getSSL() {
		return $this->ssl;
	}
	public function setSSH(bool $ssh) {
		$this->ssh = $ssh;
	}
	public function getSSH() {
		return $this->ssh;
	}
	public function setSysInfo(bool $enable) {
		$this->sysinfo = $enable;
	}
	public function disableSysInfo() {
		$this->setSysInfo(false);
	}
	public function getSysInfo() {
		return $this->sysinfo;
	}
	public function setShell(bool $enable) {
		$this->shell = $enable;
	}
	public function disableShell() {
		$this->setShell(false);
	}
	public function getShell() {
		return $this->shell;
	}
	public function setDnsControl(bool $enable) {
		$this->dnscontrol = $enable;
	}
	public function disableDnsControl() {
		$this->setDnsControl(false);
	}
	public function getDnsControl() {
		return $this->dnscontrol; 
	}
	public function setNotify(bool $enable) {
		$this->notify = $enable;
	}
	public function disableNotify() {
		$this->setNotify(false);
	}
	public function getNotify() {
		return $this->notify;
	}
	public function setSkin(string $skin) {
		$skin = strtolower($skin);
		if (!in_array($skin, [self::skinEnhanced, self::skinDefault, self::skinPowerUser])) {
			throw new Exception("valid skins is: " . self::skinEnhanced . " , ". self::skinDefault . " and " . self::skinPowerUser);
		}
		$this->skin = $skin;
	}
	public function getSkin() {
		return $this->skin;
	}
	public function setNameServers(array $nameservers) {
		if (count($nameservers) != 2) {
			throw new Exception();
		}
		foreach($nameservers as $nameserver) {
			if (!is_string($nameserver) or !$nameserver) {
				throw new Exception();
			}
		}
		$this->nameservers = $nameservers;
	}
	public function getMaxEmailForwarders(): int{
		return $this->maxEmailForwarders;
	}
	public function setMaxEmailForwarders(int $maxEmailForwarders) {
        if ($maxEmailForwarders < 0 and $maxEmailForwarders != self::unlimited) {
            throw new Exception("max email forward get positive number");
        }
		$this->maxEmailForwarders = $maxEmailForwarders;
    }
	public function getEmailForwarders(): int {
		return $this->emailForwarders;
	}
	public function getMaxEmailResponders(): int {
		return $this->maxEmailResponders;
	}
	public function setMaxEmailResponders(int $maxEmailResponders) {
        if ( $maxEmailResponders < 0 and $maxEmailResponders != self::unlimited) {
            throw new Exception("max email responder get positive number");
        }
		$this->maxEmailResponders = $maxEmailResponders;
    }
	public function getEmailResponders(): int {
		return $this->emailResponders;
	}
	public function setCreator(string $creator) {
		$this->creator = $creator;
	}
	public function getCreator(): string {
		return $this->creator;
	}
	public function setCustomHTTP(string $config, string $domain) {
		$params = array(
			"domain" => $domain,
			"config" => $config,
		);
		$this->socket->set_method("POST");
		$this->socket->query("/CMD_API_CUSTOM_HTTPD",$params);
		$result = $this->socket->fetch_parsed_body();
		if (!$result){
			$exception = new FailedException();
			$exception->setRequest($params);
			throw $exception;
		}
		if (isset($result["error"]) and $result["error"] == 1){
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		return $result;
	}
	public function createUsername(string $str, int $maxlength = 8): string {
		$str = strtolower($str);
		$str = preg_replace("!^\d+!", "", $str);
		$str = preg_replace("!(\W+|_)!", "", $str);
		$str = substr($str, 0, $maxlength-3).rand(100, 999);
		return $str;
	}
	public function getFiles(): FileManager {
		return new FileManager($this);
	}
	public function getDomain(): string {
		return $this->domain;
	}
	public function getUsername(): string{
		return $this->username;
	}
	public function getPassword(): string{
		return $this->password;
	}
	public function setPassword(string $password) {
		$this->password = $password;
	}
	public function getEmail(): string{
		return $this->email;
	}
	public function getMaxQuota(): int{
		return $this->maxQuota;
	}
	public function setMaxQuota(int $maxQuota) {
		if ($maxQuota <= 0 and $maxQuota != self::unlimited) {
			throw new Exception("max Quota get positive number");
		}
		$this->maxQuota = $maxQuota;
	}
	public function getQuota(): int{
		return $this->quota;
	}
	public function getMaxBandwidth(): int{
		return $this->maxBandwidth;
	}
	public function setMaxBandwidth(int $maxBandwidth) {
		if ($maxBandwidth <= 0 and $maxBandwidth != self::unlimited) {
			throw new Exception;
		}
		$this->maxBandwidth = $maxBandwidth;
	}
	public function getBandwidth(): int{
		return $this->bandwidth;
	}
	public function getMaxFtps(): int{
		return $this->maxFtps;
	}
	public function setMaxFtps(int $maxFtps) {
		if ($maxFtps < 0 and $maxFtps != self::unlimited) {
			throw new Exception("max ftp get positive number");
		}
		$this->maxFtps = $maxFtps;
	}
	public function getFtps(): int{
		return $this->ftps;
	}

	public function getMaxEmails(): int{
		return $this->maxEmails;
	}
	public function setMaxEmails(int $maxEmails) {
		if ($maxEmails < 0 and $maxEmails != self::unlimited) {
			throw new Exception("max emails get positive number");
		}
		$this->maxEmails = $maxEmails;
	}
	public function getEmails(): int{
		return $this->emails;
	}
	public function getMaxMailingLists(): int{
		return $this->maxMailingLists;
	}
	public function setMaxMailingLists(int $maxMailingLists) {
		if ($maxMailingLists < 0 and $maxMailingLists != self::unlimited) {
			throw new Exception("max mailing list get positive number");
		}
		$this->maxMailingLists = $maxMailingLists;
	}
	public function getMailingLists(): int{
		return $this->mailingLists;
	}
	
	public function getMaxSqls(): int{
		return $this->maxSqls;
	}
	public function setMaxSqls(int $maxSqls) {
		if ($maxSqls < 0 and $maxSqls != self::unlimited) {
			throw new Exception("max sql get positive number");
		}
		$this->maxSqls = $maxSqls;
	}
	public function getSqls(): int{
		return $this->sqls;
	}
	public function getMaxSubDomains(): int{
		return $this->maxSubDomains;
	}
	public function setMaxSubDomains(int $maxSubDomains) {
		if ($maxSubDomains < 0 and $maxSubDomains != self::unlimited) {
			throw new Exception("max subDomain get positive number");
		}
		$this->maxSubDomains = $maxSubDomains;
	}
	public function getSubDomains(): int{
		return $this->subDomains;
	}
	public function getMaxParkDomains(): int{
		return $this->maxParkDomains;
	}
	public function setMaxParkDomains(int $maxParkDomains) {
		if ($maxParkDomains < 0 and $maxParkDomains != self::unlimited) {
			throw new Exception("max parkdomain get positive number");
		}
		$this->maxParkDomains = $maxParkDomains;
	}
	public function getParkDomains(): int{
		return $this->parkDomains;
	}
	public function getMaxAddonDomains(): int{
		return $this->maxAddonDomains;
	}
	public function setMaxAddonDomains(int $maxAddonDomains) {
		if ($maxAddonDomains < 0 and $maxAddonDomains != self::unlimited) {
			throw new Exception("max addondomain get positive number");
		}
		$this->maxAddonDomains = $maxAddonDomains;
	}
	public function getAddonDomains(): int{
		return $this->addonDomains;
	}

	public function getPHP():bool{
		return $this->php;
	}
	public function setPHP(bool $enable = true) {
		$this->php = $enable;
	}
	public function disablePHP() {
		$this->setPHP(false);
	}
	public function getIP():string{
		return $this->ip;
	}
	public function setIP(string $ip) {
		$this->ip = $ip;
	}
	public function getReseller():string{
		return $this->reseller;
	}
	public function setReseller(bool $enable) {
		$this->reseller = $enable;
	}
	public function isSuspended() {
		return $this->suspended;
	}
	public function setSuspended(bool $status) {
		$this->suspended = $status;
	}
	public function getAPI(): API {
		return $this->api;
	}
	public function setPackage(string $package){
		$this->package = $package;
	}
	public function getPackage(): string{
		return $this->package;
	}
	protected function getTickets(): array {
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_TICKET");
		$result = $this->socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"]) {
			$FailedException = new FailedException();
			$FailedException->setResponse($result);
			throw $FailedException;
		}
		$tickets = array();
		foreach ($result as $key => $data) {
			$values = array();
			$parts = explode("&", $data);
			foreach ($parts as $part) {
				list($name, $value) = explode("=", $part, 2);
				$values[$name] = $value;
			}
			$tickets[$key] = $values;
		}
		usort($tickets, function($a, $b) {
			return $b["time"] - $a["time"];
		});
		return $tickets;
	}
	protected function getCurrentBackups(): array {
		$this->reload();
		$params = array(
			"domain" => $this->domain,
		);
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_SITE_BACKUP", $params);
		$result = $this->socket->fetch_parsed_body();
		if ((isset($result["error"]) and $result["error"]) or !isset($result["list"])) {
			$FailedException = new FailedException();
			$FailedException->setRequest($params);
			$FailedException->setResponse($result);
			throw $FailedException;
		}
		natsort($result["list"]);
		return $result["list"];
	}
}