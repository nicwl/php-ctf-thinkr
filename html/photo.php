<?php
require('checks.php');
require_once('storage/user.php');
require_once('storage/thought.php');
require_once('storage/user_thought.php');

$user = User::load($_SESSION['user']);

if (isset($_POST["submit"]) && $user->getField('verified') === 'true') {
	$target_dir = "uploads/";
	$target_file = $target_dir . basename($_FILES["file"]["name"]);
	$uploadOk = 1;
	// Check if image file is a actual image or fake image
    $check = getimagesize($_FILES["file"]["tmp_name"]);
    if($check === false) {
        $uploadOk = 0;
    }
    // Check if file already exists
	if (file_exists($target_file)) {
	    $uploadOk = 0;
	}
	// Check file size
	if ($_FILES["file"]["size"] > 500000) {
	    $uploadOk = 0;
	}
	if ($uploadOk == 1) {
		move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
		$user->setField('photo', $target_file);
		$user->save();
	}
}

$title = "Upload picture";
require('_header.php');
?>
<?php
if ($user->getField('verified') === 'true') {
?>
	<h1>Express yourself</h1>
	<form class="form-horizontal" id='upload' method='post' enctype="multipart/form-data">
		<div class="form-group">
			<label class="col-sm-2 control-label" for="id_file">Select image</label>
			<div class="col-sm-10">
				<input class="form-control" type="file" id="id_file" name="file">
			</div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button type="submit" name="submit" class="btn btn-default">Upload photo</button>
			</div>
		</div>
	</form>
<?php
} else {
?>
<h1>You do not have permission to personalize.</h1>
<p>Only verified users can personalize. Please wait for an admin to approve your account. This can take 2-4 days.
<?php
}
require('_footer.php'); ?>