# Thinkr - Vulnerability Report

## Summary

This report documents all security vulnerabilities found in the Thinkr PHP web application. The application is a microblogging platform with user registration, authentication, thought posting, and profile photo upload features. It contains multiple critical, high, and medium severity vulnerabilities that can be chained to achieve full application compromise.

---

## Vulnerability 1: Information Disclosure via HTML Comment (Flag 1)

**Severity:** Low
**File:** `html/_header.php:8`
**Type:** CWE-615 — Inclusion of Sensitive Information in Source Code Comments

### Description

A flag (sensitive data) is embedded directly in an HTML comment in the page header, visible to anyone who views the page source on any page of the application.

```html
<!-- flag{3nt3r-th3-m@tr1><} -->
```

### Impact

Any unauthenticated user can view the page source and obtain this sensitive value. HTML comments are delivered to the client and are trivially accessible.

### Recommendation

Remove sensitive data from HTML comments. Never embed secrets, flags, or internal notes in client-facing markup.

---

## Vulnerability 2: Broken Access Control via Cookie Manipulation (Flag 2)

**Severity:** High
**File:** `html/checks.php:6-12`
**Type:** CWE-284 — Improper Access Control / CWE-565 — Reliance on Cookies without Validation

### Description

Access to the signup page is gated by a client-side cookie `has_referral`. The application sets `has_referral=0` for new visitors and checks its value to determine if the user should be allowed to access `/signup.php`:

```php
function has_referral() {
    if (!isset($_COOKIE["has_referral"])) {
        setcookie("has_referral", '0', 0, '/');
        $_COOKIE["has_referral"] = '0';
    }
    return (bool)(int)$_COOKIE["has_referral"];
}
```

An attacker can simply set `has_referral=1` in their browser cookies to bypass the beta restriction and access the signup page, which also reveals a flag:

```html
<pre style="margin-top: 15px">flag{nOm-n0M-t@st1e-c00K13}</pre>
```

### Impact

- Complete bypass of the "invite-only beta" access control
- Any visitor can create accounts
- Sensitive data (flag) exposed on the signup page

### Recommendation

- Never use client-side cookies for authorization decisions
- Implement server-side referral token validation (e.g., signed tokens stored in the database)
- Remove sensitive data from page templates

### Additional Cookie Issues

The cookie is also set without `HttpOnly`, `Secure`, or `SameSite` flags, making it vulnerable to interception and XSS-based theft.

---

## Vulnerability 3: Path Traversal / Local File Inclusion via `read.php` (Flag 3)

**Severity:** Critical
**File:** `html/read.php:7`, `html/storage/fsmodel.php:15-16, 37-48`
**Type:** CWE-22 — Improper Limitation of a Pathname to a Restricted Directory (Path Traversal)

### Description

The `read.php` endpoint passes the `thought` GET parameter directly to `Thought::load()`, which constructs a file path by concatenating the user-supplied ID to a base directory:

```php
// read.php:7
$thought = Thought::load($_GET['thought']);

// fsmodel.php:15-16
private static function getObjFile($id) {
    return static::getDir() . $id;
}
```

The only validation is a length check (`MAX_ID_LENGTH = 32`):

```php
static function load($id) {
    if (strlen($id) > self::MAX_ID_LENGTH) {
        throw new Exception("ID exceeds maximum length");
    }
    // ...
    $data = file_get_contents(static::getObjFile($id));
}
```

The base directory is `/var/www/html/data/Thought/`. With the 32-character limit, an attacker can use path traversal sequences to read files within a constrained radius. For example:

| Payload | Resolves To | Characters |
|---------|-------------|------------|
| `../../read.php` | `/var/www/html/read.php` | 15 |
| `../User.csv` | `/var/www/html/data/User.csv` | 13 |
| `../../config.php` | `/var/www/html/config.php` | 18 |
| `../../storage/user.php` | `/var/www/html/storage/user.php` | 24 |

Reading `../User.csv` reveals the entire user database including password hashes, salts, and a flag stored as a user entry:

```csv
FLAG,,flag{[_[_t3LeP@thy-1ntEn5if13s_]_]},true,
```

### Impact

- Arbitrary file read within the webroot (limited by 32-char max)
- Full exposure of user credentials (salts and hashes)
- Application source code disclosure
- Discovery of internal directory structure

### Recommendation

