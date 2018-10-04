<?php

require("functions/functions.php");

echo "<pre>";

$options = getopt("c:",array("config:"));
var_dump($options);
if(isset($options["c"]) && ( !empty($options["c"]) || $options["c"] == "0" ) && (is_numeric($options["c"]) || $options["c"] == "0" ) ){
	InstanceMonitorStartNewBreakCurrent($options["c"]);
}
echo "</pre>";

?>
