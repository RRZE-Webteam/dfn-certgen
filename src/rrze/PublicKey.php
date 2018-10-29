<?php
namespace RRZE;

class PublicKey extends X509 {
	public $crt;
	public $filepath;
	public function readFile($filepath)
	{
		if (is_readable($filepath))
		{
			$this->crt = openssl_x509_read('file://' . $filepath);
			if ($this->crt)
			{
				$this->filepath = $filepath;
			}
			$this->data = $this->_parsePublicKey($this->crt);
			return true;
		}
		else
		{
			return false;
		}
	}

	private function _parsePublicKey($crt)
	{
		$result = [];
		$x509 = openssl_x509_parse($crt);
		if (!$x509)
		{
			return false;
		}
		else
		{
			$result['x509'] = $x509;
		}
		if (isset($x509['extensions']['subjectAltName']))
		{
			$result['san'] = $this->_parseSAN($x509['extensions']['subjectAltName']);
		}
		$result['validity'] = $this->_getValidity($x509);
		return $result;
	}

	private function _parseSAN($sanstring)
	{
		$elements = explode(', ', $sanstring);
		$elements = array_map(function($val) {return explode(':', $val); }, $elements);
		$san = array_filter(array_map(function($val) {if (strtolower($val[0]) === 'dns') return$val[1]; }, $elements));
		sort($san);
		if (!empty($san))
		{
			return $san;
		}
		return false;
	}

	private function _getValidity($x509)
	{
		$now = new \DateTime();

		$validity['from'] = new \DateTime();
		$validity['from']->setTimestamp($x509['validFrom_time_t']);

		$validity['to'] = new \DateTime();
		$validity['to']->setTimestamp($x509['validTo_time_t']);

		$validity['duration'] = $validity['to']->diff($validity['from']);
		$validity['remaining'] = $validity['to']->diff($now);

                return $validity;
	}
}
