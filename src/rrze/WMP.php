<?php
namespace RRZE;
use Config;
use SebastianBergmann\Diff\Differ;

class WMP extends Singleton {
	public $request_url;
	public $response;

	public function options2URL($options = false)
	{
		$this->options = $options;
		$component = [];
		$config = \RRZE\Config::getInstance();

		if ($options === false)
		{
			$options = $config->get('getopt');
		}

		$map = [
			'd' => 'name',
			't' => 'type',
			'i' => 'id',
			's' => 'status'
		];

		foreach ($map as $key => $value)
		{
			if (isset($options[$key]))
			{
				if (is_array($options[$key]) && count($options[$key]) > 1)
				{
					$component[$value] = implode(',', $options[$key]);
				}
				else
				{
					$component[$value] = $options[$key];
				}
			}
		}

		# build url
		$url = $config->get('wmp:data_source');
		foreach ($component as $key => $value)
		{
			$url .= '/' . $key . '/' . $value;
		}
		$this->request_url = $url;
		return $url;
	}

	public function getResponse($url)
	{
		$ch = curl_init();
		curl_setopt_array(
			$ch, array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => TRUE,
			)
		);
		$input = curl_exec($ch);
		curl_close($ch);
		$api_result = ((array) json_decode($input));
		$this->response[$url] = $api_result;
		return $api_result;
	}

	public function writeCNF($url)
	{
		$config = \RRZE\Config::getInstance();
		$options = $config->get('getopt');

		$cli = \RRZE\CLI::getInstance();

		if (!$this->response[$url])
		{
			$this->getResponse($url);
		}

		foreach ($this->response[$url] as $id => $item)
		{
			if (isset($item->opensslcnf))
			{
				$overwrite = false;
				$filepath = $config->get('dir:cnf') . 'openssl.' . $item->servername . '.cnf';
				$linkpath = $config->get('dir:todo:cnf') . 'openssl.' . $item->servername . '.cnf';
				if (file_exists($filepath))
				{
					$current_cnf = file_get_contents($filepath);
					if ($current_cnf !== $item->opensslcnf)
					{
						$cli->einfo($item->servername);
						$differ = new Differ($config->get('differ:header'), $config->get('differ:line_numbers'));
						$cli->ewarn($differ->diff(file_get_contents($filepath), $item->opensslcnf));
						if ($cli->yesno($config->get('cli:question:overwrite')))
						{
							file_put_contents($filepath, $item->opensslcnf);
							$this->insertTodoLink($filepath, $linkpath);
						}
					}
				}
				else
				{
					file_put_contents($filepath, $item->opensslcnf);
					$this->insertTodoLink($filepath, $linkpath);
				}
			}
		}
	}

	public function insertTodoLink($filepath, $linkpath)
	{
		$cli = \RRZE\CLI::getInstance();
		$result = symlink($filepath, $linkpath);
		if ($result === false) 
		{
			$cli->eerror('Konnte keinen Symlink von ' . $linkpath . ' auf die openssl.cnf-Datei ' . $filepath . 'anlegen!');
		}
	}
}