- Validate that IDs contain only expected characters (e.g., hex characters for MD5 hashes): `if (!preg_match('/^[a-f0-9]{32}$/', $id))`
- Use `basename()` or `realpath()` to prevent directory traversal
- Never construct file paths from raw user input

---

## Vulnerability 4: PHP Type Juggling in Password Comparison (Flag 4)

**Severity:** Critical
**File:** `html/storage/user.php:20-26`
**Type:** CWE-697 — Incorrect Comparison / CWE-1024 — Comparison with Incompatible Type

### Description

The `checkPassword()` method uses PHP's loose comparison operator (`==`) instead of strict comparison (`===`):

```php
function checkPassword($pw) {
    $hash = static::getHash($this->getField('salt'), $pw);
    if ($hash == $this->getField('hash')) {  // VULNERABLE: loose comparison
        return TRUE;
    }
    return FALSE;
}
```

PHP's `==` operator performs type juggling. When two strings both look like numbers in scientific notation (e.g., `0e123456...`), PHP converts them to floats before comparing. Both evaluate to `0.0`, so they compare as equal.

The user `elephant` has a stored hash of `0e612198634316944013585621061115` (salt: `vunp`). This hash matches the pattern `0e[0-9]+`, which PHP interprets as `0 * 10^612... = 0.0`.

Any password whose `md5("vunp" . $password)` also produces a hash matching `0e[0-9]+` will be accepted. The probability of a random MD5 matching this pattern is approximately `(1/16)^2 * (10/16)^30 ≈ 3 in 10^9`, which is trivially brute-forceable. Known working passwords include `rtvt3dj12u` and `1dxrw487z0`.

### Impact

- Authentication bypass for the `elephant` account (a verified user)
- Gaining verified status grants access to posting thoughts and uploading files
- Flag 4 (`flag{fl0@t-l1k3-A-5tR1n9}`) is displayed on the home page for verified users

### Recommendation

- Use strict comparison (`===`) for all security-sensitive comparisons
- Use `password_hash()` and `password_verify()` instead of raw MD5
- Never use MD5 for password hashing — use bcrypt, scrypt, or Argon2

---

## Vulnerability 5: Unrestricted File Upload Leading to Remote Code Execution (Flag 5)

**Severity:** Critical
**File:** `html/photo.php:9-30`
**Type:** CWE-434 — Unrestricted Upload of File with Dangerous Type

### Description

The photo upload feature checks that the uploaded file is a valid image using `getimagesize()`, but does not validate or restrict the file extension:

```php
$target_file = $target_dir . basename($_FILES["file"]["name"]);
// ...
$check = getimagesize($_FILES["file"]["tmp_name"]);
if($check === false) {
    $uploadOk = 0;
}
// ...
if ($uploadOk == 1) {
    move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
}
```

An attacker can create a polyglot file that is both a valid JPEG and a valid PHP file by appending PHP code to a JPEG:

```bash
cat image.jpg payload.php > pwn.php
```

Where `payload.php` contains:

```php
<?php echo file_get_contents("../flagsflagsflagsflagsflagsflagsflagsflags/flag4"); ?>
```

The file passes the `getimagesize()` check (valid JPEG header) and is saved with the `.php` extension in the web-accessible `uploads/` directory. Requesting `http://target/uploads/pwn.php` executes the PHP payload.

### Impact

- Remote Code Execution (RCE) on the web server
- Ability to read any file the web server process can access
- Full server compromise
- Flag 5: `flag{1M4G3-1N3-th3-p0S51b1Lit1ES}`

### Recommendation

- Whitelist allowed file extensions (`.jpg`, `.png`, `.gif` only)
- Rename uploaded files to random names with forced extensions
- Store uploads outside the webroot or in a directory with PHP execution disabled
- Use content-type validation in addition to `getimagesize()`
- Add `php_flag engine off` to the `uploads/.htaccess`

---

## Vulnerability 6: Weak Password Hashing (MD5)

**Severity:** High
**File:** `html/storage/user.php:10-12`
**Type:** CWE-328 — Use of Weak Hash / CWE-916 — Use of Password Hash With Insufficient Computational Effort

### Description

Passwords are hashed using plain MD5 with a 4-character salt:

```php
private static function getHash($salt, $pw) {
    return md5($salt.$pw);
}
```

MD5 is cryptographically broken and extremely fast to compute, making brute-force and dictionary attacks practical. The 4-character salt from a 36-character keyspace (`0-9a-z`) provides only 1,679,616 possible salts, which is insufficient to resist rainbow table attacks.

