<?php
namespace packages\directadmin_api;

use packages\base\utility\Password;

class EmailManager {
	
	const UTF = "UTF-8";
	const ISO = "iso-8859-1";
	const PLAIN = "text/plain";
	const HTML = "text/html";
	protected $api;
	protected $account;

	public function __construct(Account $account) {
		$this->account = $account;
		$this->api = $this->account->getAPI();
	}

	/**
	 *
	 * export an array like this:
	 * Array
	 * (
	 * 	[domain] => domain.com
	 * 	[list] => array()
	 * )
	 * each index of list array should something like this:
	 * Array
	 *	(
	 *		[account] => info
	 *		[login] => info@domain.com
	 *		[usage] => Array
	 *			(
	 *				[apparent_usage] => 0.0857
	 *				[imap_bytes] => 147456
	 *				[quota] => 50 or unlimited
	 *				[usage] => 0.1406
	 *				[webmail_bytes] => 0.000000
	 *			)
	 *		[sent] => Array
	 *			(
	 *				[send_limit] => 200
	 *				[sent] => 0
	 *			)
	 *		[suspended] => no
	 *	)
	 */
	public function getEmails(string $inputDomain = "") {
		$socket = $this->api->getSocket();
		$socket->set_method("GET");
		$domain = ($inputDomain) ? $inputDomain : $this->account->getDomain();
		$params = array(
			"json" => "yes",
			"domain" => $domain,
		);
		$socket->query("/CMD_EMAIL_POP", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$list = [];
		foreach ($result["emails"] as $key => $item) {
			if (is_numeric($key) and $key != 0) {
				$list[] = $item;
			}
		}
		return array(
			"domain" => $domain,
			"list" => $list,
		);
	}

	public function createEmail(array $data): array {
		if (!isset($data["username"]) or !$data["username"]) {
			throw new Exception("'username' is required to create new email account");
		}
		if (!isset($data["password"])) {
			$data["password"] = Password::generate();
		}
		foreach (array("quota", "limit") as $item) {
			if (isset($data[$item]) and !is_numeric($data[$item])) {
				throw new Exception($item . " must pass as int (zero is unlimited)");
			}
		}
		$data["quota"] = (isset($data["quota"])) ? $data["quota"] : 50;
		$data["limit"] = (isset($data["limit"]) and $data["limit"] > 0) ? $data["limit"] : 200;
		$domain = (isset($data["domain"]) and $data["domain"]) ? $data["domain"] : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "create",
			"domain" => $domain,
			"user" => $data["username"],
			"passwd" => $data["password"],
			"passwd2" => $data["password"],
			"quota" => $data["quota"],
			"limit" => $data["limit"],
		);
		$socket->query("/CMD_API_POP", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$userExist = stripos($result["details"], "That user already exists");
			if ($userExist !== false) {
				throw new EmailAlreadyExistException();
			} else {
				$exception = new FailedException();
				$exception->setRequest($params);
				$exception->setResponse($result);
				throw $exception;
			}
		}
		$data["username"] .= "@" . $domain;
		return $data;
	}

	public function modifyEmail(array $data): array {
		if (!isset($data["username"]) or !$data["username"]) {
			throw new Exception("give 'username' index to modify email account");
		}
		$domain = (isset($data["domain"]) and $data["domain"]) ? $data["domain"] : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "modify",
			"domain" => $domain,
			"user" => $data["username"],
		);
		if (isset($data["password"]) and $data["password"]) {
			$params["passwd"] = $data["password"];
			$params["passwd2"] = $data["password"];
		}
		foreach (array("quota", "limit") as $item) {
			if (isset($data[$item])) {
				if (!is_numeric($data[$item]) or $data[$item] < 0) {
					throw new Exception($item . " must pass as int (Zero is unlimited)");
				}
				$params[$item] = $data[$item];
			}
		}
		$socket->query("/CMD_API_POP", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$data["username"] .= "@" . $domain;
		return $data;
	}

