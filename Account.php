<?php
namespace packages\directadmin_api;
use packages\base\{date, log, utility};

class Account {
	const unlimited = -1;
	const skinEnhanced = "enhanced";
	const skinDefault = "default";
	const skinPowerUser = "power_user";
	const skinEvolution = "evolution";
	public static function importByUsername(API $api, string $username) {
		$account = new static($api, $username);
		$account->reload();
		return $account;
	}
	public static function createUsername(string $str, int $maxlength = 8): string {
		$str = $newStr = strtolower($str);
		$continue = true;
		do {
			$newStr = preg_replace("!^\d+!", "", $newStr);
			$newStr = preg_replace("!(\W+|_)!", "", $newStr);
			$continue = $newStr != $str;
			$str = $newStr;
		} while($continue);
		$str = substr($str, 0, $maxlength-3).rand(100, 999);
		return $str;
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
	protected $parent;
	protected $databaseManager;
	
	/** @var int|null unix timestamp */
	protected $create_at;

	/**
	 * @var DomainManager holds object created by getDomainManager
	 */
	protected $domainManager;

	/**
	 * @var EmailManager holds object created by getEmailManager
	 */
	protected $emailManager;

	public function __construct(API $api, string $username, string $domain = "", string $email = "") {
		$this->api = $api;
		$this->socket = $api->getSocket();
		$this->username = $username;
		if ($domain) {
			$this->domain = $domain;
		}
		if ($email) {
			$this->email = $email;
		}
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
		if ($this->username == "admin" and !isset($result["domain"])) {
			$result["domain"] = "";
		}
		$oldUsername = $this->username;
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
		if (isset($result["creator"]) and $result["creator"] != "root" and $result["creator"] != "admin" and $result['creator'] != $oldUsername) {
			$this->parent = self::importByUsername($this->api, $result["creator"]);
		}
		if (isset($result['date_created'])) {
			$this->create_at = strtotime($result['date_created']);
		}
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
		$params["username"] = $this->username ? $this->username : self::createUsername($this->domain);
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
		if ($this->maxQuota != self::unlimited) {
			$params["quota"] = $this->maxQuota;
		} else {
			$params["uquota"] = "ON";
		}
		if ($this->maxBandwidth != self::unlimited) {
			$params["bandwidth"] = $this->maxBandwidth;
		} else {
			$params["ubandwidth"] = "ON";
		}
		if ($this->maxAddonDomains != self::unlimited) {
			$params["vdomains"] = $this->maxAddonDomains;
		} else {
			$params["uvdomains"] = "ON";
		}
		if ($this->maxSubDomains != self::unlimited) {
			$params["nsubdomains"] = $this->maxSubDomains;
		} else {
			$params["unsubdomains"] = "ON";
		}
		if ($this->maxEmails != self::unlimited) {
			$params["nemails"] = $this->maxEmails;
		} else {
			$params["unemails"] = "ON";
		}
		if ($this->maxMailingLists != self::unlimited) {
			$params["nemailml"] = $this->maxMailingLists;
		} else {
			$params["unemailml"] = "ON";
		}
		if ($this->maxEmailResponders != self::unlimited) {
			$params["nemailr"] = $this->maxEmailResponders;
		} else {
			$params["unemailr"] = "ON";
		}
		if ($this->maxEmailForwarders != self::unlimited) {
			$params["unemailf"] = $this->maxEmailForwarders;
		} else {
			$params["uunemailf"] = "ON";
		}
		if ($this->maxSqls != self::unlimited) {
			$params["mysql"] = $this->maxSqls;
		} else {
			$params["umysql"] = "ON";
		}
		if ($this->maxParkDomains != self::unlimited) {
			$params["domainptr"] = $this->maxParkDomains;
		} else {
			$params["udomainptr"] = "ON";
		}
		if ($this->maxFtps != self::unlimited) {
			$params["ftp"] = $this->maxFtps;
		} else {
			$params["uftp"] = "ON";
		}
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
		if (isset($result["error"]) and $result["error"]) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$startAt = date::time();
		$found = false;
		$countTickets = count($this->getTickets());
		while(($timeout == 0 or date::time() - $startAt < $timeout) and !$found) {
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
		while(($timeout == 0 or date::time() - $startAt < $timeout) and !$found) {
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
		$log = log::getInstance();
		$log->info("modifing user", $this->username);
		if (empty($modifiedData)) {
			$log->reply()->fatal("modify data is empty");
			throw new Exception("modify data is empty");
		}
		$log->reply("modify data is", $modifiedData);
		if (isset($modifiedData["password"])) {
			$this->changePassword($modifiedData["password"]);
			unset($modifiedData["password"]);
		}
		if (empty($modifiedData)) {
			return;
		}
		$params = array(
			"action" => "customize",
			"user" => $this->username
		);
		if (isset($modifiedData["maxQuota"])) {
			if ($modifiedData["maxQuota"] == self::unlimited) {
				$params["uquota"] = "ON";
			} else {
				$params["quota"] = $modifiedData["maxQuota"];
			}
		} else if ($this->maxQuota != self::unlimited) {
			$params["quota"] = $this->maxQuota;
		} else {
			$params["uquota"] = "ON";
		}
		if (isset($modifiedData["maxBandwidth"])) {
			if ($modifiedData["maxBandwidth"] == self::unlimited) {
				$params["ubandwidth"] = "ON";
			} else {
				$params["bandwidth"] = $modifiedData["maxBandwidth"];
			}
		} else if ($this->maxBandwidth != self::unlimited) {
			$params["bandwidth"] = $this->maxBandwidth;
		} else {
			$params["ubandwidth"] = "ON";
		}
		if (isset($modifiedData["maxAddonDomains"])) {
			if ($modifiedData["maxAddonDomains"] == self::unlimited) {
				$params["uvdomains"] = "ON";
			} else {
				$params["vdomains"] = $modifiedData["maxAddonDomains"];
			}
		} else if ($this->maxAddonDomains != self::unlimited) {
			$params["vdomains"] = $this->maxAddonDomains;
		} else {
			$params["uvdomains"] = "ON";
		}
		if (isset($modifiedData["maxParkDomains"])) {
			if ($modifiedData["maxParkDomains"] == self::unlimited) {
				$params["udomainptr"] = "ON";
			} else {
				$params["domainptr"] = $modifiedData["maxParkDomains"];
			}
		} else if ($this->maxParkDomains != self::unlimited) {
			$params["domainptr"] = $this->maxParkDomains;
		} else {
			$params["udomainptr"] = "ON";
		}
		if (isset($modifiedData["maxSubDomains"])) {
			if ($modifiedData["maxSubDomains"] == self::unlimited) {
				$params["unsubdomains"] = "ON";
			} else {
				$params["nsubdomains"] =  $modifiedData["maxSubDomains"];
			}
		} else if ($this->maxSubDomains != self::unlimited) {
			$params["nsubdomains"] = $this->maxSubDomains;
		} else {
			$params["unsubdomains"] = "ON";
		}
		if (isset($modifiedData["maxEmails"])) {
			if ($modifiedData["maxEmails"] == self::unlimited) {
				$params["unemails"] = "ON";
			} else {
				$params["nemails"] = $modifiedData["maxEmails"];
			}
		} else if ($this->maxEmails != self::unlimited) {
			$params["nemails"] = $this->maxEmails;
		} else {
			$params["unemails"] = "ON";
		}
		if (isset($modifiedData["maxEmailForwarders"])) {
			if ($modifiedData["maxEmailForwarders"] == self::unlimited) {
				$params["uunemailf"] = "ON";
			} else {
				$params["nemailf"] = $modifiedData["maxEmailForwarders"];
			}
		} else if ($this->maxEmailForwarders != self::unlimited) {
			$params["unemailf"] = $this->maxEmailForwarders;
		} else {
			$params["uunemailf"] = "ON";
		}
		if (isset($modifiedData["maxEmailResponders"])) {
			if ($modifiedData["maxEmailResponders"] == self::unlimited) {
				$params["unemailr"] = "ON";
			} else {
				$params["nemailr"] = $modifiedData["maxEmailResponders"];
			}
		} else if ($this->maxEmailResponders != self::unlimited) {
			$params["nemailr"] = $this->maxEmailResponders;
		} else {
			$params["unemailr"] = "ON";
		}
		if (isset($modifiedData["maxMailingLists"])) {
			if ($modifiedData["maxMailingLists"] == self::unlimited) {
				$params["unemailml"] = "ON";
			} else {
				$params["nemailml"] = $modifiedData["maxMailingLists"];
			}
		} else if ($this->maxMailingLists != self::unlimited) {
			$params["nemailml"] = $this->maxMailingLists;
		} else {
			$params["unemailml"] = "ON";
		}
		if (isset($modifiedData["maxSqls"])) {
			if ($modifiedData["maxSqls"] == self::unlimited) {
				$params["umysql"] = "ON";
			} else {
				$params["mysql"] = $modifiedData["maxSqls"];
			}
		} else if ($this->maxSqls != self::unlimited) {
			$params["mysql"] = $this->maxSqls;
		} else {
			$params["umysql"] = "ON";
		}
		if (isset($modifiedData["maxFtps"])) {
			if ($modifiedData["maxFtps"] == self::unlimited) {
				$params["uftp"] = "ON";
			} else {
				$params["ftp"] = $modifiedData["maxFtps"];
			}
		} else if ($this->maxFtps != self::unlimited) {
			$params["ftp"] = $this->maxFtps;
		} else {
			$params["uftp"] = "ON";
		}
		if (isset($modifiedData["shell"])) {
			$params["ssh"] = $modifiedData["shell"] ? "ON" : "OFF";
		} else {
			$params["ssh"] = $this->shell ? "ON" : "OFF";
		}
		if (isset($modifiedData["anonymousFtp"])) {
			$params["aftp"] = $modifiedData["anonymousFtp"] ? "ON" : "OFF";
		} else {
			$params["aftp"] = $this->anonymousFtp ? "ON" : "OFF";
		}
		if (isset($modifiedData["cgi"])) {
			$params["cgi"] = $modifiedData["cgi"] ? "ON" : "OFF";
		} else {
			$params["cgi"] = $this->cgi ? "ON" : "OFF";
		}
		if (isset($modifiedData["php"])) {
			$params["php"] = $modifiedData["php"] ? "ON" : "OFF";
		} else {
			$params["php"] = $this->php ? "ON" : "OFF";
		}
		if (isset($modifiedData["spam"])) {
			$params["spam"] = $modifiedData["spam"] ? "ON" : "OFF";
		} else {
			$params["spam"] = $this->spam ? "ON" : "OFF";
		}
		if (isset($modifiedData["cron"])) {
			$params["cron"] = $modifiedData["cron"] ? "ON" : "OFF";
		} else {
			$params["cron"] = $this->cron ? "ON" : "OFF";
		}
		if (isset($modifiedData["ssl"])) {
			$params["ssl"] = $modifiedData["ssl"] ? "ON" : "OFF";
		} else {
			$params["ssl"] = $this->ssl ? "ON" : "OFF";
		}
		if (isset($modifiedData["sysinfo"])) {
			$params["sysinfo"] = $modifiedData["sysinfo"] ? "ON" : "OFF";
		} else {
			$params["sysinfo"] = $this->sysinfo ? "ON" : "OFF";
		}
		if (isset($modifiedData["dnscontrol"])) {
			$params["dnscontrol"] = $modifiedData["dnscontrol"] ? "ON" : "OFF";
		} else {
			$params["dnscontrol"] = $this->dnscontrol ? "ON" : "OFF";
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
		if (!in_array($skin, [self::skinEnhanced, self::skinDefault, self::skinPowerUser, self::skinEvolution])) {
			throw new Exception("valid skins is: " . self::skinEnhanced . " , ". self::skinDefault . " , " . self::skinPowerUser . " and " . self::skinEvolution);
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
	public function getMaxEmailForwarders() {
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
		return $result;
	}
	public function getCustomHTTP(string $domain) {
		$params = array(
			"domain" => $domain,
		);
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_CUSTOM_HTTPD",$params);
		$rawBody = $this->socket->fetch_body();
		if (stripos($rawBody, "error") !== false) {
			$results = $this->socket->fetch_parsed_body();
			if(isset($results["error"]) and $results["error"] == 1){
				throw new FailedException($result);
			}
		}
		return $rawBody;
	}
	public function getFiles(): FileManager {
		return new FileManager($this);
	}
	public function getDatabases(): DatabaseManager {
		return new DatabaseManager($this);
	}
	public function getDNS(): DNSManager {
		return new DNSManager($this);
	}
	public function getDomain() {
		return $this->domain;
	}
	public function getUsername() {
		return $this->username;
	}
	public function getPassword() {
		return $this->password;
	}
	public function setPassword(string $password) {
		$this->password = $password;
	}
	public function setEmail(string $email) {
		$this->email = $email;
	}
	public function getEmail() {
		return $this->email;
	}
	public function getMaxQuota() {
		return $this->maxQuota;
	}
	public function setMaxQuota(int $maxQuota) {
		if ($maxQuota <= 0 and $maxQuota != self::unlimited) {
			throw new Exception("max Quota get positive number");
		}
		$this->maxQuota = $maxQuota;
	}
	public function setQuato(int $quota) {
		$this->quota = $quota;
	}
	public function getQuota() {
		return $this->quota;
	}
	public function getMaxBandwidth() {
		return $this->maxBandwidth;
	}
	public function setMaxBandwidth(int $maxBandwidth) {
		if ($maxBandwidth <= 0 and $maxBandwidth != self::unlimited) {
			throw new Exception;
		}
		$this->maxBandwidth = $maxBandwidth;
	}
	public function setBandwidth(int $bandwidth) {
		$this->bandwidth = $bandwidth;
	}
	public function getBandwidth() {
		return $this->bandwidth;
	}
	public function getMaxFtps() {
		return $this->maxFtps;
	}
	public function setMaxFtps(int $maxFtps) {
		if ($maxFtps < 0 and $maxFtps != self::unlimited) {
			throw new Exception("max ftp get positive number");
		}
		$this->maxFtps = $maxFtps;
	}
	public function getFtps() {
		return $this->ftps;
	}

	public function getMaxEmails() {
		return $this->maxEmails;
	}
	public function setMaxEmails(int $maxEmails) {
		if ($maxEmails < 0 and $maxEmails != self::unlimited) {
			throw new Exception("max emails get positive number");
		}
		$this->maxEmails = $maxEmails;
	}
	public function setEmails(int $emails) {
		$this->emails = $emails;
	}
	public function getEmails() {
		return $this->emails;
	}
	public function getMaxMailingLists() {
		return $this->maxMailingLists;
	}
	public function setMaxMailingLists(int $maxMailingLists) {
		if ($maxMailingLists < 0 and $maxMailingLists != self::unlimited) {
			throw new Exception("max mailing list get positive number");
		}
		$this->maxMailingLists = $maxMailingLists;
	}
	public function getMailingLists() {
		return $this->mailingLists;
	}
	
	public function getMaxSqls() {
		return $this->maxSqls;
	}
	public function setMaxSqls(int $maxSqls) {
		if ($maxSqls < 0 and $maxSqls != self::unlimited) {
			throw new Exception("max sql get positive number");
		}
		$this->maxSqls = $maxSqls;
	}
	public function getSqls() {
		return $this->sqls;
	}
	public function getMaxSubDomains() {
		return $this->maxSubDomains;
	}
	public function setMaxSubDomains(int $maxSubDomains) {
		if ($maxSubDomains < 0 and $maxSubDomains != self::unlimited) {
			throw new Exception("max subDomain get positive number");
		}
		$this->maxSubDomains = $maxSubDomains;
	}
	public function getSubDomains() {
		return $this->subDomains;
	}
	public function getMaxParkDomains() {
		return $this->maxParkDomains;
	}
	public function setMaxParkDomains(int $maxParkDomains) {
		if ($maxParkDomains < 0 and $maxParkDomains != self::unlimited) {
			throw new Exception("max parkdomain get positive number");
		}
		$this->maxParkDomains = $maxParkDomains;
	}
	public function getParkDomains() {
		return $this->parkDomains;
	}
	public function getMaxAddonDomains() {
		return $this->maxAddonDomains;
	}
	public function setMaxAddonDomains(int $maxAddonDomains) {
		if ($maxAddonDomains < 0 and $maxAddonDomains != self::unlimited) {
			throw new Exception("max addondomain get positive number");
		}
		$this->maxAddonDomains = $maxAddonDomains;
	}
	public function setAddonDomains(int $addonDomains) {
		$this->addonDomains = $addonDomains;
	}
	public function getAddonDomains() {
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
	public function setPackage(string $package) {
		$this->package = $package;
	}
	public function getPackage() {
		return $this->package;
	}
	public function setParent(Account $parent) {
		$this->parent = $parent;
	}
	public function getParent() {
		return $this->parent;
	}
	public function getDatabaseManager(): DatabaseManager {
		if (!$this->databaseManager) {
			$this->databaseManager = new DatabaseManager($this);
		}
		return $this->databaseManager;
	}
	public function getDomainManager(): DomainManager {
		if (!$this->domainManager) {
			$this->domainManager = new DomainManager($this);
		}
		return $this->domainManager;
	}
	public function getEmailManager(): EmailManager {
		if (!$this->emailManager) {
			$this->emailManager = new EmailManager($this);
		}
		return $this->emailManager;
	}
	/**
	 * @param string $domain
	 * @param string $certificates should contain public key and private key, and CA if there is any.
	 */
	public function setupSSL(string $domain, string $certificates) {
		$username = $this->api->getUsername();
		$impersonate = $username != $this->username;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($this->username, API::User, true);
		}

		$socket = $this->api->getSocket();
		$params = array(
			'action' => 'save',
			'background' => 'auto',
			'certificate' => $certificates,
			'domain' => $this->domain,
			'submit' => 'Save',
			'type' => 'paste',
		);
		$socket->set_method("POST");
		$socket->query("/CMD_API_SSL", $params);
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
	 * Get create date
	 * 
	 * @return int|null
	 */
	public function getCreateAt(): ?int {
		return $this->create_at;
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
		if ($tickets) {
			usort($tickets, function($a, $b) {
				return $b["time"] - $a["time"];
			});
		}
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
