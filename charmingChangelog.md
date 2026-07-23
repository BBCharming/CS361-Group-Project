## [1.7.3] - 2026-07-23 - Fixed: dashboards needed a manual refresh to show fresh data

### The bug

None of the JSON API endpoints sent any cache headers. After running
LADINA on an evidence card, the dashboard's own follow-up
`GET /analyst/dashboard.php` (meant to pull the fresh result
immediately) could be served from the browser's HTTP cache instead of
hitting the server again — so the UI kept showing stale data until a
manual hard refresh forced the browser to actually re-request it. Same
class of risk existed on every other dashboard's data endpoints, even
though it had only been observed on this one so far.

### The fix (both server and client side, for both endpoint types)
- Added `Cache-Control: no-store` to every dashboard-data JSON/XML
  endpoint: `analyst/dashboard.php`, `police/dashboard.php`,
  `police/evidence.php`, `police/export_xml.php`,
  `zicta/dashboard.php`, `zicta/analytics.php`, `zicta/audit.php`,
  `victim/dashboard.php`, `victim/check_status.php`
- Added `cache: 'no-store'` to the matching `fetch()` calls in
  `dashboard.html`, `police-dashboard.html`, `zicta-dashboard.html`,
  `assets/js/ajax-poll.js`, and `anonymous-report.html`'s status
  checker

Belt-and-suspenders on purpose — either one alone should be enough,
but relying on only the client or only the server leaves a gap if a
proxy or an older browser handles one but not the other.

--------

## [1.7.2] - 2026-07-23 - Fixed: LADINA ran the wrong Python interpreter

### The bug

`proc_open()`'s `$env` argument **replaces the child process's entire
environment** rather than adding to it — this isn't obvious from the
PHP docs and I got it wrong. The code was passing only
`['GEMINI_API_KEY' => ...]`, which wiped out `PATH` (and everything
else) for the Python subprocess. If `php -S` was started from a shell
with a virtualenv active (`PATH` pointing at `.../cs50-env/bin` first),
that never reached the child process — it silently fell back to
whatever `python3` the OS finds with no `PATH` at all (typically system
Python), which doesn't have the same packages as the venv one. Showed
up as `[!] Missing dependency. Run: pip install google-genai` even
after actually installing it — because it was installed in the venv,
and the subprocess was never running the venv's Python.

### The fix
`$env` now starts from `getenv()` (the current process's full
environment) with `GEMINI_API_KEY` layered on top, instead of a
from-scratch array. `PATH` — and anything else a deployment might
depend on — now carries through to the Python subprocess correctly.

### Verified with a reproduction, not just re-reading the code
Built a fake `python3` binary, put its directory first on `PATH`
(simulating an activated venv), and ran both versions of the
`proc_open` call side by side: the old code ran the system Python
(`3.12.3`) and completely ignored the fake one on `PATH`; the fixed
version correctly picked up and ran the fake one. Same mechanism as
the real bug, reproduced directly rather than inferred.

--------

## [1.7.1] - 2026-07-22 - Fixed: LADINA always reported "failed"

### The bug

`includes/ladina_runner.php` expected `ZatcherAnalyzer.py`'s output at
`<full evidence path>_zatcher_intel.json`, in the `ai/` directory (that
was the `proc_open` working directory). What the script actually does:

```python
out = Path(path.stem + "_zatcher_intel.json")
out.write_text(...)
```

That's a **relative** path with the extension stripped (`abc123.png` →
`abc123_zatcher_intel.json`), resolved against the Python process's
cwd — not the `ai/` folder, and not the original filename. Every run
was silently writing its report to the wrong place, so
`is_file($outJson)` was always false and every analysis reported
"failed" even when Gemini itself succeeded.

### The fix
- `proc_open`'s working directory is now the evidence file's own
  folder (`dirname($filePath)`), matching where the script actually
  writes
- The expected output filename now matches Python's `path.stem` exactly
  (extension stripped, no directory prefix)
- Captures `stdout`/`stderr` from the Python process and logs them via
  `error_log()` when the output file still isn't found, so a real
  failure is diagnosable from the PHP error log instead of a dead end

### Verified for real, not just re-read
Ran `ZatcherAnalyzer.py` directly against a text-bearing test image with
tesseract, confirmed it writes to a bare relative filename resolved
against cwd (reproduced the exact bug in isolation). Then ran the
*fixed* `runLadinaAnalysis()` against the same image with a deliberately
invalid `GEMINI_API_KEY` — confirmed it correctly locates and parses
the output this time (Gemini's own "failed after all retries" fallback
note came through as a successful *report*, rather than the function
falsely returning null). Temp JSON file cleanup after read also
confirmed working.

--------

