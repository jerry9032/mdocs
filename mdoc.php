<?php
require_once(dirname(__FILE__) . '/lib/Spyc.php');
require_once(dirname(__FILE__) . '/lib/markdown-geshi.php');

// support for hooks
$_action_callbacks = array();
$_filter_callbacks = array();
define('ACTION_ABORT', '___ACTION__ABORT___');
function action_hook($name, $data = NULL) {
    global $_action_callbacks;
    if (isset($_action_callbacks[$name])) {
        foreach($_action_callbacks[$name] as $cb) {
            $ret = $cb($data);
            if ($ret === ACTION_ABORT) return false;
        }
    }
    return true;
}

function filter_hook($name, $data) {
    global $_filter_callbacks;
    if (isset($_filter_callbacks[$name])) {
        foreach($_filter_callbacks[$name] as $cb) {
            $data = $cb($data);
        }
    }
    return $data;
}

function add_action($name, $cb) {
    global $_action_callbacks;
    if (!isset($_action_callbacks[$name])) {
        $_action_callbacks[$name] = array($cb);
    } else {
        $_action_callbacks[$name][] = $cb;
    }
}

function add_filter($name, $cb) {
    global $_filter_callbacks;
    if (!isset($_filter_callbacks[$name])) {
        $_filter_callbacks[$name] = array($cb);
    } else {
        $_filter_callbacks[$name][] = $cb;
    }
}
// end support for hooks

// support for special page
$_special_pages = array();
function invoke_special($method) {
    global $_special_pages;
    if (isset($_special_pages[$method])) {
        $cb = $_special_pages[$method][0];
        $cb();
    } else {
        header('Status: 404 Not found');
        exit();
    }
}

function register_special($method, $cb, $description = NULL) {
    global $_special_pages;
    $_special_pages[$method] = array($cb, $description);
}
// end support for special page

$mdoc_config = include(dirname(__FILE__) . '/config.php');

// vhost support
$pos = strpos($_SERVER['HTTP_HOST'], ":");
if ($pos === false) {
    $host = $_SERVER['HTTP_HOST'];
} else {
    $host = substr($_SERVER['HTTP_HOST'], 0, $pos);
}
$host_config = $mdoc_config['vhost'][$host];
if ( isset($host_config) ) {
    $doc_root   = $host_config['doc_root'];
    $cache_root = $host_config['cache_root'];
} else {
    header('Status: 500 Internal Error');
}
// end support for vhost

loadConfig();

function loadConfig() {
    global $mdoc_config;
    $default_config = array(
        'site_name' => 'Mdoc documentation',
        'short_name' => 'Mdoc',
        'main_url' => '/',
        'edit_link' => '?edit=1',
        'plugins' => array(),
    );
    $mdoc_config = array_merge($default_config, $mdoc_config);
}

function loadPlugins() {
    global $mdoc_config;
    foreach ($mdoc_config['plugins'] as $p) {
        require_once(dirname(__FILE__) . "/_plugins/$p.php");
    }
}

$parser = new MarkdownGeshi_Parser();

function parseMarkdown($md) {
    global $parser;
    return $parser->transform($md);
}

function applyTemplate($template, $data) {
    require_once(dirname(__FILE__) . '/lib/smarty/Smarty.class.php');
    $smarty = new Smarty();
    $smarty->setTemplateDir(dirname(__FILE__).'/_template');
    $smarty->setCompileDir(dirname(__FILE__).'/_tmp/templates_c');
    $smarty->setCacheDir(dirname(__FILE__).'/_tmp/cache');
    $smarty->setConfigDir(dirname(__FILE__).'/_tmp/config');

    $template = filter_hook('template', $template);

    if ($template == NULL) {
        $template = 'default.html';
    } else {
        $template = "$template.html";
    }

    $data = filter_hook('template_data', $data);
    $smarty->assign($data);
    return $smarty->fetch($template);
}

function generate($contents, $data) {
    global $mdoc_config;
    $arr = explode("\n---\n", $contents, 2);
    if (count($arr) < 2) {
        $md = $content;
    } else {
        $yaml = $arr[0];
        $yaml = spyc_load($yaml);
        $data['layout'] = $yaml['layout'];
        $md = $arr[1];
    }

    $data['content'] = parseMarkdown($md);
    $data = array_merge($mdoc_config, $data);
    $generated = applyTemplate($data['layout'], $data);
    return $generated;
}

function sendfile($file, $type = "http") {
    if ($type == 'text') {
        header("Content-Type: text/plain; charset=utf-8");
    } elseif ($type == 'http') {
        header("Content-Type: text/html");
    }
    $server = $_SERVER['SERVER_SOFTWARE'];
    if (strpos($server, 'nginx') !== false) {
        header("X-Accel-Redirect: /$file");
    } else if (strpos($server, 'lighttpd') !== false) {
        header("X-Sendfile: ".dirname(__FILE__)."/$file");
    } else {
        header("Status: 500 Internal Error");
    }
}

