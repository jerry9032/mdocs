<?php

function _404_special() {
	global $mdoc_config;
	echo applyTemplate('bootstrap3-404', $mdoc_config);
}

function _create_special() {
	global $mdoc_config;
	echo applyTemplate('bootstrap3-create', array_merge(
		$mdoc_config,
		array(
			"stage" => "index"
		)
	));
}

function _create_do_special() {
	$scm_path = $_POST["scm_path"];
	$parts = explode("/", $scm_path);
	foreach($parts as $part) {
		$valid = preg_match("/^[-_\w\d]+$/", $part);
		if ( $valid == false ) {
			echo "\"$part\" is not a valid part of svn_path.";
			return ;
		}
	}

	global $doc_root;
	$ret = exec("cd $doc_root && [ -f $scm_path/meta.md ]", $output, $return_code);
	if ($return_code == 0) {
		// meta.md already exist
		header("Location: /$scm_path");
	}
	$ret = exec("cd $doc_root > /dev/null && mkdir -p '$scm_path' && cp meta-sample.md '$scm_path/meta.md'", $output, $return_code);

	if ($return_code != 0) {
		echo "shell exec mkdir/cp error, err_no=$return_code.";
		return;
	}
	if (function_exists("_git_create"))
		_git_create($scm_path);
	else {
		echo "add git repo failed.";
		return ;
	}

	header("Location: /$scm_path");
}

register_special('404', '_404_special');
register_special('create', '_create_special');
register_special('create.do', '_create_do_special');

?>
