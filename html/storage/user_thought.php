<?php
require_once('csvmodel.php');

class UserThoughtEdge extends CSVModel {
	static function getFields() {
		return ['id', 'user', 'thought'];
	}

	static function create() {
		$obj = parent::create();
		$obj->setField('id', rand());
		return $obj;
	}

	static function withUser($user, $limit=NULL) {
		$r = array_filter(static::all(), function ($obj) use ($user) { return $obj->getField('user') === $user; });
		if ($limit !== NULL) {
			return array_slice($r, 0, $limit);
		}
		return $r;
	}

	static function withThought($thought, $limit=NULL) {
		$r = array_filter(static::all($limit), function ($obj) use ($thought) { return $obj->getField('thought') === $thought; });
		if ($limit !== NULL) {
			return array_slice($r, 0, $limit);
		}
		return $r;	
	}
}