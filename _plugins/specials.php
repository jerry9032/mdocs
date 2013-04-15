<?php
function _special_specials() {
    global $_special_pages;
    $output = array();
    $output[] = 'title: All specials';
    $output[] = '---';
    $output[] = '## List of all special pages';
    foreach($_special_pages as $k => $s) {
        if ($s[1] !== NULL) {
            $desc = $s[1];
        } else {
            $desc = $k;
        }
        $output[] = "* **[$k](/special:$k)** : $desc";
    }
    echo generate(implode("\n", $output), array('source_link' => '#'));
}

function _special_all_pages() {
    exec("cd _doc >/dev/null && find . -name '*.md' -type f -exec awk -F: '/^title/ {file = \"{}\"; print substr(file, 3, length(file) - 5) \"\\000\" $2; exit} /^---/{exit}' {} \;", $files, $return_code);
    if ($return_code != 0) {
        exit(1);
    }
    $output = array();
    $output[] = 'title: All pages';
    $output[] = '---';
    $output[] = '## List of all pages';
    foreach($files as $f) {
        $arr = explode("\000", $f);
        $title = $arr[1];
        $link = $arr[0];
        $output[] = "* **[$link](/$link)** : $title";
    }
    echo generate(implode("\n", $output), array('source_link' => '#'));
}

register_special('specials', '_special_specials', 'List of all special pages');
register_special('all_pages', '_special_all_pages', 'List of all pages');
