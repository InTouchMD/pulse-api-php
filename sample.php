<?php

$apiURL = "API_URL_HERE";
$apiKey = "API_KEY_HERE";

$p = new PulseApi($apiURL, $apiKey);

$contact = [
	//'id'            => 1000,
	'firstname'     => 'Sample',
	'middlename'    => 'Test',
	'lastname'      => 'Contact',
	'_customFields' => [
		'npi'       => 111222333,
		'ims_id'    => 123456,
	],
];
$emails = [
	[
		'email_address' => 'test@example.com',
	],
];

if(!$response = $p->saveFullContact($contact, $emails))
{
	$response = $p->errors();
}

echo "<pre>";
var_dump($response);