## [1.7.0] - 2026-07-22 - Report Case Button (Victim Dashboard)

### victim-dashboard.html
- New "Report a Case" button opens a report form inline (category,
  title, description, suspect phone/email, transaction ID, optional
  evidence file) — submits to `victim/report.php`
- On success, immediately refreshes the case table via a new
  `window.zatcherRefreshCaseTracker` hook (see below) instead of
  waiting for the next 30s poll tick

### victim/report.php
- Now accepts an optional evidence file in the same submission,
  reusing the shared upload helper (see rename below)

### includes/evidence_upload.php (renamed from anonymous_evidence_upload.php)
- This helper never actually checked anything about anonymous vs
  logged-in — it just validates/stores a file. Now that
  `victim/report.php` uses it too, kept the name accurate:
  `handleAnonymousEvidenceUpload()` → `handleEvidenceUpload()`. All
  three callers (`victim/report.php`, `victim/report_anonymous.php`,
  `victim/upload_evidence_anonymous.php`) updated.

### assets/js/ajax-poll.js
- Exposes `window.zatcherRefreshCaseTracker` so other scripts on
  `victim-dashboard.html` can force an immediate table refresh instead
  of waiting for the poll interval

--------

## [1.6.0] - 2026-07-22 - Anonymous Reporting

### Summary

New capability: file a complete fraud report — with optional evidence
in the same submission — with no account at all. In return, the
reporter gets a reference code, since there's no login to check status
against later.

### schema/migration_002_anonymous_reports.sql
- `incidents.user_id` is now nullable (`NULL` = anonymous)
- New `incidents.reference_code` column (unique, only populated for
  anonymous submissions) — verified against a live MariaDB import:
  column exists, nullable, unique index confirmed

### models/Incident.php
- `create()` now accepts a null `user_id` and an optional
  `reference_code`
- New `findByReferenceCode()` — deliberately returns only
  status-tracker-safe fields (title, category, status, timestamps,
  evidence count), not suspect details or the description
- New `generateReferenceCode()` — short, unambiguous (no 0/O/1/I)
  code like `ZR-7F3K9Q2A`, collision-checked against the DB

### New public (no-session) endpoints
- **victim/report_anonymous.php** — files the report; accepts an
  optional evidence file in the same multipart POST
- **victim/upload_evidence_anonymous.php** — attach evidence to an
  existing anonymous report later, using the reference code as the
  only "auth" (same model as a shipping tracking number)
- **victim/check_status.php** — look up status by reference code

### includes/anonymous_evidence_upload.php (new)
- Shared file-validation/storage logic (real MIME check, random
  filename, 5MB cap) for the two upload paths above — same approach
  as `analyst/upload_evidence.php`, kept separate since the auth model
  differs (none vs session) and these never fail the surrounding
  report over a bad attachment

### Fixed: anonymous reports would have silently vanished from every dashboard
`analyst/dashboard.php`, `police/evidence.php`, and
`police/export_xml.php` all did an **inner** `JOIN users` on
`incidents.user_id` to get the reporter's name. An anonymous incident
has no user row, so an inner join would have hidden it from analysts
and police entirely — the opposite of a filed report. Switched all
three to `LEFT JOIN` with `COALESCE(u.full_name, 'Anonymous report')`.

### New frontend
- **anonymous-report.html** — the report form (with optional evidence
  upload), a status-check form, and an "add more evidence" form that
  appears once a valid reference code is looked up
- **index.html** — added a secondary "Report Anonymously" button next
  to the existing "Report Fraud Now" CTA; didn't touch the existing one

### ⚠️ Not verified against a live server this round (same as 1.5.0)

MariaDB itself started fine every time; the sandbox's background
`php -S` process consistently hung this round, same issue as last
time. Verified instead by: (1) actually running the migration against
a live MariaDB import and confirming the resulting column
types/nullability/unique index directly, and (2) manually cross-
checking every field name the frontend reads against the exact backend
response it's reading from. `php -l` and `node -c` both pass on
everything new. Worth testing the full anonymous submission → status
check → evidence-add flow yourself before relying on it.

--------

## [1.5.0] - 2026-07-21 - Police & ZICTA Dashboards

### Summary

`police-dashboard.html` and `zicta-dashboard.html` were still the
original mock-data placeholders (`CF-201`, `SB-1042`, etc.) with no JS
at all, even though their backend endpoints (`police/dashboard.php`,
`police/evidence.php`, `police/export_xml.php`, `zicta/dashboard.php`,
`zicta/analytics.php`, `zicta/audit.php`) have existed since 1.3.0.
Wired both up the same way as the analyst/victim dashboards.

