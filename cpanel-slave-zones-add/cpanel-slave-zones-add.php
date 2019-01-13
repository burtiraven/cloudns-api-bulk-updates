<?php

// Auth ID and Password
define("AUTH_ID", 2404);
define("AUTH_PASS", "nospe8684");

// IP address of the master server (primary server)
define("MASTER_IP", "185.136.96.66");

// Second IP address for master server (it may be IPv6 or IPv4 address)
//define("MASTER_IP2", "185.136.97.66");

// the directory with the zone files, their names are used to create the slave zones, not the content of the files
define("ZONES_DIR", "/var/named/");
// this file will contain a list of files that are not dns zone files and there won't be a request to be added the next time the script runs
define("TMPFILE", "/tmp/cloudns_invalid-zone-names.txt");


// function to connect to the API
function apiCall ($url, $data) {
	$url = "https://api.cloudns.net/{$url}";
	$data = "auth-id=".AUTH_ID."&auth-password=".AUTH_PASS."&{$data}";
	$init = curl_init();
	curl_setopt($init, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($init, CURLOPT_URL, $url);
	curl_setopt($init, CURLOPT_POST, true);
	curl_setopt($init, CURLOPT_POSTFIELDS, $data);
	$content = curl_exec($init);
	curl_close($init);
	return json_decode($content, true);
}

// checking if we can log in successfully
$login = apiCall('dns/login.json', "");
if (isset($login['status']) && $login['status'] == 'Failed') {
	die($login['statusDescription']);
}

// gets the content of the file
$fopen = fopen(TMPFILE, "a+");

// gets the content of the file
$invalid_zones = file(TMPFILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// gets the zone files names
$handle = opendir(ZONES_DIR);
if ($handle) {
	// loops through the files
	while (false !== ($zoneName = readdir($handle))) {
		// checks if the zone name is invalid and if not adds the slave zone
		if (in_array($zoneName, $invalid_zones)) {
			continue;
		}

		// check file format
		if (!strpos($zoneName, '.db')) {
			file_put_contents(TMPFILE, $zoneName."\n", FILE_APPEND);
		}
		$zoneName = preg_replace('/\.db$/', '', $zoneName);

		//calling the api
		$response = apiCall('dns/register.json', "domain-name={$zoneName}&zone-type=slave&master-ip=".MASTER_IP);
		// if the api returns the zone is invalid we put it in the file with the invalid zones
		if ($response['status'] == 'Failed') {
			file_put_contents(TMPFILE, $zoneName."\n", FILE_APPEND);
			continue;
		}
		
		if (defined('MASTER_IP2')) {
			apiCall('dns/add-master-server.json', "domain-name={$zoneName}&master-ip=".MASTER_IP2);
		}
	}

	closedir($handle);
}
fclose($fopen);
