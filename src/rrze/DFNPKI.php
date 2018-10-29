<?php
namespace RRZE;
use CLI;
use PublicKey;
use PrivateKey;

class DFNPKI extends X509 {

	public $soapClient;
	public $cli;

	private function _initialize()
	{
		$this->cli = \RRZE\CLI::getInstance();
		try
		{
			$this->_soap_client();
		}
		catch (Exception $e)
		{
			$this->cli->eerror($e->getMessage());
			exit;
               	}
	}

	private function _soap_client()
	{
		$this->soapClient = new \SoapClient(
			$this->config->get('tls:soap:url'),
			[
				"trace" => 1,
				"exceptions" => 1
			]
                );
	}

	public function newRequest($data)
	{
		$this->_initialize();

		$result = $this->soapClient->newRequest(
			$data['RaID'],
			$data['PKCS10'],
			$data['AltNames'],
			$data['Role'],
			$data['Pin'],
			$data['AddName'],
			$data['AddEMail'],
			$data['AddOrgUnit'],
			$data['Publish']
		);
		if (is_int($result))
		{
			$data['serial'] = $result;
			$this->_saveRequestData($result, $data);
		}
		return $result;
	}

	public function fetchPDF($serial, $servername)
	{
		$this->_initialize();
		if ($serial and $servername)
		{
			$pdf = $this->soapClient->getRequestPrintout(
				$this->config->get('tls:req:RaID'),
				$serial,
				'application/pdf',
				$this->config->get('tls:req:Pin')
			);
                        $pdfpath = $this->config->get('dir:pdf') . $serial . '-' . $servername . '.pdf';

			if (!file_exists($pdfpath))
			{
				$save_result = file_put_contents($pdfpath, $pdf);
				if ($save_result !== false)
				{
					return $pdfpath;
				}
				else
				{
					return false;
				}
			}
			else
			{
				$this->cli->eerror("PDF " . $pdfpath . " already exists.");
				return false;
			}
		}
		else
		{
			$this->cli->eerror("Either Serial or Servername missing.");
			return false;
		}
	}

	public function openRequests($serial = false)
	{
		$result = [];
		$pattern = '/^(.+)\.json$/';
		if (is_int($serial))
		{
			$pattern = '/^(' . $serial . ')\.json$/';
		}

		foreach (new \DirectoryIterator($this->config->get('dir:todo:dfn')) as $fileInfo) 
		{
			if($fileInfo->isFile())
			{
				$matches = [];
				$filename = $fileInfo->getFilename();
				if (preg_match($pattern, $filename, $matches))
				{
					$result[$matches[1]] = json_decode(file_get_contents($this->config->get('dir:todo:dfn') . $filename));
				};
			}
		}
		return $result;
	}

	public function purgeRequest($serial)
	{
		$filepath = $this->config->get('dir:todo:dfn') . $serial . '.json';
		if (file_exists($filepath))
		{
			$result = unlink($filepath);
			if ($result)
			{
				$this->cli->esuccess('Deleted ' . $filepath);
				return true;
			}
			else
			{
				$this->cli->eerror('Could not delete ' . $filepath);
				return false;
			}
		}
		else
		{
			$this->cli->eerror('Could not find ' . $filepath);
			return false;
		}
	}

	public function fetchCRT($serial)
	{
		$this->_initialize();
		$data = $this->_loadRequestData($serial);
		if ($data)
		{
			$crt = $this->soapClient->getCertificateByRequestSerial(
				$this->config->get('tls:req:RaID'),
                                $serial,
                                $this->config->get('tls:req:Pin')
                        );

			# is $crt a valid cert?
			if (!empty($crt) and openssl_x509_parse($crt))
			{
				$crtpath = $this->config->get('dir:crt') . $data->ServerName . '.crt';
				if (!file_exists($crtpath))
				{
					# write file to destination
					if (!file_put_contents($crtpath, $crt))
					{
						$this->cli->eerror('Error writing to file: ' . $crtpath);
						return false;
					}
					else
					{
						$this->cli->einfo('Certificate written to: ' . $crtpath);
						return true;
					}
				}
				else
				{
					$this->cli->eerror('Certificate ' . $crtpath . ' already exists.');
					return false;
				}
			}
			else
			{
				$this->cli->einfo("Didn't get a valid certificate. Probably it is not ready yet.");
				return false;
			}
		}
		return false;
	}