### Impact

- Offline password cracking is trivially fast with modern hardware
- All user passwords in the leaked `User.csv` can be recovered

### Recommendation

- Use `password_hash()` with `PASSWORD_BCRYPT` or `PASSWORD_ARGON2ID`
- These functions handle salting automatically with sufficient entropy

---

## Vulnerability 7: Insecure Random Number Generation

**Severity:** Medium
**File:** `html/util.php:21`
**Type:** CWE-330 — Use of Insufficiently Random Values

### Description

The `random_str()` function uses `rand()` instead of `random_int()`, despite the comment claiming it uses a CSPRNG:

```php
function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyz')
{
    // ...
    $str .= $keyspace[rand(0, $max)];  // NOT cryptographically secure
    // ...
}
```

This function generates password salts. `rand()` uses a linear congruential generator that is predictable and seedable.

### Impact

- Password salts are predictable if the PRNG state can be determined
- Reduces the effective entropy of the salting scheme

### Recommendation

- Replace `rand()` with `random_int()` as the comment suggests
- Or use `random_bytes()` for generating salts

---

## Vulnerability 8: Sensitive Data in User Database Row

**Severity:** Medium
**File:** `html/data/User.csv:1`
**Type:** CWE-312 — Cleartext Storage of Sensitive Information

### Description

A flag is stored as a fake user entry in the user database CSV:

```csv
FLAG,,flag{[_[_t3LeP@thy-1ntEn5if13s_]_]},true,
```

The "hash" field literally contains the flag in cleartext. Anyone who gains read access to `User.csv` (via the path traversal vulnerability or direct file access) obtains this secret.

### Impact

- Sensitive data exposed alongside user records
- No additional authentication required beyond file read access

---

## Vulnerability 9: Verbose Error Messages / Information Disclosure

**Severity:** Medium
**File:** `html/config.php:6-8`, `html/storage/fsmodel.php:39,45`
**Type:** CWE-209 — Generation of Error Message Containing Sensitive Information

### Description

Error display is enabled in production:

```php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);
```

Exception messages reveal internal file paths and application structure:

```
Fatal error: Uncaught exception 'Exception' with message
'Could not find storage at /var/www/html/data/Thought/3'
in /var/www/html/storage/fsmodel.php:45
```

### Impact

- Exposes absolute file paths, directory structure, and class names
- Aids attackers in crafting path traversal payloads and understanding the application architecture

### Recommendation

- Set `display_errors = 0` in production
- Log errors to a file instead of displaying them
- Use generic error messages for user-facing output

---

## Vulnerability 10: Cross-Site Request Forgery (CSRF)

**Severity:** Medium
**Files:** `html/signin.php`, `html/signup.php`, `html/think.php`, `html/photo.php`
**Type:** CWE-352 — Cross-Site Request Forgery

### Description

No CSRF tokens are present on any form in the application. All state-changing operations (login, signup, posting thoughts, uploading photos) are vulnerable to CSRF attacks.

### Impact

- An attacker could craft a malicious page that submits forms on behalf of authenticated users
- Could be used to post thoughts, upload malicious files, or change account settings

### Recommendation

- Implement CSRF tokens on all forms
- Validate the token server-side on every state-changing request

---

## Vulnerability 11: Stored XSS via Photo URL in CSS

**Severity:** High
**File:** `html/_header.php:18`
**Type:** CWE-79 — Cross-Site Scripting (Stored)

### Description

The user's photo path is injected directly into a `<style>` tag without sanitization:

```php
if ($photo) {
    echo "<style>html { background: url('$photo'); }</style>";
}
```

The photo path is stored in the CSV and controlled by the user (it comes from the uploaded filename). An attacker could craft a filename that breaks out of the CSS context and injects arbitrary HTML/JavaScript. For example, a photo path like `'); </style><script>alert(1)</script><style>` would result in XSS.

Since the photo value is stored in `User.csv` and rendered on every page load for that user (via `_header.php`), this is a stored XSS vulnerability.

### Impact

- Session hijacking via cookie theft
- Account takeover
- Defacement

### Recommendation

- Sanitize or escape the photo URL before embedding in HTML/CSS
- Use `htmlspecialchars()` or better yet, use a whitelist of allowed path patterns
- Store only server-generated filenames, not user-supplied ones

---

## Vulnerability 12: Session Fixation

**Severity:** Medium
**File:** `html/signin.php:10-12`
**Type:** CWE-384 — Session Fixation

