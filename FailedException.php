<?php
namespace packages\directadmin_api;

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