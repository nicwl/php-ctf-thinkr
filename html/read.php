<?php
require('checks.php');
require_once('storage/thought.php');
require_once('storage/user_thought.php');
require_once('storage/user.php');

$thought = Thought::load($_GET['thought']);
$user = NULL;
$edge = array_values(UserThoughtEdge::withThought($thought->getField('id')))[0];
if ($edge != NULL) {
	$author = User::load($edge->getField('user'));
}

$title = $thought->getPreview(10);
require('_header.php');
?><h1>Thought by <?php echo $author ? $author->getField('id') : 'someone'; ?></h1>
<div id='thought'>
<?php echo htmlspecialchars($thought->getField('data')); ?>
</div>
<?php require('_footer.php');