<?php
function _search_special() {
    if (!isset($_GET['q'])) {
        //view search page
    } else {
        //view search result
        $q = escapeshellarg($_GET['q']);
        $ret = exec("cd _doc >/dev/null && grep --color=always -R -m 1 --include '*.md' -i $q . | sed -e 's/\033\[01;31m/<span style=\"color:red\">/g' -e 's,\033\[00m,</span>,g' | perl -pe 's,^./(.*?).md:,\\1\\000,'", $output, $return_code);
        if ($return_code != 0) {
            var_dump($return_code);
            return;
        }
        $result = array();
        foreach ($output as $o) {
            $res = explode("\000", $o);
            $file = $res[0];
            $file_esc = escapeshellarg($file);
            $title = shell_exec("awk -F: '/^title:/{print $2; exit 0;} /^---/{exit 0;}' _doc/$file_esc.md");
            if ($title === NULL || $title === '') $title = $file;
            $result[] = array(
                'file' => $file,
                'title' => trim($title),
                'summary' => $res[1],
            );
        }
        if (!isset($_GET['ajax'])){
            $output = array();
            $output[] = 'title: Search result';
            $output[] = '---';
            $output[] = '## Search result';
            foreach($result as $r) {
                $output[] = "### [{$r['title']}]({$r['file']})";
                $output[] = "{$r['summary']}...";
            }
            echo generate(implode("\n", $output), array('source_link' => '#'));
        } else {
            if (!isset($_GET['callback'])) {
                echo json_encode($result);
            } else {
                echo $_GET['callback'];
                echo "(";
                echo json_encode($result);
                echo ");";
            }
        }
    }
}

function _search_template_data_filter($data) {
    $data['search'] = array(
        'link' => '/special:search',
        'ajax_link' => '/special:search?ajax=1',
        'query' => 'q',
    );
    return $data;
}

register_special('search', '_search_special');
add_filter('template_data', '_search_template_data_filter');
