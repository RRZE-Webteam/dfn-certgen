<?php
namespace RRZE;
use \RRZE\Log;

class CLI extends Singleton {

	public function yesno($message)
	{
		$this->einfo($message);
		$input = trim(fgets(STDIN));
		$result = (in_array($input, ['y', 'yes', 'j', 'ja']));
		Log::info('result: ' . $result === true ? 'yes' : 'no');
		return $result;
	}

	public function eplain($message, $context = [])
	{
		$this->_message($message, $context, 'debug');
	}

	public function edebug($message, $context = [])
	{
		$this->_message($message, $context, 'debug', CYAN);
	}

	public function einfo($message, $context = [])
	{
		$this->_message($message, $context, 'info', CYAN);
	}

	public function ewarn($message, $context = [])
	{
		$this->_message($message, $context, 'warning', YELLOW);
	}

	public function eerror($message, $context = [])
	{
		$this->_message($message, $context, 'error', LIGHT_RED);
	}

	public function esuccess($message, $context = [])
	{
		$this->_message($message, $context, 'info', GREEN);
	}

	private function _message($message, $context = [], $level, $color = NC)
	{
		echo $color . $this->_printformat($message) . NC . "\n";
		if (!empty($context)) echo $this->_printformat($context) . "\n";
		Log::$level($message, (array) $context);
	}

	private function _printformat($message)
	{
		return ((is_array($message) or is_object($message)) ? json_encode($message, JSON_PRETTY_PRINT) : $message);
	}
}
