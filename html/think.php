<?php
require('checks.php');
require_once('storage/user.php');
require_once('storage/thought.php');
require_once('storage/user_thought.php');

$user = User::load($_SESSION['user']);

if (isset($_POST["thought"]) && $user->getField('verified') === 'true') {
	$id = md5($_SESSION['user'].'|'.(string)time().'|'.$_POST['thought']);
	$thought = Thought::create();
	$thought->setField('id', $id);
	$thought->setField('data', $_POST["thought"]);
	$thought->save();

	$edge = UserThoughtEdge::create();
	$edge->setField('user', $_SESSION['user']);
	$edge->setField('thought', $thought->getField('id'));
	$edge->save();
}

$title = "Think";
require('_header.php');
?>
<?php
if ($user->getField('verified') === 'true') {
?>
	<h1>Post a thought</h1>
	<form class="form-horizontal" id='think' method='post'>
		<div class="form-group">
			<textarea class="form-control" type="text" id="id_thought" name="thought" placeholder="How Can Mirrors Be Real If Our Eyes Aren't Real"></textarea>
		</div>
		<div class="form-group">
			<button type="submit" class="btn btn-default">Post thought</button>
		</div>
	</form>
	<h1>Your recent thoughts</h1>
	<ul id="thoughts">
		<?php
			$edges = UserThoughtEdge::withUser($_SESSION['user'], 5);
			foreach ($edges as $edge) {
				$thought = Thought::load($edge->getField('thought'));
				echo "<li>" . $thought->getLink() . "</li>";
			}
		?>
	</ul>
<?php
} else {
?>
<h1>You do not have permission to think.</h1>
<p>Only verified users can think. Please wait for an admin to approve your account. This can take 2-4 days.
<?php
}
require('_footer.php'); ?>