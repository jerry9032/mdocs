<?php
require_once(dirname(__FILE__) . '/markdown-geshi.php');
class MyMarkdown extends MarkdownGeshi_Parser {
    protected $_toc = array();
    protected $_toc_id = 1;
    function set_title($title) {
        array_unshift($this->_toc, array(1, $title, 0));
    }
	function _doHeaders_callback_setext($matches) {
		# Terrible hack to check we haven't found an empty list item.
		if ($matches[2] == '-' && preg_match('{^-(?: |$)}', $matches[1]))
			return $matches[0];
		
		$level = $matches[2]{0} == '=' ? 1 : 2;
        $text = $this->runSpanGamut($matches[1]);
        $clean_text = strip_tags($this->unhash($text));
        $id = $this->_toc_id++;
		$block = "<h$level id=\"$id\">".$text."</h$level>";

        $this->_toc[] = array($level, $clean_text, $id);
		return "\n" . $this->hashBlock($block) . "\n\n";
	}
	function _doHeaders_callback_atx($matches) {
		$level = strlen($matches[1]);
        $text = $this->runSpanGamut($matches[2]);
        $clean_text = strip_tags($this->unhash($text));
        $id = $this->_toc_id++;
		$block = "<h$level id=\"$id\">".$text."</h$level>";
        $this->_toc[] = array($level, $clean_text, $id);
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

    function getToc() {
        $toc = '';
        $lastlevel = 0;
        foreach ($this->_toc as $v) {
            $level = $v[0];
            $text = $v[1];
            $id = $v[2];
            if ($level > $lastlevel) {
                $toc .= str_repeat('<ul>', $level - $lastlevel);
            } else if ($level == $lastlevel) {
                $toc .= "</li>";
            } else {
                $toc .= str_repeat("</ul></li>", $lastlevel - $level);
            }
            $toc .= "<li><a href='#$id'>$text</a>";
            $lastlevel = $level;
        }
        $toc .= str_repeat("</ul></li>", $lastlevel);
        return $toc;
    }
}
