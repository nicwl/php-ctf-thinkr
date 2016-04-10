<?php
session_start();
$user = NULL;
if (isset($_SESSION['user'])) {
	$user = User::load($_SESSION['user']);
}
?><!doctype html>
<!-- flag{3nt3r-th3-m@tr1><} -->
<html>
	<head>
		<title><?php echo $title; ?> | Thinkr</title>
		<link rel="stylesheet" type="text/css" href="bootstrap.min.css">
		<link rel="stylesheet" type="text/css" href="style.css">
		<?php
			if ($user !== NULL) {
				$photo = $user->getField('photo');
				if ($photo) {
					echo "<style>html { background: url('$photo'); }</style>";
				}
			}
		?>
	</head>
	<body>
		<?php
		if ($user !== NULL) {
		?>
			<nav class="navbar navbar-default">
  				<div class="container-fluid">
	  				<div class="navbar-header">
				        <a class="navbar-brand" href="/">Thinkr</a>
				    </div>
				    <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
				        <ul class="nav navbar-nav">
				            <li><a href="/">Home</a></li>
				            <li><a href="/think.php">Think</a></li>
				            <li><a href="/photo.php">Personalize</a></li>
				        </ul>
				        <ul class="nav navbar-nav navbar-right">
				        	<li><a href="/signout.php">Log out</a></li>
				        </ul>
					</div>
				</div>
			</nav>
		<?php
		}
		?>
		<div id="content">