<?php
require_once('csvmodel.php');
require_once('util.php');

class User extends CSVModel {
	static function getFields() {
		return array("id", "salt", "hash", "verified", "photo");
	}

	private static function getHash($salt, $pw) {
		return md5($salt.$pw);
	}

	function setPassword($pw) {
		$salt = random_str(4);
		$this->setField('salt', $salt);
		$this->setField('hash', static::getHash($salt, $pw));
	}

	function checkPassword($pw) {
		$hash = static::getHash($this->getField('salt'), $pw);
		if ($hash == $this->getField('hash')) {
			return TRUE;
		}
		return FALSE;
	}
}