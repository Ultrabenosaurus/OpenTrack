<?php

include 'lib/opentrack.class.php';
$tracker = new OpenTrack();
$tracker->dbConnect("address", "username", "password", "databasename", "tablename");
if(isset($_GET['test'])){
	$results = $tracker->track(true);
	echo "<pre>" . print_r($results, true) . "</pre>";
} else {
	$tracker->track();
}

?>