<?php
require('config.php');
session_start();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function has_referral() {
	if (!isset($_COOKIE["has_referral"])) {
		setcookie("has_referral", '0', 0, '/');
		$_COOKIE["has_referral"] = '0';
	}
	return (bool)(int)$_COOKIE["has_referral"];
}

function is_signed_in() {
	return isset($_SESSION['user']);
}

function can_view_page() {
	global $path;
	if ($path === '/beta.php') {
		return TRUE;
	}
	if ($path === '/signup.php') {
		return has_referral();
	}
	return is_signed_in();
}

if (!isset($GLOBALS['checks_run'])) {
	if (!can_view_page()) {
		if ($path == '/signup.php') {
			header("Location: /beta.php");
		} else {
			header("Location: /signin.php");
		}
		exit();
	}
	$GLOBALS['checks_run'] = 1;
}