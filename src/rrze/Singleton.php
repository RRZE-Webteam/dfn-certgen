<?php
namespace RRZE;

interface ISingleton {
	public static function getInstance(): ISingleton;
}

abstract class Singleton implements ISingleton {
	private static $_instances = [];
	final private function __construct () {}
	final private function __clone() {}
	final private function __wakeup() {}

	final public static function getInstance(): ISingleton {
		self::$_instances[static::class] = self::$_instances[static::class] ?? new static();
		return self::$_instances[static::class];
	}
}
