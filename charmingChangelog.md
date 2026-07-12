## [1.2.0] - 2026-07-12 - Backend Authentication & Incident Reporting
 
### Summary
 
Implemented the core PHP backend layer connecting the existing MySQL schema
(`zatcher_db`) to the application: user authentication (register/login with
password hashing), incident report submission, and evidence file uploads.
Frontend forms are not yet wired up — this phase covers backend endpoints only.
 
--------
 
### New Files Created
 
#### config/db.php
- Centralized PDO connection helper (`getDbConnection()`)
- Uses prepared statements, exception-based error mode
#### models/Incident.php
- `Incident` class wrapping incident-related queries
- Methods: `create()`, `findById()`, `findBySuspectIdentifier()`
#### auth/register.php
- Validates input, checks for duplicate email/phone
- Hashes passwords with `password_hash()` (bcrypt) before storing
#### auth/login.php
- Verifies credentials with `password_verify()`
- Starts session on success, regenerates session ID to prevent fixation
#### victim/report.php
- Authenticated endpoint for submitting new incident reports
- Validates required fields, inserts via `Incident` model
#### analyst/upload_evidence.php
- Authenticated endpoint for attaching evidence files to an incident
- Validates real MIME type (not client-supplied), enforces 5MB size limit
- Generates random filenames to avoid overwrite/collision and path traversal
--------
 
### Testing Notes
 
- Verified locally using PHP's built-in dev server (`php -S localhost:8000`)
- Confirmed all backend files load without fatal errors
- **Frontend forms not yet connected** — `register.html` and `login.html` are
  still static placeholder pages with no `<form>` elements. This is the next
  required step before end-to-end testing is possible.
--------
 
### Next Steps (for Frontend Team)
 
1. Add `<form>` markup to `register.html` and `login.html` matching existing
   `.placeholder-page` / `.placeholder-content` styling
2. Form field `name` attributes must match backend expectations exactly:
   - Register: `full_name`, `email`, `phone_number`, `password`
   - Login: `email`, `password`
3. Point form `action` at `auth/register.php` / `auth/login.php`
4. Decide on submission method: standard form POST (page reload) vs. `fetch()`
   AJAX call (backend returns JSON either way)
 

