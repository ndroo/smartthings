<?php

require_once("../config.php");
require_once("../cpdo.class.php");

/*
Example json

{"date":"Thu Feb 22 19:00:32 UTC 2018","name":"switch","displayName":"Sump Pump","device":"Sump Pump","deviceId":"0fba1f29-80bd-4856-ad7d-462733cd89ca","value":"off","isStateChange":"true","id":"a7f249b8-1802-11e8-a1f2-630b1b3f70bd","description":"null","descriptionText":"{{ linkText }} {{ name }} is {{ value }}","installedSmartAppId":"null","isoDate":"2018-02-22T19:00:32.147Z","isDigital":"false","isPhysical":"false","location":"152 Strachan Ave","locationId":"54f68d81-b3a0-450a-a68a-6d277ea883cb","unit":"null","source":"DEVICE","program":"SmartThings"}
 */

$contents = file_get_contents('php://input');
$contents = json_decode($contents,true);

if(!isset($_REQUEST['key']) || $_REQUEST['key'] !== $auth_key) {
	http_response_code(401);
	die(json_encode(array("success"=>false,"error"=>['code'=>2,'message'=>"Authentication failure"])));
}
else if(!isset($contents['deviceId']) || !isset($contets['value']) || !isset($contents['name']) || !isset($contents['displayName']))
{
	$data = array();
	$data['id'] = $contents['deviceId'];
	$data['type'] = $contents['name'];
	$data['val'] = $contents['value'];
	$data['name'] = $contents['displayName'];

	$val_type = null;
	switch($contents['name']) {
		case 'switch':
			$val_type = 'bool';
			break;
		case 'temperature':
		case 'level':
		case 'power':
			$val_type = 'double';
			break;
		case 'motion':
		case 'lock':
		case 'presence':
		case 'contact':
			$val_type = 'string';
			break;
	}

	$data['val_type'] = $val_type;
	$data['timestamp'] = time();

	// Create connection
	$conn = new CPDO($constring, $username, $password);
	$conn->Insert("sensors",$data);

	http_response_code(200);
	die(json_encode(array("success"=>true)));
}
else {
	error_log("Bad request");
	$return= array("success"=>false,"error"=>array());
	$return['error']['code'] = 1;
	$return['error']['message'] = "One or more required fields absent from request";
	http_response_code(400);
	die(json_encode($return));
}
