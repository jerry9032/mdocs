<?php
require_once(dirname(__FILE__) . '/lib/mymarkdown.php');
require_once(dirname(__FILE__) . '/lib/Spyc.php');
$mdoc_config = include(dirname(__FILE__) . '/config.php');

function parseMarkdown($md) {
    $parser = new MyMarkdown();
    $html = $parser->transform($md);
    $toc = $parser->getToc();
    return array('html' => $html, 'toc' => $toc);
}

$_template_data = NULL;

function _tmpl_cb($matches) {
    global $_template_data;
    $key = strtolower($matches[1]);
    if (isset($_template_data[$key]))
        return $_template_data[$key];
    else
        return null;
}

function applyTemplate($template, $data) {
    global $_template_data;
    if ($template == NULL) {
        $template = 'default.html';
    } else {
        $template = "$template.html";
    }
    $tmpl = file_get_contents('_template/' . $template);
    if ($tmpl === false) return false;
    $_template_data = $data;
    $retval = preg_replace_callback('/{{{([^}]*)}}}/', '_tmpl_cb',  $tmpl);
    $_template_data = NULL;
    return $retval;
}

function generate($file, $data) {
    global $mdoc_config;
    $contents = file_get_contents($file);
    $arr = explode("\n---\n", $contents, 2);
    if (count($arr) < 2) {
        return false;
    }
    $yaml = $arr[0];
    $md = $arr[1];
    $yaml = spyc_load($yaml);
    $result = parseMarkdown($md);

    $data['content'] = $result['html'];
    $data['title'] = $yaml['title'];
    $data['author'] = $yaml['author'];
    $data['toc'] = $result['toc'];
    $data['site_name'] = $mdoc_config['site_name'];

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
    $md = "# 目录\n## 文章列表\n";
    if (!empty($linkdir)) {
        $linkdir = '/' . $linkdir;
    }
    foreach ($files as $f) {
        $md .= "* [{$f['title']}]($linkdir/{$f['name']}) _last updated by **{$f['author']}** on ".strftime("%Y-%m-%d %H:%M",$f['mtime'])."_\n";
    }
    

    //generate page
    $result = parseMarkdown($md);
    $html = $result['html'];
    $data['source_link'] = '#';
    $data['content'] = $result['html'];
    $data['title'] = $linkdir;
    $data['author'] = '';
    $data['toc'] = $result['toc'];
    $data['site_name'] = $mdoc_config['site_name'];
    return applyTemplate("default", $data);
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
        file_put_contents("$cache.$rand", generate($ori, $data));
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