### police-dashboard.html
- Stats: Pending / Investigating / Verified, from `police/dashboard.php`
- "Verified Cases" table (resolved/closed only) with a View button per
  case
- Case detail panel: full incident info + evidence trail from
  `police/evidence.php?incident_id=`, plus an **Export XML** button
  that opens `police/export_xml.php?incident_id=` in a new tab
- **Removed** the mock "Save case" and "Save evidence entry" forms —
  there's no backend for police to create/edit cases or evidence
  (per the README, police only receive verified evidence; they don't
  originate it), so those forms had nothing to submit to. Flagging
  this rather than wiring them to something that doesn't exist.

### zicta-dashboard.html
- Stats: Total cases / Open cases / Analysts on roster, from
  `zicta/dashboard.php`
- "Cases by Category" table from `zicta/analytics.php`
- "Analyst Roster" table from `zicta/dashboard.php`'s `analysts` field
- "Audit Trail" table from `zicta/audit.php`'s `status_updates`
- **Removed** the mock "SIM Card Blocking" and "Website Takedown"
  sections — there's no schema support for either (no `sim_blocks` or
  `takedowns` tables exist), so this was pure mockup UI with nothing
  behind it. If that's a feature you actually want, it needs its own
  schema + endpoints — flagging rather than faking it.

### ⚠️ Not verified against a live server this round

Every previous release in this log was tested end-to-end against a
real MariaDB instance before being sent. This one wasn't — the sandbox's
background-process handling became unreliable partway through (starting
`php -S` in the background repeatedly hung the tool), so this round
relied on: (1) re-reading each backend endpoint's actual field names
immediately before writing the matching JS, and (2) reusing the exact
fetch/render pattern already proven working in the 1.4.0 analyst/victim
dashboards. `php -l` and `node -c` both pass on everything new. Worth
an extra look when you test locally, since it hasn't had the same live
scrutiny as the rest.

--------

## [1.4.0] - 2026-07-21 - Analyst Dashboard: LADINA Console

### Summary

`dashboard.html` is now the analyst's working view of LADINA — the
console for monitoring automatic evidence analysis and stepping in by
hand when it fails or a case needs a closer look. Fully tested end-to-
end against a real MariaDB instance (register → login → view cases →
upload evidence → attempt analysis → update status), not just linted.

### Changed

#### analyst/upload_evidence.php
- LADINA now runs automatically right after a file is saved. Never
  blocks the upload response — if `GEMINI_API_KEY` isn't configured or
  the run fails, the upload still succeeds and reports
  `analysis_status: not_configured|failed|completed`

#### analyst/dashboard.php
- GET now also returns `ladina_configured` (whether the server has
  `GEMINI_API_KEY` set), `stats` (pending/investigating/closed counts),
  and per-evidence `has_analysis` + parsed `analysis` fields
- Manual `analyze_evidence` action now distinguishes "not configured"
  (502, clear message) from "ran and failed" (502, different message)
  so an analyst knows which one they're looking at

#### login.html
- Victim redirect changed from `dashboard.html` to the new
  `victim-dashboard.html` — `dashboard.html` is analyst-only now

### New Files

#### includes/ladina_runner.php
- `runLadinaAnalysis()` and `ladinaIsConfigured()` extracted out of
  `analyst/dashboard.php` so the auto-run-on-upload path and the manual
  re-run action share one implementation instead of two

#### dashboard.html (rebuilt)
- Case table, per-case evidence review, LADINA status stat card,
  status-update form, and a Run/Re-run button per evidence item.
  Auto-refreshes every 30s. Built entirely from the `dashboard-*`
  classes already sitting unused in style.css (`.stats-grid`,
  `.data-table`, `.status-badge`, etc.) — no new global CSS

#### victim-dashboard.html
- Victim's own case tracker, since `dashboard.html` no longer serves
  that role. Uses the same `dashboard-*` classes for visual consistency

#### assets/js/ajax-poll.js
- Implemented for real: polls `victim/dashboard.php` every 30s and
  renders `victim-dashboard.html`'s table/stat cards

### Verified locally (not just linted)

Ran a full flow against a fresh MariaDB import of `zatcher_db.sql` +
`migration_001_add_roles.sql`: analyst login → GET dashboard → status
update → evidence upload (auto-analysis correctly reports
`not_configured` without `GEMINI_API_KEY`) → manual re-run correctly
502s with a clear diagnostic message → victim register/login/dashboard
returns their own empty case list. All PHP passes `php -l`, all inline
JS passes `node -c`.

--------

## [1.3.1] - 2026-07-21 - Frontend Wiring Fix (login.html / register.html)

### Summary

