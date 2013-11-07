<?php

function _stat_flatten_array($arr) {
  static $tmp = array();
  foreach ($arr as $key => $value) {
    if (is_array($value)) {
      _stat_flatten_array($value);
    } else {
      $tmp[] = $value;
    }
  }
  return $tmp;
}

function _stat_get_latest_mtime($path, $arr) {
  $arr = _stat_flatten_array($arr);
  $max = array("mtime" => 0);
  foreach($arr as $idx => $file) {
    $stat = stat("$path/$file.md");
    if ($stat === false)
      continue;
    if ($stat["mtime"] > $max["mtime"])
      $max = $stat;
  }
  return $max;
}

?>
