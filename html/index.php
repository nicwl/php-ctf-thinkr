<?php
require('checks.php');
require('config.php');
require_once('storage/user.php');
require_once('storage/thought.php');
require_once('storage/user_thought.php');

$title = 'Thinkr';
require('_header.php');
?>
<h1>Things people have thought</h1>

<ul id="thoughts">
	<?php
		$edges = UserThoughtEdge::all(10);
		foreach ($edges as $edge) {
			$user = User::load($edge->getField('user'));
			$thought = Thought::load($edge->getField('thought'));
			echo "<li>" . $user->getField('id') . " thought ". $thought->getLink() . "</li>";
		}
	?>
</ul>

<?php
$user = User::load($_SESSION['user']);
if ($user->getField('verified') === 'true') {
	echo "<pre>".file_get_contents($GLOBALS['FLAGS_DIR'].'/flag3')."</pre>";
}
?>

<?php require('_footer.php');