function returnCachedFile($doc_root, $cache_root, $file) {
    global $mdoc_config;

    $file = str_replace("//", "/", $file);
    $cache = "$cache_root/" . str_replace("/", ",.,.", trim($file, '/'));
    $ori = "$doc_root/$file.md";
    $need_build = true;
    if (isset($mdoc_config['disable_cache']) && $mdoc_config['disable_cache']) {
        $need_build = true;
    } else if (file_exists($cache)) {
        $oristat = stat($ori);
        $cachestat = stat($cache);
        if ($oristat['mtime'] < $cachestat['mtime']) {
            $need_build = false;
        }
    }
    if ($need_build) {
        $rand = rand();
        if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0755, true);
        }
        $data = array('source_link' => $file);
        file_put_contents("$cache.$rand", generate(file_get_contents($ori), $data));
        rename("$cache.$rand", "$cache");
    }
    sendfile($cache);
}

// input meta.md merge files
function generateMergedFile($module_config_file, $scm_path, $last_update, $layout = null) {
    global $mdoc_config;

    $module_config = include $module_config_file;
    foreach ($module_config["nav"] as $title => $file) {
        if ( is_array($file) ) {
            $sub_array = $file;
            foreach ($sub_array as $sub_title => $sub_file) {
                $ctx = file_get_contents(dirname($module_config_file)."/".$sub_file.".md");
                $arr = explode("\n---\n", $ctx, 2);
                if (count($arr) < 2) {
                    $md = $ctx;
                } else {
                    $md = $arr[1];
                }
                $contents[$title][$sub_title] = parseMarkdown($md);
            }
        } else {
            $ctx = file_get_contents(dirname($module_config_file)."/".$file.".md");
            $arr = explode("\n---\n", $ctx, 2);
            if (count($arr) < 2) {
                $md = $ctx;
            } else {
                $md = $arr[1];
            }
            $contents[$title] = parseMarkdown($md);
        }
    }
    $data = array_merge($mdoc_config, $module_config, array(
        "source_link" => $scm_path,
        "contents" => $contents,
        "last_update" => date("dS F, Y, l", $last_update)
    ));
    $layout = isset($layout) ? $layout : $module_config['layout'];
    $generated = applyTemplate($layout, $data);
    return $generated;
}

function returnMergedFile($doc_root, $cache_root, $scm_path, $layout = null) {
    global $mdoc_config;

    $cache = "$cache_root/" . str_replace("/", ",.,.", trim($scm_path, '/'));
    $config_file = "$doc_root/$scm_path/meta.md";

    // scan files to calculate last update time
    $config = include $config_file;
    $oristat = _stat_get_latest_mtime("$doc_root/$scm_path", $config["nav"]);
    $configstat = stat($config_file);
    $last_update = max($oristat['mtime'], $configstat['mtime']);

    $need_build = true;
    if (file_exists($cache)) {
      $cachestat = stat($cache);
      if ($last_update <= $cachestat['mtime']) {
        $need_build = false;
      }
    }
    if ($need_build) {
      $rand = rand();
      file_put_contents("$cache.$rand", generateMergedFile($config_file, $scm_path, $last_update, $layout));
      rename("$cache.$rand", "$cache");
    }
    sendfile($cache);
}

function fixName($file) {
    return str_replace('/.md', '/index.md', $file);
}

loadPlugins();

$file = $_GET['path'];

// special page support
if (strpos($file, "special:") === 0) {
    // use special page
    $method = substr($file, strlen("special:"));
    invoke_special($method);
    exit();
}

// page mode
if ($_GET['post'] == 1) {
    $mode = 'post';
} else if ($_GET['edit'] == 1) {
    $mode = 'edit';
} else {
    $mode = 'view';
}

if ($mode == 'view') {
    if (!action_hook('before view', $file)) {
        exit(1);
    }
    if (is_file("$doc_root/$file")) {
        sendfile("$doc_root/$file", 'text');
    } else if (is_file(fixName("$doc_root/$file"))) {
        sendfile(fixName("$doc_root/$file"), 'text');
    } else if (is_file("$doc_root/$file/meta.md")) {
        returnMergedFile($doc_root, $cache_root, $file);
    } else if (is_file("$doc_root/$file.md")) {
        returnCachedFile($doc_root, $cache_root, $file);
    } else {
        header("Status: 404 Not found");
        exit;
    }
} else if ($mode == 'edit') {
    //edit mode
    if (!action_hook('before_edit', $file)) {
        header('Status: 403 Forbidden');
        exit(1);
    }
    sendfile("_template/_edit.html");
} else if ($mode == 'post') {
    //verify contents
    if (!action_hook('before_commit')) {
        header('Status: 403 Forbidden');
        exit(1);
    }
    $content = $_POST['content'];
    $content = filter_hook('contents_before_save_edit', $content);
    if (empty($content)) {
        echo 'content empty!';
        exit();
    }
    $filename = fixName("$doc_root/$file.md");
    $dirname = dirname($filename);
    if (!is_dir($dirname)) {
        mkdir($dirname, 0755, true);
    }
    if (!action_hook('before_save_edit', $filename)) {
        echo "hook failed";
        exit();
    }
    $ret = file_put_contents($filename, $content);
    if ($ret === false) {
        echo "write file $file error";
        exit();
    }
    if (!action_hook('after_save_edit', $filename)) {
        echo "hook failed, but file might have been modified\n";
        echo "backup your data and try editting again";
    } else {
        echo 1;
    }
}