Local dev-server testing (`php -S localhost:8000`) surfaced the exact gap
flagged in 1.3.0: submitting `login.html` produced
`GET /login.html?fullName=...&password=...` instead of hitting the backend.
Root cause: the page's script block declared `const form` and `const
message` twice in the same scope, which is a `SyntaxError` at parse time —
the whole script silently failed to load, so nothing intercepted the
submit and the browser fell back to its default GET-to-self.

### Fixed

#### login.html
- Removed the broken duplicate-`const` script (it also contained dead
  code referencing nonexistent `#loginForm`/`#username` elements with
  hardcoded demo passwords)
- Login field changed from "Full name" to "Email address" — the backend
  authenticates by email (`auth/login.php`), and full name isn't unique
  in the schema
- Added a real `fetch()` POST to `auth/login.php`; on success, redirects
  based on the `role` the backend returns (victim/analyst →
  `dashboard.html`, police → `police-dashboard.html`, zicta →
  `zicta-dashboard.html`) — analyst has no dedicated page yet, so it
  shares the generic one for now

#### assets/js/validate.js
- Kept all existing client-side validation as-is; once it passes, now
  actually submits to `auth/register.php` via `fetch()` instead of just
  calling `form.reset()`
- The role `<select>` already in `register.html` (Victim/Analyst/
  Police/Administrator) is sent along; "Administrator" is translated to
  the backend's `zicta` role at submit time — no HTML changes needed

#### auth/register.php
- Now reads and whitelists the submitted `role` against
  `victim/analyst/police/zicta`, defaulting to `victim` if missing or
  invalid, instead of ignoring the field entirely

### ⚠️ Security note (not fixed, flagging for the team)

`register.html` lets anyone self-register as `analyst`, `police`, or
`zicta` — there's no verification step. The README describes ZICTA as
the body that authorizes analysts, which implies those roles shouldn't
be self-service. Left as-is since it's an existing frontend design
decision, not something to silently override, but worth a team
conversation before this goes anywhere real.

--------

## [1.3.0] - 2026-07-21 - Component Join Layer (RBAC + Role Dashboards + LADINA hook)

### Summary

Wired the previously-empty stub files into a working backend, without
touching any existing design/markup or the file layout. The core problem
this closes: every role dashboard (`victim/`, `analyst/`, `police/`,
`zicta/`) existed as an empty `.php` file with no shared way to check who's
allowed to call it, and the schema's `users.role` enum (`user`/`admin`)
couldn't represent the four roles the README describes.

--------

### New Files Created

#### includes/auth_guard.php
- `requireLogin()` / `requireRole($roles)` — single shared RBAC gate,
  used by every role-protected endpoint instead of each folder
  re-implementing its own session check
#### models/User.php, models/Evidence.php
- Filled in the two model stubs, matching the existing `Incident` model's
  style (constructor-injected PDO, prepared statements)
#### auth/logout.php
- Destroys the session and its cookie
#### victim/dashboard.php
- JSON case list for the logged-in victim (feeds `ajax-poll.js`)
#### analyst/dashboard.php
- Lists open incidents + evidence; POST actions for `update_status`
  (logs to `incident_updates`) and `analyze_evidence` (shells out to
  `ai/ZatcherAnalyzer.py`, stores the result on the evidence row)
#### police/dashboard.php, police/evidence.php, police/export_xml.php
- Case summary, verified-evidence viewer, and `evidence_report.xml` export
  via `SimpleXMLElement`
#### zicta/dashboard.php, zicta/analytics.php, zicta/audit.php
- Platform totals + analyst roster, category/status/month breakdowns for
  `charts.js`, and a status-update/comment audit trail
#### schema/migration_001_add_roles.sql
- The one unavoidable schema change: widens `users.role` from
  `enum('user','admin')` to `enum('victim','analyst','police','zicta')`
  so the RBAC guard has real roles to check against. Additive only —
  remaps existing rows to `victim`, seeds one demo account per
  back-office role.

### Modified Files

#### auth/register.php
- Self-registration now inserts role `'victim'` instead of the generic
  `'user'`, matching the new enum

--------

### Still Not Wired

- `login.html` / `register.html` still don't submit to the backend —
  their form field names don't match `auth/login.php` /
  `auth/register.php`, and there's no `fetch()` call. That's a frontend
  markup/JS change, deliberately left untouched here.
- `assets/js/ajax-poll.js` and `assets/js/charts.js` are still empty —
  the placeholder dashboards (`dashboard.html`, `police-dashboard.html`,
  `zicta-dashboard.html`) have no data containers to bind to yet.
- LADINA analysis requires `GEMINI_API_KEY` set in the PHP process's
  environment; `analyze_evidence` degrades gracefully (502, not a crash)
  if it isn't configured.

--------

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
 

