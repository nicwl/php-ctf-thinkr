<?php
require('storage/user.php');

if (isset($_POST["username"])) {
	$user = User::load($_POST["username"]);
	if ($user === NULL) {
		$message = "User does not exist";
	} else {
		if ($user->checkPassword($_POST["password"])) {
			session_start();
			$_SESSION["user"] = $_POST["username"];
			header("Location: /");
			exit();
		} else {
			$message = "Incorrect password";
		}
	}
}
$title = "Sign in";
require('_header.php');
?>
	<h1>Thinkr</h1>
	<p>Welcome! Come share your thoughts!</p>
	<form class="form-horizontal" id='signin' method='post'>
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
			<div class="col-sm-offset-2 col-sm-10">
				<button type="submit" class="btn btn-default">Sign up</button>
			</div>
		</div>
	</form>
	<p>Don't have an account? <a href="/signup.php">Get one!</a>
<?php require('_footer.php'); ?>