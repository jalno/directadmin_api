<?php
namespace packages\directadmin_api;

use packages\base\{Date, log, utility, json, View\Error};

class Account {
	const unlimited = -1;
	const skinEnhanced = "enhanced";
	const skinDefault = "default";
	const skinPowerUser = "power_user";
	const skinEvolution = "evolution";

	/**
	 * @param API $api
	 * @param string $username
	 * @throws FailedException
	 * @throws NotFoundAccountException
	 */
	public static function importByUsername(API $api, string $username) {
		$account = new self($api, $username);
		$account->reload();
		return $account;
	}
	
	/**
	 * @param string $str
	 * @param int $maxlength default is 8
	 * @return string
	 */
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

	/** @var API */
	protected $api;

	/** @var HTTPSocket */
	protected $socket;
	/** @var string|null */
	protected $domain;
	/** @var string */
	protected $username;
	/** @var string|null */
	protected $password;
	/** @var string|null */
	protected $email;
	/** @var int|string|null */
	protected $quota;
	/** @var int */
	protected $maxQuota = self::unlimited;
	/** @var int|null */
	protected $bandwidth;
	/** @var int */
	protected $maxBandwidth = self::unlimited;
	/** @var int|null */
	protected $ftps;
	/** @var int */
	protected $maxFtps = self::unlimited;
	/** @var int|null */
	protected $emails;
	/** @var int */
	protected $maxEmails = self::unlimited;
	/** @var int|null */
	protected $mailingLists;
	/** @var int */
	protected $maxMailingLists = self::unlimited;
	/** @var int|null */
	protected $sqls;
	/** @var int */
	protected $maxSqls = self::unlimited;
	/** @var int|null */
	protected $subDomains;
	/** @var int */
	protected $maxSubDomains = self::unlimited;
	/** @var int|null */
	protected $parkDomains;
	/** @var int */
	protected $maxParkDomains = self::unlimited;
	/** @var int|null */
	protected $addonDomains;
	/** @var int */
	protected $maxAddonDomains = self::unlimited;
	/** @var bool */
	protected $php = true;
	/** @var bool */
	protected $reseller = false;
	/** @var int|null */
	protected $ip;
	/** @var bool */
	protected $anonymousFtp = false;
	/** @var bool */
	protected $cgi = false;
	/** @var bool */
	protected $spam = true;
	/** @var bool */
	protected $cron = true;
	/** @var bool */
	protected $ssh = false;
	/** @var bool */
	protected $ssl = true;
	/** @var bool */
	protected $sysinfo = false;
	/** @var bool */
	protected $shell = false;
	/** @var int */
	protected $skin = self::skinEnhanced;
	/** @var bool */
	protected $notify = true;
	/** @var bool */
	protected $dnscontrol = false;
	/** @var int|null */
	protected $maxEmailForwarders = self::unlimited;
	/** @var int|null */
	protected $emailForwarders;
	/** @var int|null */
	protected $maxEmailResponders = self::unlimited;
	/** @var int|null */
	protected $emailResponders;
	/** @var array<string> */
	protected $nameservers = [];
	/** @var Account|null */
	protected $parent;
	/** @var DatabaseManager|null */
	protected $databaseManager;
	/** @var DomainPointerManager|null */
	protected $domainPointerManager;

	/** @var string */
	protected $package;
	/** @var bool */
	protected $suspended = false;
	/** @var string|null */
	protected $creator;
	
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

