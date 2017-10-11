<?php
namespace PulseApi;

class PulseApi
{
	protected
		$apiURL = "{YOUR_URL_HERE}"
		, $apiKey = "{YOUR_API_KEY_HERE}"
		, $errors = [];

	public function __construct($apiURL = false, $apiKey = false)
	{
		if($apiURL)
		{
			$this->setApiURL($apiURL);
		}
		if($apiKey)
		{
			$this->setApiKey($apiKey);
		}

		if(!$this->apiURL || !$this->apiKey)
		{
			trigger_error("An API URL and Key must be provided", E_USER_ERROR);
			return;
		}

		if(!function_exists('curl_init'))
		{
			trigger_error("Curl must be installed and enabled.", E_USER_ERROR);
			die;
		}
	}

	public function getContact($args)
	{
		return $this->request('GET', 'contact', $args);
	}

	public function getContactByID($id)
	{
		return $this->request('GET', 'contact/'.$id, []);
	}

	public function saveFullContact($contact, $emails = false, $locations = false, $phones = false, $faxes = false)
	{
		$args = $contact;
		$args['children'] = [];

		if($emails)
		{
			$args['children']['email'] = $emails;
		}
		if($locations)
		{
			$args['children']['location'] = $locations;
		}
		if($phones)
		{
			$args['children']['phones'] = $phones;
		}
		if($faxes)
		{
			$args['children']['faxes'] = $faxes;
		}

		return $this->request('POST', 'contact/saveFull', $args);
	}

	public function setApiURL($apiURL)
	{
		$this->apiURL = $apiURL;
	}

	public function setApiKey($apiKey)
	{
		$this->apiKey = $apiKey;
	}

	public function errors()
	{
		return $this->errors;
	}

	protected function request($method, $endpoint, $args = [])
	{
		$url = $this->apiURL."/".$endpoint;

		if($method == 'GET' && $args)
		{
			$url .= "?".http_build_query($args);
		}

		if($handle = curl_init($url))
		{
			curl_setopt_array($handle, [
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_HTTPHEADER => [
					'X_PULSE_API_KEY: '.$this->apiKey,
					'ACCEPT: application/json',
					'Cache-Control: no-cache',
				],
			]);

			if($method == 'POST')
			{
				var_dump($args);
				curl_setopt($handle, CURLOPT_POST, 1);
				curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($args));

				var_dump(http_build_query($args));
			}

			$response = curl_exec($handle);

			$this->errors = curl_error($handle);

			curl_close($handle);

			if($this->errors)
			{
				return FALSE;
			}

			$response = json_decode($response, true);

			if($response['code'] !== 0)
			{
				$this->errors = $response['messages'];
				return $response;
			}
			else
			{
				return $response;
			}
		}
		else
		{
			$this->errors = "An error occurred while initializing the request.";
		}

		return FALSE;
	}
}