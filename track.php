<?php

// check for debug mode
$test = (isset($_GET['test'])) ? true : false;

// include class, instantiate new OpenTrack object
include 'lib/OpenTrack.php';
$tracker = new OpenTrack($test);

// connect to database
$tracker->dbConnect("address", "username", "password", "databasename", "tablename");

// optional: prevent log directory structure
$tracker->logsDirOrganise(false);

// running tracking and save results, print them if in debug mode
$results = $tracker->track();
if($test){
	echo "<pre>" . print_r($results, true) . "</pre>";
}

?>