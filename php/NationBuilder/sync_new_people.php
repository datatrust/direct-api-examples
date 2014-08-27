<?php

	// Could easily be changed to use a cursor stored in file
	$last_sync_date = '<DATE IN YYYYMMDD HERE>';

	// DT Settings
	$endpoint = '<API ENDPOINT HERE>';
	$token = '<CLIENT TOKEN HERE>';
	$dql_where = "<YOUR DQL HERE>";
	$dql_limit = "<RESULT LIMIT HERE>";

	// NB Settings
	$nb_token = "<NB TOKEN HERE>"; // See http://nationbuilder.com/api_quickstart
	$nb_slug = "<NB SLUG NAME HERE>";

	// Direct Mapping
	$dt_to_nb = array(
		'firstname'=>'first_name',
		'middlename'=>'middle_name',
		'lastname'=>'last_name',
		'precinct'=>'precinct_code',
		'precinctname'=>'precinct_name',
		'statevoteridnumber'=>'state_file_id',
		'mediamarket'=>'media_market_name',
		'registrationdate'=>'registered_at',
		'statesenatedistrict'=>'state_upper_district',
		'statelowerhousedistrict'=>'state_lower_district',
		'rnc_regid'=>'rnc_regid',
		'rncid'=>'rnc_id',
		'sex'=>'sex',
		'nameprefix'=>'prefix',
		'namesuffix'=>'suffix',
		'emailaddress'=>'email',
		'phonenumber'=>'phone',
		'dateofbirth'=>'birthdate',
		'rnccalcparty' => 'inferred_support_level'
	);

	$address_map = array(
		"addressline1" => "address1",
		"addressline2" => "address2",
		"addresscity" => "city",
		"addressstate" => "state"
	);

	$nb_url = "https://" . $nb_slug . ".nationbuilder.com/api/v1/people?access_token=" . $nb_token;

	print "Attempting sync of new registrations since " . $last_sync_date . "...\n";

	$dql_select = "firstname,middlename,lastname,dateofbirth,race,emailaddress,phonenumber,phonehasdonotcallflag,reg_addressline1,reg_addressline2,reg_addresscity,reg_addressstate,reg_addresszip5,reg_addresszip4,precinct,precinctname,statevoteridnumber,party,rnccalcparty,mediamarket,registrationdate,statesenatedistrict,statelowerhousedistrict,rnc_regid,rncid,sex,nameprefix,namesuffix,mail_addressline1,mail_addressline2,mail_addresscity,mail_addressstate,mail_addresszip5,mail_addresszip4,latitude,longitude,mail_latitude,mail_longitude";

	$dql_query = "SELECT " . $dql_select . " WHERE " . $dql_where . " AND vtr_rowcreatedatetime>'" . $last_sync_date . "' LIMIT " . $dql_limit;

	$request_url = 'https://' . $endpoint . '/v2/api/query_get_file.php?ClientToken=' . $token . '&q=' . urlencode($dql_query);
	$request_result = json_decode(file_get_contents($request_url),true);

	if($request_result["Success"] != true) {
		print_r($request_result);
	} else {
		$call_id = $request_result["Call_ID"];
		$check_url = 'https://' . $endpoint . '/v2/api/get_call.php?ClientToken=' . $token . '&Call_ID=' . $call_id;

		print "Finished sending call " . $call_id . ". Waiting for a result...\n";

		do {
			$check_result = json_decode(file_get_contents($check_url), true);
			sleep(2);
		} while($check_result["Results"][0]["status"] == "created");

		if($check_result["Results"][0]["status"] != "complete") {
			print_r($check_result);
		} else {
			$file_url = $check_result["Results"][0]["reads"][0]["file_url"];
			$file_url_split = split("/", $file_url);
			$filename = end($file_url_split);

			print "URL received at " . $file_url . ". Downloading...\n";

			file_put_contents(__DIR__ . "/" . $filename, fopen($file_url, 'r'));

			print "Finished downloading. Starting processing...\n";

			$file = fopen(__DIR__ . "/" . $filename ,'r');
			$header = fgetcsv($file,0,",");

			while(! feof($file)) {
				$record = fgetcsv($file,0,",");

				if(count($record) > 1) {
					$record_better = array();

					for ($i=0; $i<count($record); $i++) {
						$record[$i] = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
						'|[\x00-\x7F][\x80-\xBF]+'.
						'|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
						'|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
						'|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
						'?',$record[$i]);

						$record[$i] = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
						'|\xED[\xA0-\xBF][\x80-\xBF]/S','?',$record[$i]);

						$record_better[$header[$i]] = trim($record[$i]);
					}

					$record = $record_better;

					$person = array();

					foreach($record as $rk => $rv) {
						if($rv != '') {
							if(array_key_exists($rk,$dt_to_nb)) {
								$person[$dt_to_nb[$rk]] = $rv;
							}
						}
					}

					switch ($record['race']) {
						case 'B':
							$person['demo'] = "Black";
							break;
						case 'W':
							$person['demo'] = "White";
							break;
						case 'H':
							$person['demo'] = "Hispanic";
							break;
						case 'A':
							$person['demo'] = "Asian";
							break;
						case 'O':
						case 'I':
							$person['demo'] = "Other";
							break;
					}

					switch ($record['party']) {
						case 'R':
							$person['party'] = 'R';
							break;							
						case 'D':
							$person['party'] = 'D';
							break;
						case 'I':
							$person['party'] = 'I';
							break;
						case 'U':
							$person['party'] = 'U';
							break;
						case 'T':
							$person['party'] = 'L';
							break;
						case 'M':
							$person['party'] = 'E';
							break;
						case 'G':
							$person['party'] = 'G';
							break;
						case 'C':
							$person['party'] = 'C';
							break;
						case 'F':
							$person['party'] = 'F';
							break;	
						case 'P':
							$person['party'] = 'P';
							break;
						case 'E':
							$person['party'] = 'A';
							break;
						case 'O':
						case 'L':
						case 'X':
						case 'S':
						case 'W':
						case 'A':
							$person['party'] = 'O';
							break;	
					}

					$mailing_address=$registered_address=array();

					foreach($address_map as $k_dt => $k_nb) {
						if($record['reg_' . $k_dt] != '') {
							$registered_address[$k_nb] = $record['reg_' . $k_dt];
						}

						if($record['mail_' . $k_dt] != '') {
							$mailing_address[$k_nb] = $record['mail_' . $k_dt];
						}
					}

					if($record['reg_addresszip5'] != '') {
						if($record['reg_addresszip4'] != '') {
							$registered_address['zip'] = $record['reg_addresszip5'] . "-" . $record['reg_addresszip4'];
						} else {
							$registered_address['zip'] = $record['reg_addresszip5'];
						}
					}

					if($record['mail_addresszip5'] != '') {
						if($record['mail_addresszip4'] != '') {
							$mailing_address['zip'] = $record['mail_addresszip5'] . "-" . $record['mail_addresszip4'];
						} else {
							$mailing_address['zip'] = $record['mail_addresszip5'];
						}
					}

					if($record['mail_latitude'] != '' && $record['mail_longitude'] != '') {
						$registered_address['lat'] = $record['mail_latitude'];
						$registered_address['lng'] = $record['mail_longitude'];
					}

					if($record['latitude'] != '' && $record['longitude'] != '') {
						$mailing_address['lat'] = $record['latitude'];
						$mailing_address['lng'] = $record['longitude'];
					}

					if(count($registered_address) > 0) {
						$registered_address['country_code'] = 'US';
					}

					if(count($mailing_address) > 0) {
						$mailing_address['country_code'] = 'US';
					}

					$person['mailing_address'] = $mailing_address;
					$person['registered_address'] = $registered_address;

					if($record['phonehasdonotcallflag'] == 'Y') {
						$person['federal_donotcall'] = true;
					} else if($record['phonehasdonotcallflag'] == 'N') {
						$person['federal_donotcall'] = false;
					}

					if($record['rnccalcparty'] == '1' || $record['rnccalcparty'] == '2') {
						$person['inferred_party'] = 'R';
					} else if($record['rnccalcparty'] == '4' || $record['rnccalcparty'] == '5') {
						$person['inferred_party'] = 'D';
					}

					//$person['is_supporter'] = false;
					$person['is_prospect'] = true;
					//$person['is_volunteer'] = false;

					$data = array('person' => $person);

					$ch = curl_init($nb_url);

					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
					curl_setopt($ch, CURLOPT_TIMEOUT, '10');
					curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json","Accept: application/json"));
					 
					$json_data = json_encode($data);

					print_r($person);

					curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
					 
					$json_response = curl_exec($ch);
					curl_close($ch);
					 
					$response = json_decode($json_response, true);
					
					print_r($response);
				}
			}
		}
	}

?>
