<?php

// include the class, instantiate a new OpenTrack object
include 'lib/opentrack.class.php';
$tracker = new OpenTrack();

// connect to the MySQL URI, access a specific database and table
$tracker->dbConnect("address", "username", "password", "databasename", "tablename");

// if running as a test, print results otherwise store information in the database
if(isset($_GET['test'])){
	$results = $tracker->track(true);
	echo "<pre>" . print_r($results, true) . "</pre>";
} else {
	$tracker->track();
}

?>