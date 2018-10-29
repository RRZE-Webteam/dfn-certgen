<?php
namespace RRZE;

class X509 {
	protected $config;
	public $data;

	public function __construct() {
		$this->config = \RRZE\Config::getInstance();
	}
}
