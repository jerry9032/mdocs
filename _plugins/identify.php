<?php

// ODP 环境中的打日志插件

require_once(dirname(__FILE__) . "/../../php/phplib/bd/Init.php");
Bd_Init::init("man");

function _identify_who_view() {
  $user = Saf_SmartMain::getUserInfo();
  if (empty($user)) {
    $user = Bd_PhpCas::login();
  }
  $ua = $_SERVER["HTTP_USER_AGENT"];
  Bd_Log::notice("action[view] user[$user] ua=[$ua]");
}

add_action("before view", "_identify_who_view");

?>