### Description

After successful authentication, the session ID is not regenerated:

```php
if ($user->checkPassword($_POST["password"])) {
    session_start();
    $_SESSION["user"] = $_POST["username"];
    header("Location: /");
    exit();
}
```

An attacker who can set a victim's session ID (e.g., via a URL parameter or subdomain cookie) could authenticate as the victim after login.

### Recommendation

- Call `session_regenerate_id(true)` immediately after successful authentication

---

## Vulnerability 13: CSV Injection / Append-Only Database Design Flaw

**Severity:** Medium
**File:** `html/storage/csvmodel.php:18-26`
**Type:** CWE-1286 — Improper Validation of Syntactic Correctness of Input

### Description

The `CSVModel::save()` method appends to the CSV file, and `load()` returns the *most recent* row matching an ID. This means any user can overwrite another user's data by creating a new entry with the same ID (if they can control the ID field). In the case of `User`, the signup form does check for existing usernames, but the underlying storage mechanism has no integrity protection.

Additionally, the fields are written via `fputcsv()` but user-controlled data (like the photo path) is never sanitized for CSV metacharacters, which could corrupt the database.

### Recommendation

- Implement proper database integrity (use a real database)
- Validate and sanitize all fields before CSV storage
- Use unique constraints enforced at the storage layer

---

## Vulnerability 14: No Rate Limiting / Brute Force Protection

**Severity:** Medium
**File:** `html/signin.php`
**Type:** CWE-307 — Improper Restriction of Excessive Authentication Attempts

### Description

There is no rate limiting, account lockout, or CAPTCHA on the login form. Combined with the weak MD5 hashing and the type juggling vulnerability, this makes brute-force attacks practical.

### Recommendation

- Implement rate limiting (e.g., exponential backoff after failed attempts)
- Add CAPTCHA after multiple failed login attempts
- Consider account lockout with notification

---

## Vulnerability 15: Exposed .git Directory (Potential)

**Severity:** High
**File:** (noted in `flagsflagsflagsflagsflagsflagsflagsflags/README`)
**Type:** CWE-538 — Insertion of Sensitive Information into Externally-Accessible File or Directory

### Description

The README in the flags directory warns:

> You should remove the .git directory else the game is really easy.

If the `.git` directory is present in the webroot and accessible via HTTP, an attacker can reconstruct the entire source code repository, including all historical commits, flags, and secrets.

### Recommendation

- Remove `.git` from the webroot
- Block access to `.git` via web server configuration

---

## Vulnerability 16: Hardcoded Secrets Directory Path

**Severity:** Low
**File:** `html/config.php:12`
**Type:** CWE-798 — Use of Hard-coded Credentials

### Description

The flags directory path is hardcoded in config.php with a comment that draws attention to it:

```php
// This is where flags are stored so that they can't be accessed
// by viewing the source. Do not attempt to access this directory.
$GLOBALS['FLAGS_DIR'] = 'flagsflagsflagsflagsflagsflagsflagsflags';
```

This path is discoverable via the path traversal vulnerability (reading `config.php`) and its "security through obscurity" protection is the long directory name exceeding the 32-char `MAX_ID_LENGTH` — which is bypassed by the file upload RCE.

---

## Attack Chain Summary

The vulnerabilities can be chained for full compromise:

1. **View source** -> Flag 1 (HTML comment)
2. **Set cookie `has_referral=1`** -> Access signup page -> Flag 2 (visible on page) -> Create account
3. **Path traversal via `read.php`** -> Read `User.csv` -> Flag 3 (stored as user entry) + all password hashes
4. **Type juggling attack on `elephant`'s hash** -> Login as verified user -> Flag 4 (shown on home page)
5. **Upload PHP polyglot as profile photo** -> RCE -> Read flag files -> Flag 5

---

## Flags Found

| # | Flag | Vulnerability Used |
|---|------|--------------------|
| 1 | `flag{3nt3r-th3-m@tr1><}` | HTML source comment |
| 2 | `flag{nOm-n0M-t@st1e-c00K13}` | Cookie manipulation |
| 3 | `flag{[_[_t3LeP@thy-1ntEn5if13s_]_]}` | Path traversal to read User.csv |
| 4 | `flag{fl0@t-l1k3-A-5tR1n9}` | PHP type juggling auth bypass |
| 5 | `flag{1M4G3-1N3-th3-p0S51b1Lit1ES}` | Unrestricted file upload / RCE |
