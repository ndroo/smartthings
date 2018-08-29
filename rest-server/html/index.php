<?php

header('Content-Type: application/json');

//These should not be avaialble from the web-root to the public
require_once("../config.php");
require_once("../cpdo.class.php");

/*
Example json we're being sent by Smartthings

{"date":"Thu Feb 22 19:00:32 UTC 2018","name":"switch","displayName":"Sump Pump","device":"Sump Pump","deviceId":"0fba1f29-80bd-4856-ad7d-462733cd89ca","value":"off","isStateChange":"true","id":"a7f249b8-1802-11e8-a1f2-630b1b3f70bd","description":"null","descriptionText":"{{ linkText }} {{ name }} is {{ value }}","installedSmartAppId":"null","isoDate":"2018-02-22T19:00:32.147Z","isDigital":"false","isPhysical":"false","location":"152 Strachan Ave","locationId":"54f68d81-b3a0-450a-a68a-6d277ea883cb","unit":"null","source":"DEVICE","program":"SmartThings"}
 */

if(isset($_REQUEST['healthcheck'])) {
	$response = array();
	$conn = new CPDO($constring, $username, $password);
	$response['info'][] = "DB connection successful";

	//Test we can insert
        $data = array();
        $data['id'] = "TEST_ID";
        $data['type'] = "TEST_TYPE";
        $data['val'] = "TEST_VAL";
        $data['name'] = "TEST_NAME";
        $data['val_type'] = "TEST_VAL_TYPE";
        $data['timestamp'] = time();
	$conn->Insert("sensors",$data);

	//Test we can select
	$res = $conn->query("select * from sensors where id = 'TEST_ID'");
	$response['info'][] = "select from sensors table successful";
	$obj = $res->fetchObject();

	//did we get back what we expected?
	if(is_object($obj))
	{
		//can we delete?
		$conn->query("delete from sensors where id = 'TEST_ID'");
		$response['info'][] = "delete from sensors table successful";
		$response['status'] = "Config test passed";
		http_response_code(200);
		die(json_encode($response));
	}
	else {
		//Didn't get back what we expected
		$response['info'][] = "error selecting from sensors table";
		$response['errors'][] = "Failed to find sample record";
		$response['status'] = "Config test failed";
		http_response_code(500);
		die(json_encode($response));
	}
	//All other errors will produce 5XX's, review /var/log/apache2/error.log for more details

}

//Try extract json from body
$contents = file_get_contents('php://input');
$contents = @json_decode($contents,true);

//Does the key we're using in the app match the key for this server?
if(!isset($_REQUEST['key']) || $_REQUEST['key'] !== $auth_key) {
	http_response_code(401);
	die(json_encode(array("success"=>false,"error"=>['code'=>2,'message'=>"Authentication failure"])));
}
if(isset($_REQUEST['info'])) {
	$conn = new CPDO($constring, $username, $password);
	$result = $conn->query("select distinct(id),name,max(timestamp),(UNIX_TIMESTAMP() - max(timestamp))/60/60/24 as days_since_update from sensors join battery_sensors using (id) group by id order by days_since_update desc;");

	echo "Battery sensors not reporting in last 3 days:\n";
	while ($obj = $result->fetchObject()) {
		if($obj->days_since_update > 2) {
			$obj->days_since_update = round($obj->days_since_update,0);
			$obj->name = str_pad($obj->name,30);
			echo "$obj->days_since_update\t$obj->name\t$obj->id\n";
		}
	}

	echo "\n\n";
	echo "Battery sensors never reporting:\n";
	$result = $conn->query("select distinct(id) from battery_sensors left join sensors using (id) where sensors.id is null");
        while ($obj = $result->fetchObject()) {
		$obj->name = str_pad($obj->name,30);
		echo "$obj->id\n";
	}
	echo "\n\n";
	echo "END OF REPORT";
}
//Validate some basic eleemnts of the json to ensure we've got enough to do an insert
else if(!isset($contents['deviceId']) || !isset($contets['value']) || !isset($contents['name']) || !isset($contents['displayName']))
{
	$data = array();
	$data['id'] = $contents['deviceId'];
	$data['type'] = $contents['name'];
	$data['name'] = $contents['displayName'];

	$val_type = null;
	$val = $contents['value'];
	switch($contents['name']) {

		case 'switch':
			if($contents['value'] == "on")
				$val = 1;
			else
				$val = 0;
			$val_type = 'bool';
			break;
		case 'temperature':
			$val_type = 'double';
			break;
		case 'level':
			$val_type = 'double';
			break;
		case 'power':
			$val_type = 'double';
			break;
		case 'energy':
			$val_type = 'double';
			break;

		case 'acceloration':
                        if($contents['value'] == "active")
                                $val = 1;
                        else
                                $val = 0;
                        $val_type = 'bool';
			break;
		case 'motion':
			if($contents['value'] == "active")
				$val = 1;
			else
				$val = 0;
                        $val_type = 'bool';
			break;

		case 'lock':
                        if($contents['value'] == "locked")
				$val = 1;
			else
				$val = 0;
                        $val_type = 'bool';
                        break;

		case 'presence':
			if($contents['value'] == "present")
				$val = 1;
			else
				$val = 0;
                        $val_type = 'bool';
			break;
		case 'contact':
			if($contents['value'] == "closed")
                                $val = 1;
                        else
                                $val = 0;
                        $val_type = 'bool';
			break;

		case 'valve':
			if($contents['value'] == "closed")
				$val = 1;
			else
				$val = 0;
			$val_type = 'bool';
			break;
	}

	$data['val_type'] = $val_type;
	$data['val'] = $val;
	$data['timestamp'] = time();

	// Create connection
	$conn = new CPDO($constring, $username, $password);
	$conn->Insert("sensors",$data);

	http_response_code(200);

	die(json_encode(array("success"=>true)));
}
else {
	//Not neough details in the json, let the client know this request isn't good enough
	error_log("Bad request");
	$return= array("success"=>false,"error"=>array());
	$return['error']['code'] = 1;
	$return['error']['message'] = "One or more required fields absent from request";
	http_response_code(400);
	die(json_encode($return));
}
