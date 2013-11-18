<?php

// ODP 环境中的打日志插件

require_once(dirname(__FILE__) . "/../../php/phplib/bd/Init.php");
Bd_Init::init("man");

function _identify_get_user() {
  $user = Saf_SmartMain::getUserInfo();
  if (empty($user)) {
    $user = Bd_PhpCas::login();
  }
  return $user;
}

function _identify_action($action) {
  $user = _identify_get_user();
  $host = $_SERVER["HTTP_HOST"];
  $ua = $_SERVER["HTTP_USER_AGENT"];
  Bd_Log::notice("host[$host] action[$action] user[$user] ua[$ua]");
}

function _identify_who_view() { _identify_action('view'); }
function _identify_who_edit() { _identify_action('edit'); }
function _identify_who_post() { _identify_action('post'); }

add_action("before view", "_identify_who_view");
add_action("before_edit", "_identify_who_edit");
add_action("before_commit", "_identify_who_post");

?>
