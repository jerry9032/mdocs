<?php

function _raw_outcome($cat = 'content')
{
	global $mdoc_config;
	$scm_path = $_GET['scm_path'];
	returnMergedFile('_doc/man', "_cache/man_raw_$cat", $scm_path, "raw_outcome_$cat");
}

function _raw_toc() { _raw_outcome('toc'); }
function _raw_content() { _raw_outcome('content'); }

register_special('raw_toc', '_raw_toc', '');
register_special('raw_content', '_raw_content', '');
