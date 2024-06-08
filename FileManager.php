<?php
namespace packages\directadmin_api;
use packages\base\{IO, IO\File, Log, HTTP, HTTP\ClientException, HTTP\ServerException};

class FileManager {
	protected $api;
	protected $account;
	public function __construct(Account $account) {
		$this->account = $account;
		$this->api = $this->account->getAPI();
	}
	public function download(string $filePath, File $source) {
		$log = Log::getInstance();
		$log->info("insure the source directory is exists");
		$sourceDirectory = $source->getDirectory();
		if (!$sourceDirectory->exists()) {
			$sourceDirectory->make(true);
		}
		$username = $this->api->getUsername();
		$log->info("send http client request to {$username}@", $this->api->getHost(), " for download ", $filePath);
		$client = new HTTP\Client();
		try {
			$username = $this->api->getUsername();
			$accountUsername = $this->account->getUsername();
			if ($accountUsername != $username) {
				if (strrpos($username, "|") === false) {
					$username = $username . "|" . $accountUsername;
				}
			}
			$log->info("init client params");
			$params = array(
				"ssl_verify" => false,
				"cookies" => false,
				"save_as" => $source,
				"auth" => array(
					"username" => $username,
					"password" => $this->api->getPassword(),
				),
				"query" => array(
					"path" => $filePath
				),
			);
			$log->reply($params);
			$response = $client->get("{$this->api->getHost()}/CMD_API_FILE_MANAGER", $params);
		} catch (ClientException $e) {
			$FailedException = new FailedException();
			$FailedException->setRequest($e->getRequest());
			$FailedException->setResponse($e->getResponse());
			throw $FailedException;
		} catch (ServerException $e) {
			$FailedException = new FailedException();
			$FailedException->setRequest($e->getRequest());
			$FailedException->setResponse($e->getResponse());
			throw $FailedException;
		}
	}
	public function upload(string $filePath, File $source) {
		$log = Log::getInstance();
		if (!$source->exists()) {
			throw new Exception("file not exists");
		}
		$client = new HTTP\Client();
		try {
			$username = $this->api->getUsername();
			$accountUsername = $this->account->getUsername();
			if ($accountUsername != $username) {
				if (strrpos($username, "|") === false) {
					$username = $username . "|" . $accountUsername;
				}
			}
			$log->info("init client params");
			$multipart = $this->multipart(array(
				"MAX_FILE_SIZE" => 1048576000,
				"action" => "upload",
				"path" => $filePath,
				"file1" => $source,
			));
			$params = array(
				"ssl_verify" => false,
				"cookies" => false,
				"auth" => array(
					"username" => $username,
					"password" => $this->api->getPassword(),
				),
				"headers" => array(
					"Content-Type" => $multipart['content-type'],
				),
				"body" => $multipart['body']
			);
			$response = $client->post("{$this->api->getHost()}/CMD_API_FILE_MANAGER", $params);
			$body = $response->getBody();
			if (!$body) {
				$FailedException = new FailedException();
				$FailedException->setRequest($params);
				throw $FailedException;
			}
			$errorPos = strrpos($body, "error");
			if ($errorPos !== false and substr($body, $errorPos + 6, 1) == 1) {
				$FailedException = new FailedException();
				$FailedException->setRequest($params);
				$FailedException->setResponse($body);
				throw $FailedException;
			}
		} catch (ClientException $e) {
			$FailedException = new FailedException();
			$FailedException->setRequest($e->getRequest());
			$FailedException->setResponse($e->getResponse());
			throw $FailedException;
		} catch (ServerException $e) {
			$FailedException = new FailedException();
			$FailedException->setRequest($e->getRequest());
			$FailedException->setResponse($e->getResponse());
			throw $FailedException;
		}
	}
	protected function multipart(array $fields) {
		$params = [];
		$watcher = function($input, $prefix = "") use (&$params, &$watcher) {
			foreach($input as $key => $value) {
				if (is_array($value)) {
					$watcher($value, $prefix ? $prefix."[{$key}]" : $key);
				} else {
					$params[$prefix ? $prefix."[{$key}]" : $key] = $value;
				}
			}
		};
		$body = "";
		$key = str_repeat('-', 29);
		for ($x = 0;$x < 29;$x++) {
			$key .= rand(1, 9);
		}
		$watcher($fields);
		foreach($params as $name => $value) {
			$body .= $key . "\r\n";
			if ($value instanceof File) {
				$body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$value->basename}\"\r\n";
				$body .= "Content-Type: ".IO\mime_type($value->getPath())."\r\n\r\n";
				$body .= $value->read();
			} else {
				$body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n".$value;
			}
			$body .= "\r\n";
		}
		$body .= $key."--";
		return array(
			'content-type' => 'multipart/form-data; boundary='.substr($key, 2),
			'key' => $key,
			'body' => $body,
		);
	}
}