	public function deleteEmail(array $data): ?bool {
		if (!isset($data["username"]) or !$data["username"]) {
			throw new Exception("give 'username' index to delete email account");
		}
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "delete",
			"domain" => (isset($data["domain"]) and $data["domain"]) ? $data["domain"] : $this->account->getDomain(),
			"user" => $data["username"],
		);
		$socket->query("/CMD_API_POP", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$userExist = stripos($result["details"], "does not exist");
			if ($userExist !== false) {
				throw new EmailNotExistException();
			} else {
				$exception = new FailedException();
				$exception->setRequest($params);
				$exception->setResponse($result);
				throw $exception;
			}
		}
		return true;
	}
	/**
	 * export an array like this:
	 * Array
	 * (
	 * 	[domain] => domain.com
	 * 	[list] => array()
	 * )
	 * the [list] array is something like this
	 * Array
	 *	(
	 *		[info] => folan@jeyserver.com
	 *	)

	 */
	public function getEmailForwarders(string $inputDomain = ""): array {
		$domain = $inputDomain ? $inputDomain : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "list",
			"domain" => $domain,
		);
		$socket->query("/CMD_API_EMAIL_FORWARDERS", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		return array(
			"domain" => $domain,
			"list" => $result
		);
	}

	public function createEmailForwarder(array $data): array {
		$domain = (isset($data["domain"]) and $data["domain"]) ? $data["domain"] : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "create",
			"domain" => $domain,
			"user" => $data["username"],
			"email" => $data["email"], //forward
		);
		$socket->query("/CMD_API_EMAIL_FORWARDERS", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$userExist = stripos($result["details"], "already exists");
			if ($userExist !== false) {
				throw new EmailAlreadyExistException();
			} else {
				$exception = new FailedException();
				$exception->setRequest($params);
				$exception->setResponse($result);
				throw $exception;
			}
		}
		$data["user"] .= "@" . $domain;
		return $data;
	}

	public function modifyEmailForwarder(array $data) {
		$domain = (isset($data["domain"]) and $data["domain"]) ? $data["domain"] : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "modfiy",
			"json" => "yes",
			"domain" => $domain,
			"user" => $data["username"],
			"email" => $data["email"], // forward
		);
		$socket->query("/CMD_API_EMAIL_FORWARDER", $params);
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		return true;
	}

	public function deleteEmailForwarder(array $data) {
		$domain = (isset($data["domain"]) and $data["domain"]) ? $data["domain"] : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "delete",
			"json" => "yes",
			"domain" => $domain,
			"select1" => $data["username"],
		);
		$socket->query("/CMD_API_EMAIL_FORWARDERS", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$userExist = stripos($result["details"], "does not exist");
			if ($userExist !== false) {
				throw new EmailNotExistException();
			} else {
				$exception = new FailedException();
				$exception->setRequest($params);
				$exception->setResponse($result);
				throw $exception;
			}
		}
		return true;
	}

	public function getAutoResponders(string $inputDomain = "") {
		$domain = $inputDomain ? $inputDomain : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "list",
			"domain" => $domain,
		);
		$socket->query("/CMD_API_EMAIL_AUTORESPONDER", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		$list = [];
		foreach ($result as $key => $item) {
			$list[$key] = array(
				"cc" => $item,
			);
		}
		return array(
			"domain" => $domain,
			"list" => $list,
		);
	}

	public function createAutoResponder(array $data) {
		if (!isset($data["username"]) or !$data["username"]) {
			throw new Exception("give 'username' index to modify email account");
		}
		if (!isset($data["subject"]) or !$data["subject"]) {
			$data["subject"] = "Autoreply";
		}
		if (isset($data["encoding"]) and $data["encoding"]) {
			if (in_array($data["encoding"], array(self::UTF, self::ISO))) {
				throw new Exception("'encoding' index should be EmailManager::UTF or EmailManager::ISO");
			}
		} else {
			$data["encoding"] = self::UTF;
		}
		if (isset($data["content_type"]) and $data["content_type"]) {
			if (!in_array($data["content_type"], array(self::HTML, self::PLAIN))) {
				throw new Exception("'content_type' index should be EmailManager::HTML or EmailManager::PLAIN");
			}
		} else {
			$data["content_type"] = self::PLAIN;
		}
		if (!isset($data["reply_time"]) or $data["reply_time"]) {
			$data["reply_time"] = "1h";
		}
		$domain = (isset($data["domain"]) and $data["domain"]) ? $data["domain"] : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "create",
			"domain" => $domain,
			"user" => $data["username"],
			"subject" => $data["subject"],
			"reply_encoding" => $data["encoding"],
			"reply_content_type" => $data["content_type"],
			"reply_once_time" => $data["reply_time"],
			"text" => $data["text"],
			"email" => "",
			"create" => "Create",
		);
		if (isset($data["cc"]) and $data["cc"]) {
			$params["cc"] = "ON";
			$params["email"] = $data["cc"];
		}
		$socket->query("/CMD_API_EMAIL_AUTORESPONDER", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$userExist = stripos($result["details"], "An autoresponder with that name already exists");
			if ($userExist !== false) {
				throw new EmailAlreadyExistException();
			} else {
				$exception = new FailedException();
				$exception->setRequest($params);
				$exception->setResponse($result);
				throw $exception;
			}
		}
		$data["username"] .= "@" . $domain;
		return $data;
	}

	public function modifyAutoResponder(array $data) {
		if (!isset($data["username"]) or !$data["username"]) {
			throw new Exception("give 'username' index to modify email account");
		}
		$domain = (isset($data["domain"]) and $data["domain"]) ? $data["domain"] : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("GET");
		$socket->query("/CMD_API_EMAIL_AUTORESPONDER_MODIFY", array(
			"json" => "yes",
			"domain" => $domain,
			"user" => $data["username"],
		));
		$result = $socket->fetch_parsed_body();
		$params = array(
			"action" => "modify",
			"json" => "yes",
			"domain" => $domain,
			"user" => $data["username"],
		);
		$params["email"] = $result["email"];
		$params["text"] = $result["text"];
		foreach($result["headers"] as $key => $item) {
			if (is_array($item)) {
				foreach ($item as $param) {
					if (isset($param["selected"])) {
						if ($key == "reply_once_select") {
							$params["reply_once_time"] = $param["value"];
						} elseif ($key == "reply_content_types") {
							$params["reply_content_type"] = $param["value"];
						} elseif ($key == "reply_encodings") {
							$params["reply_encoding"] = $param["value"];
						} else {
							$params[$key] = $param["value"];
						}
					}
				}
			} elseif ($key == "subject_prefix") {
				$params["subject"] = $item;
			}
		}
		if (isset($data["encoding"]) and $data["encoding"]) {
			if (!in_array($data["encoding"], array(self::UTF, self::ISO))) {
				throw new Exception("'encoding' index should be EmailManager::UTF or EmailManager::ISO");
			}
			$params["reply_encoding"] = $data["encoding"];
		}
		if (isset($data["content_type"]) and $data["content_type"]) {
			if (!in_array($data["content_type"], array(self::HTML, self::PLAIN))) {
				throw new Exception("'content_type' index should be EmailManager::HTML or EmailManager::PLAIN");
			}
			$params["reply_content_type"] = $data["content_type"];
		}
		if (isset($data["reply_time"]) and $data["reply_time"]) {
			$params["reply_once_time"] = $data["reply_time"];
		}
		if (isset($data["text"])) {
			$params["text"] = $data["text"];
		}
		if (isset($data["subject"]) and $data["subject"]) {
			$params["subject"] = $data["subject"];
		}
		if (isset($data["cc"]) and $data["cc"]) {
			$params["cc"] = "ON";
			$params["email"] = $data["cc"];
		}
		$socket->set_method("POST");
		$socket->query("/CMD_API_EMAIL_AUTORESPONDER", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		if (isset($params["email"])) {
			$params["cc"] = $params["email"];
		}
		unset($params["action"], $params["json"], $params["email"]);
		return $params;
	}

	public function deleteAutoResponder(array $data) {
		$domain = (isset($data["domain"]) and $data["domain"]) ? $data["domain"] : $this->account->getDomain();
		$socket = $this->api->getSocket();
		$socket->set_method("POST");
		$params = array(
			"action" => "delete",
			"domain" => $domain,
			"select1" => $data["username"],
		);
		$socket->query("/CMD_API_EMAIL_AUTORESPONDER", $params);
		$result = $socket->fetch_parsed_body();
		if (isset($result["error"]) and $result["error"] == 1) {
			$exception = new FailedException();
			$exception->setRequest($params);
			$exception->setResponse($result);
			throw $exception;
		}
		return true;
	}

} 