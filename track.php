<?php

$test = (isset($_GET['test'])) ? true : false;
include 'lib/OpenTrack.php';
$tracker = new OpenTrack($test);
$tracker->dbConnect("address", "username", "password", "databasename", "tablename");
$tracker->logsDirOrganise(false);
$results = $tracker->track();
if($test){
	echo "<pre>" . print_r($results, true) . "</pre>";
}

?>