	/**
	 * 
	 * @return void
	 * @throws FailedException
	 * @throws NotFoundAccountException
	 */
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
		if (!isset($result["domain"])) {
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
		$this->reseller = ($result["usertype"] == "reseller");
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
		/** @var array<string,string> */
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
		/** @var array<string,string> */
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
		/** @var array<string,string> */
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
		try {
			$this->delete();
		} catch (NotFoundAccountException $e) {}

		$tickets = $this->getTickets(array(
			"ipp" => 1,
		));
		$lastTicket = reset($tickets);

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
		$checkUserInTicket = function(string $message): bool {
			return stripos($message, "User {$this->username} has been restored") !== false || stripos($message, $this->username) !== false;
		};
		while(($timeout == 0 or date::time() - $startAt < $timeout) and !$found) {
			$tickets = $this->getTickets();
			foreach ($tickets as $ticket) {
				if (!$lastTicket or $ticket["last_message"] > $lastTicket["last_message"]) {
					$lastTicket = $ticket;
					$subject = strtolower(trim($ticket["subject"]));
					if (
						substr($subject, 0, 34) == "your user files have been restored" and
						strtolower($ticket["new"]) == "yes"
					) {
						$content = $this->getTicket($ticket["message"]);
						if ($content and $checkUserInTicket($content["message"])) {
							break 2;
						}
					} else if (
						substr($subject, 0, 36) == "an error occurred during the restore" and
						strtolower($ticket["new"]) == "yes"
					) {
						$content = $this->getTicket($ticket["message"]);
						if ($content) {
							if ($checkUserInTicket($content["message"])) {
								$e = new FailedException();
								$e->setRequest($params);
								$e->setResponse($result);
								throw $e;
							}
						}
					}
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
		$this->reload();
		return $this;
	}
	public function backup(int $timeout = 600) {
		$log = log::getInstance();
		$log->info("init backup params");
		/** @var array<string,string> */
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

		$log->info("get system last message");
		$tickets = $this->getTickets(array(
			"ipp" => 1,
		));
		$lastTicket = reset($tickets);
		if ($lastTicket) {
			$log->reply("sent in: ", Date\Gregorian::format("Y/m/d H-i-s", $lastTicket["last_message"]));
		} else {
			$log->reply("NotFound");
		}
		$found = false;
		$log->info("listening for add new ticket in ", $timeout, " Second");
		while (($timeout == 0 or date::time() - $startAt < $timeout) and !$found) {
			$log->info("get system tickets for checking new ticket");
			$tickets = $this->getTickets();
			foreach ($tickets as $ticket) {
				if (!$lastTicket or $ticket["last_message"] > $lastTicket["last_message"]) {
					$log->info("the new ticket found, check it for sure");
					$subject = strtolower($ticket["subject"]);
					if (
						substr($subject, 0, strlen("your backups are now ready")) == "your backups are now ready" and
						strtolower($ticket["new"]) == "yes"
					) {
						$content = $this->getTicket($ticket["message"]);
						if ($content) {
							if (stripos($content["message"], "User {$this->username} has been backed up") !== false) {
								$log->reply("found ticket that I was looking for");
								$found = true;
								break;
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
						$content = $this->getTicket($ticket["message"]);
						if ($content) {
							if (stripos($content["message"], "User {$this->username} has been backed up") !== false or stripos($content["message"], "{$this->username}.tar.gz") !== false) {
								$log->reply()->fatal("Sorry.., the create backup process has faield");
								$e = new FailedException();
								$e->setRequest($params);
								$e->setResponse($result);
								throw $e;
							} else {
								$log->reply("Be Happy, It's not our service");
							}
						} else {
							$log->reply()->warn("Unable to get message content");
						}
					}
				}
			}
			sleep(2);
		}
		$log->info("looking in backup files for found them");
		$month = Date\Gregorian::format("M");
		$year = Date\Gregorian::format("Y");
		$day = Date\Gregorian::format("j");
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
			$exception = new Error();
			$exception->setData(array(
				"current" => $currentBackups,
				"regex" => $backupRegexName,
			));
			throw $exception;
		}
		$backup = end($backups);
		$path = "/backups/{$backup}";
		$log->info("backup path:", $path);
		return $path;
	}
	public function changePassword(string $password) {
		/** @var array<string,string> */
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

		/** @var array<string,string> */
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
		$this->socket->query(($this->reseller ? "/CMD_API_MODIFY_RESELLER" : "/CMD_API_MODIFY_USER"), $params);
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
		/** @var array<string,string> */
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
		/** @var array<string,string> */
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
		/** @var array<string,string> */
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

	public function setCustomHTTPWithCustoms(?string $config = null, ?array $customs = null)
	{
		/** @var array<string,string> */
		$params = array(
			"domain" => $this->getDomain(),
			"config" => $config ?: '',
		);

		if ($customs) {
			$params = array_merge($params, $customs);
		}

		$this->socket->set_method("POST");
		$this->socket->query("/CMD_API_CUSTOM_HTTPD", $params);

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
		/** @var array<string,string> */
		$params = array(
			"domain" => $domain,
		);
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_API_CUSTOM_HTTPD",$params);
		$rawBody = $this->socket->fetch_body();
		if (stripos($rawBody, "error") !== false) {
			$result = $this->socket->fetch_parsed_body();
			if(isset($result["error"]) and $result["error"] == 1){
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
	public function getDomainPointerManager(): DomainPointerManager {
		if (!$this->domainPointerManager) {
			$this->domainPointerManager = new DomainPointerManager($this);
		}
		return $this->domainPointerManager;
	}
	/**
	 * @param string $domain
	 * @param string $certificate should contain public key and private key.
	 * @param string|null $ca CA certificate, if there is any.
	 */
	public function setupSSL(string $domain, string $certificate, ?string $ca = null) {
		$username = $this->api->getUsername();
		$impersonate = $username != $this->username;
		if ($impersonate) {
			$level = $this->api->getLevel();
			$this->api->setUsername($this->username, API::User, true);
		}

		$socket = $this->api->getSocket();
		try {
			/** @var array<string,string> */
			$params = array(
				'action' => 'save',
				'background' => 'auto',
				'certificate' => $certificate,
				'domain' => $this->domain,
				'submit' => 'Save',
				'type' => 'paste',
			);
			$socket->set_method("POST");
			$socket->query("/CMD_API_SSL", $params);
			$result = $socket->fetch_parsed_body();

			if ((isset($result["error"]) and $result["error"])) {
				$FailedException = new FailedException();
				$FailedException->setRequest($params);
				$FailedException->setResponse($result);
				throw $FailedException;
			}
			/** @var array<string,string> */
			$params = array(
				'domain' => $this->domain,
				'action' => 'save',
				'type' => 'cacert',
				'cacert' => $ca ?? "",
			);
			if ($ca !== null) {
				$params['active'] = 'yes';
			} 
			$socket->set_method("POST");
			$socket->query("/CMD_API_SSL", $params);
			$result = $socket->fetch_parsed_body();

			if ((isset($result["error"]) and $result["error"])) {
				$FailedException = new FailedException();
				$FailedException->setRequest($params);
				$FailedException->setResponse($result);
				throw $FailedException;
			}
		} finally {
			if ($impersonate) {
				$this->api->setUsername($username, $level, false);
			}
		}
		
	}
	/**
	 * Get create date
	 * 
	 * @return int|string|null
	 */
	public function getCreateAt(): ?int {
		return $this->create_at;
	}
	
	/**
	 * Get Ticket Message data
	 * @var string $id
	 * 
	 * @return array|false|null
	 */
	public function getTicket(string $id) {
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_TICKET", array(
			"json" => "yes",
			"number" => $id,
			"action" => "view",
			"type" => "message",
		));
		$result = json\decode($this->socket->fetch_body());
		if (isset($result["error"]) and $result["error"]) {
			$FailedException = new FailedException();
			$FailedException->setResponse($result);
			throw $FailedException;
		}
		return $result[0] ?? [];
	}
	/**
	 * Get Ticket message list
	 * @var array $params
	 * You can pass query options as key-value array
	 * 
	 * @return array
	 */
	public function getTickets(?array $params = array()): array {
		/** @var array<string,string> */
		$params = array_replace(array(
			"json" => "yes",
			"ipp" => 50,
			"type" => "message",
			"key" => "last_message",
			"order" => "DESC",
			"sort1" => -3,
		), $params);
		$this->socket->set_method("GET");
		$this->socket->query("/CMD_TICKET", $params);
		try {
			$result = json\decode($this->socket->fetch_body());
		} catch (json\JsonException $e) {
			return [];
		}
		if ((isset($result["error"]) and $result["error"]) or !isset($result["messages"])) {
			$FailedException = new FailedException();
			$FailedException->setResponse($result);
			throw $FailedException;
		}
		unset($result["messages"]["info"]);
		return $result["messages"];
	}

	/**
	 * @param array<string,string> $params
	 */
	public function issueSSL(string $domain, array $params = []): void
	{
		$username = $this->api->getUsername();
		$level = $this->api->getLevel();
		$impersonate = $username != $this->username;
		if ($impersonate) {
			$this->api->setUsername($this->username, API::User, true);
		}

		$socket = $this->api->getSocket();

		try {
			/** @var array<string,string> */
			$params = array_merge([
				'domain' => $domain,
				'action' => 'save',
				'background' => 'auto',
				'type' => 'create',
				'name' => $domain,
				'submit' => 'Save',
			], $params);

			$socket->set_method("POST");
			$socket->query("/CMD_API_SSL", $params);
			$result = $socket->fetch_parsed_body();

			if ((isset($result["error"]) and $result["error"])) {
				$FailedException = new FailedException();
				$FailedException->setRequest($params);
				$FailedException->setResponse($result);
				throw $FailedException;
			}
		} finally {
			if ($impersonate) {
				$this->api->setUsername($username, $level, false);
			}
		}
	}

	public function issueLetsencryptSSL(string $domain, array $domains = []): void
	{
		foreach (['www.'.$domain, $domain] as $item) {
			if (!in_array($item, $domains)) {
				array_unshift($domains, $item);
			}
		}

		$domains = array_unique($domains);

		$selects = [];
		$i = 0;
		foreach ($domains as $item) {
			$selects['le_select'.($i++)] = $item;
		}

		$this->issueSSL($domain, array_merge([
			'request' => 'letsencrypt',
			'keysize' => 'secp384r1',
			'encryption' => 'sha256',
			'le_wc_select0' => $domain,
			'le_wc_select1' => '*.'.$domain,
		], $selects));
	}

	protected function getCurrentBackups(): array {
		$this->reload();
		/** @var array<string,string> */
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
