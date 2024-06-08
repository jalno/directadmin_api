<?php
namespace packages\directadmin_api;

class NotFoundAccountException extends FailedException {
	protected $username;
	public function __construct($username) {
		$this->username = $username;
	}
	public function getUsername() {
		return $this->username;
	}
}