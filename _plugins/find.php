<?php

function _find_special() {
	if (!isset($_GET['q'])) {
		header('Status: 500 Internal Error');
	}

	$q = escapeshellarg($_GET['q']);
	$q = strtolower($q);
	$ret = exec("cd _doc/man > /dev/null && find . -name meta.md | grep $q | sort", $output, $return_code);

	if ($return_code != 0) {
		var_dump($return_code);
		return ;
	}

	foreach($output as &$o) {
		$o = str_replace("./", "", $o);
		$o = str_replace("/meta.md", "", $o);
	}
	unset($o);
	$results = array();
	foreach ($output as $i => $o) {
		$results[] = array(
			"id" => $i,
			"value" => $o,
		);
	}
	echo json_encode(array("results" => $results));
}

register_special('find', '_find_special');

?>
