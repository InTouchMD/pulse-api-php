<?php
namespace PulseApi;

class PulseApi
{

	const EMAIL_TACTIC 			= 1;
	const DIRECT_MAIL_TACTIC 	= 2;
	const FAX_TACTIC 			= 3;
	const PHONE_TACTIC 			= 4;
	const BANNER_TACTIC 		= 5;
	const EHR_BANNER_TACTIC 	= 6;
	const WEB_TACTIC 			= 7;
	const EVENT_TACTIC 			= 8;
	const SMS_TACTIC 			= 9;


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

	/**
	* Search contacts by a condition.
	*
	* The $condition parameter must be in the following format:
	*
	* [
	*     'field'        => 'firstname',  (other examples: gender, Contact.lastname, ContactFields.custom_field, Email.email_address)
	*     'value'        => 'Test'
	*     'search_type'  => 'numeric',
	*     'match_types'  => 'contains',
	* ]
	*
	* Basic Search/Match Types:
	* numeric: equals | greater_than | less_than | in_list | not_in_list
	* text: contains | begins_with | ends_with | is | is blank | in_list | not_in_list
	* boolean: is
	* date: 'before | after | is
	*
	* For special search/match types/conditions speak with the Pulse support team for more information.
	*
	* @param mixed $condition
	*/
	public function searchContactsByCondition($condition, $additionalArgs = [])
	{
		$args = [
			'condition'    => $condition,
			'_children'    => true,
			'_unique'      => true,
			'_dont_cache'  => true,
		];

		if($additionalArgs)
		{
			$args = array_merge($args, $additionalArgs);
		}

		return $this->request('GET', 'contact/searchByCustomCondition', $args);
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
			$this->errors = [];

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
				curl_setopt($handle, CURLOPT_POST, 1);
				curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($args));
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
				return $response['body'];
			}
		}
		else
		{
			$this->errors = "An error occurred while initializing the request.";
		}

		return FALSE;
	}


	/**
	* Returns contact data based on an email address
	*
	* @param mixed $emailAddress
	* @param mixed $withChildren
	*/
	public function getContactByEmail($emailAddress, $withChildren = false)
	{
		$search = [
			'field' 		=> 'Email.email_address',
			'value' 		=> $emailAddress,
			'search_type' 	=> 'text',
			'match_type' 	=> 'is'
		];


		return $this->searchContactsByCondition($search, ['_children' => $withChildren]);
	}

	/**
	* Gets email model data from an email address
	*
	* @param mixed $emailAddress
	* @param mixed $withChildren
	*/
	public function getEmail($emailAddress, $withChildren = false)
	{
		$args = [
			'email_address' => $emailAddress,
			'_children' 	=> $withChildren
		];


		$result = $this->request('GET', 'email', $args);
		if(!$this->errors())
		{
			return reset($result);
		}

		return false;
	}

	/**
	* Checks to see if an email address is on a specific list
	*
	* @param mixed $listID
	* @param mixed $emailAddress
	*
	*/
	public function isEmailOnList($listID, $emailAddress)
	{

		// Get pulse email ID
		if($pulseEmail = $this->getEmail($emailAddress))
		{

			$method 	= "GET";
			$endpoint 	= "list_subscription";
			$args = [
				'list_id' 		=> $listID,
				'model_class' 	=> 'Stella\Pulse\Model\Fragment\Email',
				'model_id' 		=> $pulseEmail['id'],
				'contact_id'	=> $pulseEmail['contact_id']
			];

			$result = $this->request($method, $endpoint, $args);
			if(!$this->errors())
			{
				return reset($result);
			}
		}
		else
		{
			$this->errors = "Email not found in Pulse.";
		}

		return false;

	}


	/**
	* Adds an email address to a specific list
	*
	* If the email address is not on the list, it will add them and mark them
	* subscribed or unsubscribed based on the $subscribe parameter
	*
	* @param string $emailAddress
	* @param int $contactID
	* @param int $listID
	* @param bool $subscribe - set to true to subscribe, set to false to unsubscribe
	* @param string $reason
	*/
	protected function addEmailToList($emailAddress, $contactID, $listID, $subscribe = false, $reason = "")
	{
		$addedToList = false;

		// Default values
		$saveListSubscription = [
			'list_id' 		 => $listID,
			'contact_id' 	 => $contactID,
			'tactic_id' 	 => static::EMAIL_TACTIC,
			'model_class' 	 => 'Stella\Pulse\Model\Fragment\Email',
		];


		if($subscribe)
		{
			$saveListSubscription['subscribed'] = 1;
		}
		else
		{
			$saveListSubscription['subscribed'] 	= 0;
			$saveListSubscription['opt_out_reason'] = $reason;
		}

		// Check to see if the email is already on list
		if($pulseListSubscription = $this->isEmailOnList($listID, $emailAddress))
		{

			if($pulseListSubscription['subscribed'] != $saveListSubscription['subscribed'])
			{
				// On list with oppsite status
				$saveListSubscription['id'] 		= $pulseListSubscription['id'];
				$saveListSubscription['model_id'] 	= $pulseListSubscription['model_id'];
				$this->save('list_subscription', $saveListSubscription);
				if(!$this->errors())
				{
					$addedToList = true;
				}
			}
			else
			{
				// Already unsubscribed
				$addedToList = true;
			}

		}
		elseif($this->errors())
		{

			if(in_array("Email not found in Pulse.", $this->errors()))
			{
				// Email is not in Pulse

				// Add it and then mark it unsubscribed
				if($email = $this->saveEmail($emailAddress, $contactID))
				{
					$saveListSubscription['model_id'] = $email['id'];
					$saveToList = $this->save('list_subscription', $saveListSubscription);
					if(!$this->errors())
					{
						$addedToList = true;
					}
				}

			}
		}
		else
		{
			// Email is in pulse

			// Look up email in pulse
			if($email = $this->getEmail($emailAddress))
			{
				$saveListSubscription['model_id'] = $email['id'];

				$saveToList = $this->save('list_subscription', $saveListSubscription);
				if(!$this->errors())
				{
					$addedToList = true;
				}
			}

		}


		return $addedToList;


	}

	public function subscribeEmail($emailAddress, $contactID, $listID)
	{
		return $this->addEmailToList($emailAddress, $contactID, $listID, true);
	}


	public function unsubscribeEmail($emailAddress, $contactID, $reason = "", $listID = 1)
	{
		return $this->addEmailToList($emailAddress, $contactID, $listID, false, $reason);
	}

	/**
	* Saves an email address
	*
	* @param mixed $emailAddress
	* @param mixed $contactID
	* @param mixed $emailID (optional)
	*/
	public function saveEmail($emailAddress, $contactID, $emailID = false)
	{
		$emailDomain = explode("@", $emailAddress);
		if(count($emailDomain) == 2)
		{
			$args = [
				'contact_id' 	=> $contactID,
				'email_address' => $emailAddress,
				'email_domain' 	=> $emailDomain[1]
			];

			if($emailID)
			{
				$args['id'] = $emailID;
			}

			$savedEmail = $this->save('email', $args);

			if(!$this->errors())
			{
				return reset($savedEmail);
			}
		}

		return false;
	}

	public function save($model, $args)
	{
		return $this->request('POST', $model, $args);
	}

	/**
	* Adds tags to a contact
	*
	* @param mixed $contactID
	* @param mixed $tags - A single tag or an array of tags.  Can be either tag IDs or tag names
	* @param bool $resetTags - 	If set to true, this will delete any existing tags and replace with the tags given.
	* 							If false, tags will only be appended
	*/
	public function tagContact($contactID, $tags, $resetTags = false)
	{

		$args = [
			'id' 			=> $contactID,
			'_tags' 		=> $tags,
			'_reset_tags' 	=> $resetTags
		];

		$result = $this->save("contact/saveTags", $args);

		return !$this->errors();
	}

}
