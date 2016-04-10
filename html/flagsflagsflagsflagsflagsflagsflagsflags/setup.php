<?php
require_once('config.php');
require_once('storage/user.php');
require_once('storage/thought.php');
require_once('storage/user_thought.php');

exec("chmod -R 777 data");
exec("chmod -R 777 uploads");

$fh = fopen($GLOBALS['FLAGS_DIR']."/users.txt", 'r');
$users = [];
while (!feof($fh)) {
	$line = trim(fgets($fh));
	$user = User::create();
	if ($line === 'elephant') {
		$user->setField('salt', 'vunp');
		$user->setField('hash', '0e612198634316944013585621061115');
	} else if ($line === 'FLAG') {
		$user->setField('salt', '');
		$user->setField('hash', 'flag{[_[_t3LeP@thy-1ntEn5if13s_]_]}');
	} else {
		$user->setPassword(rand().rand().rand().rand());
	}
	$user->setField('id', $line);
	$user->setField('verified', 'true');
	$user->setField('photo', '');
	$user->save();
	if ($line !== 'FLAG') {
		$users[] = $user;
	}
}

$fh = fopen($GLOBALS['FLAGS_DIR']."/data.txt", 'r');
$idx = 0;
while (!feof($fh)) {
	$line = trim(fgets($fh));
	$user = $users[$idx % count($users)];
	$id = md5($user->getField('id').'|'.(string)time().'|'.$line);
	$thought = Thought::create();
	$thought->setField('id', $id);
	$thought->setField('data', $line);
	$thought->save();

	$edge = UserThoughtEdge::create();
	$edge->setField('user', $user->getField('id'));
	$edge->setField('thought', $thought->getField('id'));
	$edge->save();

	$idx++;
}