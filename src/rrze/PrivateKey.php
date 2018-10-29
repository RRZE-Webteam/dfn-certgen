<?php
namespace RRZE;
use \RRZE\Config;
use \RRZE\Log;

class PrivateKey extends X509 {
	protected $pkey;
	public $filepath;
	public $csr;

	public function readFile($filepath)
	{
		if (is_readable($filepath))
		{
			$this->pkey = openssl_pkey_get_private('file://' . $filepath);
			if ($this->pkey)
			{
				$this->filepath = $filepath;
			}
			return true;
		}
		else
		{
			return false;
		}
	}

	public function matchPublicKey($cert)
	{
		return openssl_x509_check_private_key($cert, $this->pkey);
	}

	public function generate($filepath)
	{
		if (is_writable(dirname($filepath)))
		{
			$newpair = openssl_pkey_new($this->config->get('tls:pkey'));
			$this->pkey = openssl_pkey_get_private($newpair);
			return (openssl_pkey_export_to_file($this->pkey, $filepath) && chmod($filepath, 0600));
		}
		return false;
	}

	public function generateCSR($opensslcnf, $servername)
	{
		try
		{
			$dn = $this->config->get('tls:dn');
			$configargs = $this->config->get('tls:csr:configargs');
			$configargs['config'] = $opensslcnf;

			$this->csr = new \StdClass;
			$this->csr->newcsr = openssl_csr_new($dn, $this->pkey, $configargs);
			openssl_csr_export($this->csr->newcsr, $this->csr->content);
			$this->csr->written = openssl_csr_export_to_file($this->csr->newcsr, $this->config->get('dir:csr') . $servername . '.csr');
		} catch (\Exception $e)
		{
			Log::error($e->getMessage());
			exit;
		}
	}
}
