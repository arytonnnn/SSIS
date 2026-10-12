# SSIS &mdash; PHP + MySQL conversion

This is the CCS109 `login.html` / `index.html` mockup converted into a working
PHP site that reads and writes real data through `SSIS_Database_Schema.sql`.

## 1. Folder layout

```
ssis_php/
├── config.php                  DB connection settings (edit this first)
├── login.php                   Login form + authentication
├── logout.php                  Destroys the session
├── index.php                   The student portal (all 8 tabs, live data)
├── style.css                   Unchanged from the original mockup
├── image/                      Unchanged from the original mockup
├── includes/
│   ├── auth.php                 Session start + require_login() / require_role()
│   └── functions.php            Small view helpers (escaping, peso(), badges...)
├── actions/
│   ├── submit_document.php      Handles the "Request a Document" form
│   └── update_password.php      Handles the "Change Password" form
└── database/
    ├── SSIS_Database_Schema.sql The schema you supplied, unchanged
    └── seed_data.sql            Optional demo rows + one working login
```

## 2. Set up the database (XAMPP)

1. Start **Apache** and **MySQL** from the XAMPP control panel.
2. Open phpMyAdmin (or the `mysql` CLI) and run, in order:
   - `database/SSIS_Database_Schema.sql`
   - `database/seed_data.sql` (optional, but gives you a working login and
     something to look at in every tab immediately)
3. Copy the whole `ssis_php` folder into `C:\xampp\htdocs\` (or
   `/opt/lampp/htdocs/` on Linux), e.g. `htdocs/ssis`.
4. Visit `http://localhost/ssis/login.php`.

## 3. Demo login (if you ran seed_data.sql)

| Field              | Value                     |
|---------------------|---------------------------|
| Username / Student No. | `2026-00123` or `jdelacruz` |
| Password             | `Student@123`             |
| Role                 | Student                   |

There's also a `registrar1` / `Student@123` / Registrar account to show what
happens when a non-Student role logs in (the original mockup only designed
the Student portal, so that role gets a short landing notice instead of an
invented dashboard).

## 4. If your MySQL isn't the XAMPP default

Edit the four constants at the top of `config.php`:

```php
const DB_HOST = 'localhost';
const DB_NAME = 'ssis_db';
const DB_USER = 'root';
const DB_PASS = '';
```

## 5. What changed from the static mockup, and why

- **Login is real.** `login.php` checks the submitted username/student
  number + password against `Users` (joined to `Students`), using
  `password_verify()` against the bcrypt hash in `passwordHash`. It also
  checks the account's `role` and `status` columns.
- **Passwords are hashed**, not stored in plaintext. New accounts must be
  inserted with `password_hash($plainPassword, PASSWORD_DEFAULT)` (PHP), not
  a raw string. `actions/update_password.php` does this for you when a
  student changes their password from the Settings tab.
- **Every table in the portal is a live query** (`index.php`) — enrolled
  subjects, grades, payments, document requests, and clearance status all
  come from `Enrollments`, `Grades`, `Payments`, `DocumentRequests`, and
  `ClearanceRecords` for the logged-in student, instead of being hard-coded.
- **The "Request a Document" form** now inserts a row into
  `DocumentRequests` (`actions/submit_document.php`) instead of just adding
  a card to the page with JavaScript.
- **Two labels were adjusted** to match what the schema actually supports:
  - The dashboard's "Balance Due" card is now **"Total Paid"**, summed
    from `Payments`. The schema doesn't include a tuition/fee-assessment
    table, so a real outstanding balance can't be computed — showing a
    number for it would be a guess.
  - The Subjects tab drops the instructor/room/schedule columns and the
    syllabus modal from the mockup, since none of that is in
    `Courses` — only `courseCode`, `title`, `units`, and `semester` are.
  - The "Curriculum Checklist" sub-tab from the mockup was also removed for
    the same reason (no curriculum table in the schema).
- **Tab switching is still instant/client-side** (same JS as the original),
  but writes (document requests, password changes) POST to a small PHP
  script, then redirect back to `index.php?tab=...` with a flash message —
  a normal server round-trip rather than the JS-only `alert()` calls in the
  mockup.

## 6. Security notes worth knowing for a viva/defense

- All SQL uses PDO **prepared statements** — no string-concatenated queries.
- Output is escaped with `htmlspecialchars()` (the `h()` helper) everywhere
  user-influenced or DB-sourced data is printed, to prevent stored/reflected
  XSS.
- Sessions are used for auth state; `logout.php` clears the session array,
  expires the cookie, and calls `session_destroy()`.
- `require_role()` in `includes/auth.php` is there if you want to lock a
  page to specific roles later (e.g. a future `registrar_dashboard.php`).

This was tested end-to-end (login by username, login by student number,
wrong password, wrong role, document request submission, password change,
and logout) against a local MySQL instance before being handed to you.
