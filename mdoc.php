<?php
require_once(dirname(__FILE__) . '/lib/mymarkdown.php');
require_once(dirname(__FILE__) . '/lib/Spyc.php');
$mdoc_config = include(dirname(__FILE__) . '/config.php');
$default_config = array(
    'site_name' => 'Mdoc documentation',
    'short_name' => 'Mdoc',
    'main_url' => '/',
    'edit_link' => '?edit=1',
);
$mdoc_config = array_merge($default_config, $mdoc_config);

function parseMarkdown($md, $title = null) {
    $parser = new MyMarkdown();
    if (!empty($title)) {
        $parser->set_title($title);
    }
    $html = $parser->transform($md);
    $toc = $parser->getToc();
    return array('html' => $html, 'toc' => $toc);
}

function applyTemplate($template, $data) {
    require_once(dirname(__FILE__) . '/lib/smarty/Smarty.class.php');
    $smarty = new Smarty();
    $smarty->setTemplateDir(dirname(__FILE__).'/_template');
    $smarty->setCompileDir(dirname(__FILE__).'/_tmp/templates_c');
    $smarty->setCacheDir(dirname(__FILE__).'/_tmp/cache');
    $smarty->setConfigDir(dirname(__FILE__).'/_tmp/config');

    if ($template == NULL) {
        $template = 'default.html';
    } else {
        $template = "$template.html";
    }

    $smarty->assign($data);
    return $smarty->fetch($template);
}

function generate($contents, $data) {
    global $mdoc_config;
    $arr = explode("\n---\n", $contents, 2);
    if (count($arr) < 2) {
        return false;
    }
    $yaml = $arr[0];
    $md = $arr[1];
    $yaml = spyc_load($yaml);
    $result = parseMarkdown($md, $yaml['title']);

    $data = $yaml;

    $data['content'] = $result['html'];
    $data['toc'] = $result['toc'];

    $data = array_merge($mdoc_config, $data);

    $generated = applyTemplate($yaml['layout'], $data);
    return $generated;
}

function sort_cb($a, $b) {
    return $b['mtime'] - $a['mtime'];
}
function generateIndex($linkdir) {
    global $mdoc_config;
    var_dump($linkdir);
    $linkdir = trim($linkdir, '/');
    $dir = "_doc/$linkdir";
    $dirlist = scandir($dir);
    $files = array();
    foreach ($dirlist as $f) {
        if (!is_file("$dir/$f")) continue;
        if (strpos($f, '.md') !== strlen($f) - 3) continue;
        $stat = stat("$dir/$f");
        $contents = file_get_contents("$dir/$f");
        $arr = explode("\n---\n", $contents, 2);
        if (count($arr) < 2) {
            continue;
        }
        $yaml = $arr[0];
        $yaml = spyc_load($yaml);
        $name = substr($f, 0, strlen($f) - 3);
        $entry = array(
            'name' => $name,
            'author' => $yaml['author'],
            'title' => $yaml['title'],
            'mtime' => $stat['mtime'],
        );
        $files[] = $entry;
    }
    usort($files, 'sort_cb');

    //generate md from dirlist
    $md = "title: $linkdir 目录\n---\n## 文章列表\n";
    if (!empty($linkdir)) {
        $linkdir = '/' . $linkdir;
    }
    foreach ($files as $f) {
        $md .= "* [{$f['title']}]($linkdir/{$f['name']}) _last updated by **{$f['author']}** on ".strftime("%Y-%m-%d %H:%M",$f['mtime'])."_\n";
    }
    

    //generate page
    return generate($md, array('source_link' => '#'));
}

function sendfile($file, $type = "http") {
    if ($type == 'text') {
        header("Content-Type: text/plain; charset=utf-8");
    }
    header("X-Sendfile: ".dirname(__FILE__)."/$file");
}

function returnCachedFile($file) {
    $cache = "_cache/" . str_replace("/", ",.,.", trim($file, '/'));
    $ori = "_doc/$file";
    $need_build = true;
    if (file_exists($cache)) {
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

function returnCachedIndex($dir) {
    $cache = "_cache/" . str_replace("/", ",.,.", trim(trim($dir, '/') . '/index.md', '/'));
    $ori = "_doc/$dir";
    $need_build = true;
    if (file_exists($cache)) {
        $oristat = stat($ori);
        $cachestat = stat($cache);
        if ($oristat['mtime'] < $cachestat['mtime']) {
            $need_build = false;
            foreach (scandir($ori) as $f) {
                if (!is_file("$ori/$f")) continue;
                if (strpos($f, '.md') !== strlen($f) - 3) continue;
                $stat = stat("$ori/$f");
                if ($stat['mtime'] > $cachestat['mtime']) {
                    $need_build = true;
                    break;
                }
            }
        }
    }
    if ($need_build) {
        $rand = rand();
        if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0755, true);
        }
        $data = array('source_link' => $file);
        file_put_contents("$cache.$rand", generateIndex($dir));
        rename("$cache.$rand", "$cache");
    }
    sendfile($cache);
}

$file = $_GET['path'];
if ($_GET['post'] == 1) {
    $mode = 'post';
} else if ($_GET['edit'] == 1) {
    $mode = 'edit';
} else {
    $mode = 'view';
}

if ($mode == 'view') {
    if (is_file("_doc/$file.md")) {
        returnCachedFile("$file.md");
    } else if (is_file("_doc/$file")) {
        sendfile("_doc/$file", 'text');
    } else if (is_dir("_doc/$file")) {
        if (is_file("_doc/$file/index.md")) {
            returnCachedFile("$file/index.md");
        } else {
            returnCachedIndex($file);
        }
    } else {
        header("Status: 404 Not found");
        exit;
    }
} else if ($mode == 'edit') {
    //edit mode
    sendfile("_template/_edit.html");
} else if ($mode == 'post') {
    //verify contents
    $content = $_POST['content'];
    if (empty($content)) {
        echo 'content empty!';
        exit();
    }
    $filename = "_doc/$file.md";
    $dirname = dirname($filename);
    if (!is_dir($dirname)) {
        mkdir($dirname, 0755, true);
    }
    $ret = file_put_contents($filename, $content);
    if ($ret !== false) {
        echo "1";
    } else {
        echo "write file $file error";
    }
}
