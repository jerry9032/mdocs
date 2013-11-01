<?php

$libs = require_once("list.php");

$input = strtolower( $_GET['input'] );
$len = strlen($input);


$results = array();

if ($len > 0) {
    for ($i = 0; $i < count($libs); $i++) {
        //if (strtolower(substr(utf8_decode($libs[$i]), 0, $len)) == $input) {
        if (strpos($libs[$i], $input, 0) != false) {
            $results[] = array(
                "id" => ($i + 1) ,
                "value" => htmlspecialchars($libs[$i])
            );
        }
    }
}


header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
header ("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header ("Pragma: no-cache"); // HTTP/1.0
header("Content-Type: application/json");

$arr = array();
foreach ($results as $res) {
    $arr[] = array(
        "id" => $res['id'],
        "value" => $res['value']
    );
}

echo json_encode(array("results" => $arr));

?>
