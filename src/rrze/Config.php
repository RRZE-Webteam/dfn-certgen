<?php
namespace RRZE;
class Config extends Singleton {
	public $config;
	public $userinfo;

	public function read($filepath)
	{
		if (is_readable($filepath))
		{
			require_once($filepath);
			$this->config = $config;
			$this->config['getopt'] = $this->_get_cli_options();
			$this->config['username'] = $this->_get_username();
			$this->config['userinfo'] = $this->userinfo;
			$this->config['tls']['req']['AddName'] = $this->_get_username();
		}
		else
		{
			echo "Config file " . $filepath . " not found!\n";
			return false;
		}
	}

	public function get($key)
	{
		return $this->_find_config($key);
	}

	private function _find_config($key, $separator = ':')
	{
		$array = $this->config;
		$parts = explode($separator, $key);
		foreach ($parts as $part) {
			try
			{
				$array = $array[$part];
			}
			catch(\Exception $e)
			{
				echo $e->getMessage();
				exit;
			}
		}
    		return $array;
	}

	private function _get_username()
	{       
		# Set default name
		$user = (!getenv('SUDO_USER') ? getenv('USER') : getenv('SUDO_USER'));
		$userinfo = posix_getpwnam($user);
		$this->userinfo = $userinfo;
		$username = preg_replace('/\s?\(\w+\)$/', '', $userinfo['gecos']);

		if (isset($this->options['n']) AND $this->options['n'] !== false)
		{       
			$username = $this->options['n'];
		}
		return $username;
	}

	private function _get_cli_options($cmd_name = CMD_NAME)
	{
		$result = getopt($this->get('cli:options:' . $cmd_name));
		if (!$result)
		{
			echo $this->get('language:usage:' . $cmd_name);
			exit;
		}
		return $result;
	}
}
