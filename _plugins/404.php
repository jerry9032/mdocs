<?php

function _404_special() {

	global $mdoc_config;
	echo applyTemplate('bootstrap3-404', $mdoc_config);

}

register_special('404', '_404_special');

?>
