<?php
namespace packages\directadmin_api;

class Exception extends \Exception {}
class FailedException extends Exception {
	protected $request;
	protected $response;
	public function setRequest($request) {
		$this->request = $request;
	}
	public function getRequest() {
		return $this->request;
	}
	public function setResponse($response) {
		$this->response = $response;
	}
	public function getResponse() {
		return $this->response;
	}
}
class NotFoundAccountException extends FailedException {
	protected $username;
	public function __construct($username) {
		$this->username = $username;
	}
	public function getUsername() {
		return $this->username;
	}
}
class EmailAlreadyExistException extends FailedException {}
class EmailNotExistException extends FailedException {}
class ReachedLimitException extends FailedException {}