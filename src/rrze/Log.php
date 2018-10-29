<?php
namespace RRZE;

use Config;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \Monolog\Handler\RotatingFileHandler;
use \Monolog\Formatter\LineFormatter;
use \Monolog\Formatter\JsonFormatter;

class Log extends Singleton {
	static $config;
	static $logger;
	static $formatter;
	static $stream;

	private function _initialize($facility = CMD_NAME)
	{
		$facility = (!$facility ? CMD_NAME : $facility);
		self::$config = \RRZE\Config::getInstance();
		self::$logger = new Logger($facility);
		self::$formatter = new JsonFormatter();

		switch(self::$config->get('log:level'))
		{
			case 'DEBUG':
				$level = Logger::DEBUG;
				break;
			case 'INFO':
				$level = Logger::INFO;
				break;
			case 'NOTICE':
				$level = Logger::NOTICE;
				break;
			case 'WARNING':
				$level = Logger::WARNING;
				break;
			case 'ERROR':
				$level = Logger::ERROR;
				break;
			case 'CRITICAL':
				$level = Logger::CRITICAL;
				break;
			case 'ALERT':
				$level = Logger::ALERT;
				break;
			case 'EMERGENCY':
				$level = Logger::EMERGENCY;
				break;
		}
		self::$stream = new RotatingFileHandler(self::$config->get('dir:log') . self::$config->get('log:filename'), 0, $level, null);
		self::$stream->setFilenameFormat('{filename}-{date}', 'Y-m');
		self::$stream->setFormatter(self::$formatter);
		self::$logger->pushHandler(self::$stream);
	}

	public function debug($message, array $context = [], $facility = false)
	{
		self::_initialize($facility);
		self::$logger->debug($message, (array) $context);
	}

	public function info($message, array $context = [], $facility = false)
	{
		self::_initialize($facility);
		self::$logger->info($message, (array) $context);
	}

	public function warning($message, array $context = [], $facility = false)
	{
		self::_initialize($facility);
		self::$logger->warning($message, (array) $context);
	}

	public function error($message, array $context = [], $facility = false)
	{
		self::_initialize($facility);
		self::$logger->error($message, (array) $context);
	}
}
