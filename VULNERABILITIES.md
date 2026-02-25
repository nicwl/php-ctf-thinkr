# Thinkr CTF - Vulnerability Analysis

## Flag 1: `flag{3nt3r-th3-m@tr1><}`

**Vulnerability:** Information Disclosure in HTML Source
**Location:** `html/_header.php:8`

The flag is embedded in an HTML comment in the header template. Every page that includes `_header.php` serves this flag. Viewing the page source of any page (including unauthenticated pages like `beta.php` and `signin.php`) reveals it.

**Exploitation:** View page source in a browser.

---

## Flag 2: `flag{nOm-n0M-t@st1e-c00K13}`

**Vulnerability:** Cookie-Based Access Control Bypass
**Location:** `html/signup.php:62`, `html/checks.php:6-12`

The signup page is gated behind a "referral" system, but the access control is implemented entirely via a client-side cookie (`has_referral`). The default value is `0`, but since it's client-controlled, setting it to `1` bypasses the gate entirely and reveals the signup page which contains the flag in plain text.

**Exploitation:** Set cookie `has_referral=1`, then visit `/signup.php`.

---

## Flag 3: `flag{fl0@t-l1k3-A-5tR1n9}`

**Vulnerability:** PHP Type Juggling (Loose Comparison) on Password Hash
**Location:** `html/storage/user.php:22`, `html/flagsflagsflagsflagsflagsflagsflagsflags/setup.php:15-17`

The password check uses the loose comparison operator `==` instead of `===`. The user `elephant` has hash `0e612198634316944013585621061115` (salt: `vunp`). In PHP, `0e` followed by all digits is treated as scientific notation (equals `0`). Any password producing an MD5 of `vunp` + password that also matches `0e[0-9]+` will pass the check (`0 == 0`).

Once logged in as `elephant` (a verified user), the home page (`index.php:26-28`) displays flag3.

**Exploitation:** Find a password P where `md5('vunp' . P)` produces a `0e[digits]` hash, log in as `elephant`, view the home page.

---

## Flag 4: `flag{1M4G3-1N3-th3-p0S51b1Lit1ES}`

**Vulnerability:** Unrestricted File Upload (Remote Code Execution)
**Location:** `html/photo.php:10-30`

The photo upload validates that the file has valid image headers via `getimagesize()` but does not check the file extension. An attacker can craft a polyglot file that is both a valid image and contains PHP code, name it with a `.php` extension, and upload it. Once uploaded to `uploads/`, the PHP file is directly accessible and executable.

From there, the attacker can read `flagsflagsflagsflagsflagsflagsflagsflags/flag4` (protected from direct HTTP access by `.htaccess` but not from PHP `file_get_contents()`).

**Exploitation:** Upload a PHP-image polyglot named `something.php`, then access `/uploads/something.php` to execute code and read flag4.

---

## Bonus Flag: `flag{[_[_t3LeP@thy-1ntEn5if13s_]_]}`

**Vulnerability:** Flag Stored as Plaintext in User Database
**Location:** `html/data/User.csv:1`, `html/flagsflagsflagsflagsflagsflagsflagsflags/setup.php:18-20`

The special user `FLAG` has an empty salt and its hash field is literally the flag string. This is accessible by reading `User.csv` after obtaining code execution via the photo upload vulnerability (flag 4 chain).

**Exploitation:** Achieve RCE via flag 4's vulnerability, then read `data/User.csv`.

---

## Additional Vulnerabilities (Non-Flag)

- **Stored XSS via photo field:** `html/_header.php:18` renders the photo path unsanitized into a `<style>` tag, allowing injection.
- **Weak password hashing:** MD5 with a 4-character salt is trivially brutable.
- **`rand()` instead of `random_int()`:** `html/util.php:22` uses `rand()` which is not cryptographically secure.
