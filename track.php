<?php

$test = (isset($_GET['test'])) ? true : false;
include 'lib/OpenTrack.php';
$tracker = new OpenTrack($test);
$tracker->dbConnect("localhost", "dbu1025481", "fusiondb4u", "db1025481-fusion", "email_tracking");
// $tracker->dbConnect("address", "username", "password", "databasename", "tablename");
$tracker->logsDirOrganize(false);
$results = $tracker->track();
if($test){
	echo "<pre>" . print_r($results, true) . "</pre>";
}

?>