	public function deployKeyPair($servername)
	{
		$ts = date('Y.m.d-H.i.s');
		$path['new']['crt'] = $this->config->get('dir:crt') . $servername . '.crt';
		$path['new']['key'] = $this->config->get('dir:key') . $servername . '.key';

		$path['current']['crt'] = $this->config->get('dir:currentcrt') . $servername . '.crt';
		$path['current']['key'] = $this->config->get('dir:currentkey') . $servername . '.key';
		$path['current']['chain'] = $this->config->get('dir:currentchain') . $servername . '.chain';

		$path['backup']['crt'] = $this->config->get('dir:backup') . $ts . '-' . $servername . '.crt';
		$path['backup']['key'] = $this->config->get('dir:backup') . $ts . '-' . $servername . '.key';

		# find matching private key (current or new)
		$newcrt = new \RRZE\PublicKey;
		$newcrt->readFile($path['new']['crt']);

		$newkey = new \RRZE\PrivateKey;
		$newkey->readFile($path['new']['key']);

		$currentkey = new \RRZE\PrivateKey;
		$currentkey->readFile($this->config->get('dir:currentkey') . $servername . '.key');

		$suffixes = false;
		if ($newkey->matchPublicKey($newcrt->crt))
		{
			$this->cli->einfo('Certificate matches new key.');
			$suffixes = ['crt', 'key'];
		}

		elseif ($currentkey->matchPublicKey($newcrt->crt))
		{
			$this->cli->einfo('Certificate matches current key.');
			$suffixes = ['crt'];
		}

		else
		{
			$this->cli->eerror('No matching private key found for: ' . $currentkey->filepath);
			return false;
		}

		if ($suffixes)
		{
			# first copy current keys to backup
			$this->cli->einfo('Backing up current key(s) for ' . $servername, ['crt' => $path['current']['crt'], 'key' => $path['current']['key']]);
			foreach ($suffixes as $suffix)
			{
				if (file_exists($path['current'][$suffix]))
				{
					$this->cli->einfo("Copying " . $path['current'][$suffix] . " to " . $path['backup'][$suffix]);
					if (copy($path['current'][$suffix], $path['backup'][$suffix]))
					{
						$this->cli->esuccess("Success!");
					}
					else
					{
						$this->cli->eerror("Error!");
						return false;
					}
				}
				else
				{
					$this->cli->einfo("File " . $path['current'][$suffix] . " does not exist");
				}
			}

			# then move new key(s) to current location
			$this->cli->einfo('Deploying key(s) for ' . $servername);
			foreach ($suffixes as $suffix)
			{
				$this->cli->einfo("Moving " . $path['new'][$suffix] . " to " . $path['current'][$suffix]);
				if (rename($path['new'][$suffix], $path['current'][$suffix]))
				{
					$this->cli->esuccess("Success!");
				}
				else
				{
					$this->cli->eerror("Error!");
					return false;
				}
			}

			# create chained certificate
			{
				$dfnchain = file_get_contents($this->config->get('dir:currentcrt') . 'dfn-ca.chain');
				$freshcrt = file_get_contents($path['current']['crt']);

				$this->cli->einfo("Creating chained certificate");
				if (file_put_contents($this->config->get('dir:currentcrt') . $servername . '.chained.crt', $freshcrt . "\n" . $dfnchain))
				{
					$this->cli->esuccess("Success!");
				}
				else
				{
					$this->cli->eerror("Error!");
					return false;
				}
			}

			# finally link $servername.chain to DFN-CA chain
			$this->cli->einfo('Link ' . $servername . '.chain to dfn-ca.chain');
			chdir($this->config->get('dir:currentcrt'));
			# remove existing link
			if (is_link($this->config->get('dir:currentcrt') . $servername . '.chain'))
			{
				unlink($this->config->get('dir:currentcrt') . $servername . '.chain');
			}
			if (symlink('dfn-ca.chain', $servername . '.chain'))
			{
				$this->cli->esuccess("Success!");
			}
			else
			{
				$this->cli->eerror("Error!");
				return false;
			}

			$this->cli->einfo('Deployed new key(s) for ' . $servername);
			return true;
		}
		return false;
	}

	private function _saveRequestData($serial, $data)
	{
		$result = false;
		if (is_writable($this->config->get('dir:todo:dfn')))
		{
			$result = file_put_contents($this->config->get('dir:todo:dfn') . $serial . '.json', json_encode($data, JSON_PRETTY_PRINT));
			if ($result)
			{
				$this->cli->einfo('Request data written to ' . $this->config->get('dir:todo:dfn') . $serial . '.json.');
				return $result;
			}
		}
		$this->cli->eerror('Request data could not be written.');
		return $result;
	}

	private function _loadRequestData($serial)
	{
		$filepath = $this->config->get('dir:todo:dfn') . (int) $serial . '.json';
		$result = false;
		if (is_readable($filepath))
		{
			$result = json_decode(file_get_contents($filepath));
			return $result;
		}
		$this->cli->eerror('Request data could not be read: ');
		return $result;
	}

}
