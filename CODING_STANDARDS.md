# CODING STANDARDS — CRATES N' PLATES

Companion to `CONVENTIONS.md` (app patterns: bootstrap, guards, helpers).
This file is the quality gate: style, types, and review rules.
Tooling enforces what it can (`make lint`, `make analyse`); the rest is on the author.

## 1. Style (enforced by php-cs-fixer)

- PSR-12 with K&R braces (opening brace same line everywhere, incl. functions).
  Config: `.php-cs-fixer.dist.php`. Never hand-format; run `make format`.
- 4 spaces, single quotes unless interpolating, short arrays `[]`, LF, newline at EOF.
- `make lint` must pass before commit. It covers all PHP (no vendored code remains).

## 2. Types (enforced by phpstan level 4)

- Scope: `app/`, `includes/`, all role pages, `config.php`, `db.php`,
  `firebaseRDB.php`, `mailer.php`, `init.php`. PHP 8.2 target.
- New classes and functions: full param + return types, no untyped `$x`.
- New class/test files: `declare(strict_types=1)` first statement. Page files
  (mixed HTML) exempt.
- Prefer precise types over `array` for domain shapes at new boundaries;
  do not retrofit the existing Firebase row-arrays.
- Zero new phpstan errors: compare against the baseline before pushing.
  (`Model::find` magic getters and page superglobals are known noise.)

## 3. Naming

- `camelCase` methods/vars, `snake_case` procedural helpers, `PascalCase`
  classes, `SCREAMING_SNAKE_CASE` constants (`GMAIL_ADDRESS`, `MAIL_FROM`).
- Env vars carry a service prefix (`FIREBASE_*`, `GMAIL_*`, `MAIL_*`, `DEV_*`).
  A bare name (`SMTP_PASS`, `DEV_MODE`) is a rename candidate.
- Booleans read as questions: `isPaid`, `hasReceipt`, `canRefund`.
- No noise words (`data`, `info`, `tmp`), no sequential names, plurals for
  collections. Functions describe their result (`pendingCount()`, `selectNew()`).

## 4. Structure

- Nesting ≤ 3, cyclomatic complexity ≤ 10. Guard-clause early returns;
  the happy path stays flat. No single-exit-point gymnastics.
- One POST block per page: `post()` → validate → Firebase in `try` →
  `flash()` + `redirect()`. Never render after a successful mutation.
- Shared markup used twice (initial render + poll/AJAX) lives in exactly one
  renderer function (`cashier_order_row()` pattern), never copy-pasted.
- Pure logic (filtering, sorting, capping) goes in a side-effect-free static
  with an offline test in `tests/` wired into `make smoke` (`selectNew` pattern).
- Never auto-reload a working user's page. Notify (toast), let them refresh,
  or inject rows without touching their inputs.

## 5. Data layer

- List pages query server-side only: `Model::where / whereAny / whereRange /
  recentBy`. PHP-side `filter_by / filter_like` refines, never scans whole tables.
- New `orderBy` field ⇒ add `.indexOn` in `database.rules.json` **and** the
  `INDEX_COVERAGE` map in `tests/indexed_rules.php` in the same commit.
- Firebase `{"error"}` responses throw; cURL failures log with a
  `[firebaseRDB]` prefix. Never swallow, never return fake rows.
- Stock: decrement after successful write, restore by Firebase key, check
  status first (see `CONVENTIONS.md`).

## 6. Security (non-negotiable, never simplified away)

- `e()` every interpolated value. `csrf_field()` in every form,
  `csrf_verify()` on every POST. Role guard before any output.
- Passwords: `password_hash` / `password_verify` (bcrypt) only.
- Uploads via `save_upload()` into categorized dirs only; never trust filenames.
- OTPs and secrets never reach the browser unless `DEV_SHOW_OTP=1` (local only).
- New env var ⇒ one-line entries in `.env.example`, `render.yaml`, and the
  README table, same commit. Secrets stay out of code and logs.

## 7. JSON endpoints (polls, AJAX)

- Small internal endpoints return a named-key object (`{"pending": N, "rows": [...]}`),
  never a bare array. Cast/validate every `$_GET`/`$_POST` input at the top.
- Keep the payload minimal: counts and rendered fragments over full tables.
- Injected HTML is server-rendered (tokens valid); client bindings that must
  cover injected nodes use delegated `document` listeners.

## 8. Comments

- Why, not what. No authors/dates/tickets, no dead code, no redundant narration.
- A `ponytail:` comment marks a deliberate shortcut with its ceiling and
  upgrade path (`# ponytail: 20-row cap, cursor paging past diner scale`).

## 9. Commits

- Lowercase Conventional Commits with scope, imperative ≤ 50-char subject,
  blank line, body explaining *why*: `feat(cashier): inject new orders live`.
- Validate: `python3 commit_msg_validator.py <file>`.
- One concern per commit. Green suite: `make lint && make smoke`
  (plus `make analyse` for PHP changes).
