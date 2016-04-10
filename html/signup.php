<?php
require('checks.php');
require('storage/user.php');

if (isset($_POST["username"])) {
	$user = User::load($_POST["username"]);
	if ($user !== NULL) {
		$message = "A user with that name already exists.";
	} else if ($_POST["password"] !== $_POST["password_confirm"]) {
		$message = "The passwords don't match.";
	} else if (preg_match('/^[a-z]([a-z0-9_]*)$/i', $_POST["username"]) !== 1) {
		$message = "Your username is weird and we don't really \"get\" it.";
	} else {
		$user = User::create();
		$user->setField('id', $_POST['username']);
		$user->setPassword($_POST['password']);
		$user->setField('verified', 'false');
		$user->setField('photo', '');
		$user->save();
		session_start();
		$_SESSION['user'] = $user->getField('id');
		header('Location: /');
		exit();
	}
}

$title = "Sign up!";
require("_header.php");
?>
	<h1>Thinkr</h1>
	<h2>Your thoughts, disordered.</h2>
	<p>Welcome to Thinkr. Our mission is to make the world a better place through thinking nice thoughts.
	<p>We believe that every thought is as important as every other thought, so we show you everyone's thoughts
	in a <strong>completely random</strong> and <strong>bias free</strong> order.
	<p>Because Thinkr is still in beta, an admin is required to approve your account before you post any thoughts. This process can take 2-4 days. You will still be able to view thoughts which other people have posted without having an approved account. Thankyou for your patience.
	<form class="form-horizontal" id='signup' method='post'>
		<?php if (isset($message)) { echo "<p class='bg-danger'>$message"; } ?>
		<div class="form-group">
			<label class="col-sm-2 control-label" for="id_username">Username</label>
			<div class="col-sm-10">
				<input class="form-control" type="text" id="id_username" name="username">
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-2 control-label" for="id_password">Password</label>
			<div class="col-sm-10">
				<input class="form-control" type="password" id="id_password" name="password">
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-2 control-label" for="id_password_confirm">Confirm password</label>
			<div class="col-sm-10">
				<input class="form-control" type="password" id="id_password_confirm" name="password_confirm">
			</div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button type="submit" class="btn btn-default">Sign up</button>
			</div>
		</div>
	</form>
	<pre style="margin-top: 15px">flag{nOm-n0M-t@st1e-c00K13}</pre>
<?php
	require("_footer.php");
?>