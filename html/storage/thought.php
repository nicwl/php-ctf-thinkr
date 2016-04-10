<?php
require_once('fsmodel.php');

class Thought extends FSModel {
	function getPreview($len = 140) {
		$text = $this->getField('data');
		if (mb_strlen($text) > $len-3) {
			return htmlspecialchars(mb_substr($text, 0, $len-3))."...";
		}
		return htmlspecialchars($text);
	}

	function getURL() {
		return '/read.php?thought='.$this->getField('id');
	}

	function getLink() {
		$text = $this->getPreview();
		$url = $this->getURL();
		return "<a href='$url'>$text</a>";
	}

}