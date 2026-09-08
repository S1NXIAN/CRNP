# CODING STANDARDS — CRATES N' PLATES

Companion to `CONVENTIONS.md` (app patterns: bootstrap, guards, helpers).
This file is the quality gate: style, types, and review rules.
Tooling enforces what it can (`make lint`, `make analyse`); the rest is on the author.

## 1. Style (enforced by php-cs-fixer)

- PSR-12 with K&R braces (opening brace same line everywhere, incl. functions).
  Config: `.php-cs-fixer.dist.php`. Never hand-format; run `make format`.
- 4 spaces, single quotes unless interpolating, short arrays `[]`, LF, newline at EOF.
- `make lint` must pass before commit. It covers all PHP (no vendored code remains).
- K&R braces everywhere is a deliberate deviation from PER (which wants newline braces on classes/methods); `.php-cs-fixer.dist.php` enforces K&R. NEVER reformat braces toward PER.

## 2. Types (enforced by phpstan level 4)

- Scope: `app/`, `includes/`, all role pages, `config.php`, `db.php`,
  `firebaseRDB.php`, `mailer.php`, `init.php`. PHP 8.2 target.
- New classes and functions: full param + return types, no untyped `$x`.
- New class/test files: `declare(strict_types=1)` first statement. Page files
  (mixed HTML) exempt.
- Page-file exemption is deliberate and pinned by the fixer (`declare_strict_types => false`); strictness in pages comes from phpstan, not the declaration.
- Prefer precise types over `array` for domain shapes at new boundaries;
  do not retrofit the existing Firebase row-arrays.
- Zero new phpstan errors: compare against the baseline before pushing.
  (`Model::find` magic getters and page superglobals are known noise.)
- New domain concepts at new boundaries SHOULD be Value Objects (a
  `readonly` class validating its invariants in the constructor) instead
  of bare primitives; Firebase `array` row shapes stay as-is (no retrofit).
- `void` marks procedures with side effects only; `never` marks methods
  that always throw or exit.

## 3. Naming

- `camelCase` methods/vars, `snake_case` procedural helpers, `PascalCase`
  classes, `SCREAMING_SNAKE_CASE` constants (`GMAIL_ADDRESS`, `MAIL_FROM`).
- Env vars carry a service prefix (`FIREBASE_*`, `GMAIL_*`, `MAIL_*`, `DEV_*`).
  A bare name (`SMTP_PASS`, `DEV_MODE`) is a rename candidate.
- Booleans read as questions: `isPaid`, `hasReceipt`, `canRefund`.
- No noise words (`data`, `info`, `tmp`), no sequential names, plurals for
  collections. Functions describe their result (`pendingCount()`, `selectNew()`).
- Classes are nouns (`OrderQueue`); methods and functions start with a
  verb (`renderRows()`, `calculateTotal()`).
- Pair operations symmetrically: `get/set`, `create/destroy`,
  `open/close`, `start/stop`, `minimum/maximum`, `next/previous`.

## 4. Structure

- Nesting ≤ 3, cyclomatic complexity ≤ 10. Guard-clause early returns;
  the happy path stays flat. No single-exit-point gymnastics.
- One POST block per page: `post()` → validate → Firebase in `try` →
  `flash()` + `redirect()`. Never render after a successful mutation.
- Shared markup used twice (initial render + poll/AJAX) lives in exactly one
  renderer function (`cashier_order_row()` pattern), never copy-pasted.
- Pure logic (filtering, sorting, capping) goes in a side-effect-free static
  with an offline test in `tests/` wired into `make smoke` (`selectNew` pattern).
- Smallest scope: declare each variable where it is used. Files SHOULD
  stay within 300-500 lines; larger files MUST split into submodules.
- Compose, never step-mutate: compute parts into `const`s, combine once
  (`$totalPay = $salary - $taxes;`), never repeated `+=`/`-=` on one var.
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
- No path versioning, envelope pagination, or idempotency-key headers: these are internal same-app endpoints, not a public API. A future public API MUST version paths (`/v1/`) and revisit this section.

## 8. Comments

- Why, not what. No authors/dates/tickets, no dead code, no redundant narration.
- A `ponytail:` comment marks a deliberate shortcut with its ceiling and
  upgrade path (`# ponytail: 20-row cap, cursor paging past diner scale`).
- C1-C5: no authors/dates/tickets (Git owns them); delete obsolete
  comments with the logic change; never restate the code
  (`$i += 1; // increment` NEVER); grammar and spelling MUST be correct.
- Public PHP boundaries (classes, shared helpers) MUST carry a PHPDoc
  block: intent, `@param`, `@return`, `@throws`.
- Outstanding work uses one searchable form:
  `// TODO: (<name>) <task> past <ceiling>` or `// FIXME: <symptom>`.

## 9. Commits

- Lowercase Conventional Commits with scope, imperative ≤ 50-char subject,
  blank line, body explaining *why*: `feat(cashier): inject new orders live`.
- One concern per commit. Green suite: `make lint && make smoke`
  (plus `make analyse` for PHP changes).
- Subject hard ceiling is 72 chars; no trailing period; body lines wrap
  at 72 chars.
- Types: `feat` (minor), `fix` (patch), `docs`, `style`, `refactor`,
  `perf`, `test`, `build`/`ci`, `chore`, `revert`.
- Breaking changes MUST use `!` after type/scope (`feat(billing)!: ...`)
  plus a `BREAKING CHANGE:` footer.

## 10. Contracts & substitutability

- Shared PHP helpers and `Model` subclasses MUST document preconditions
  (what the caller guarantees) and postconditions (what success returns).
  A violated precondition MUST throw immediately; never return fake rows.
- Class invariants (stock counts, status transitions) MUST hold before
  and after every public call.
- LSP: overrides MUST accept at least the parent's inputs, return at most
  the parent's outputs, and preserve every parent invariant.

## 11. Shell scripts

- Default is POSIX `sh`: keep the existing `#!/bin/sh` + `[ ]` style and
  NEVER mix `[[ ]]` into an `sh` file. New Bash-only scripts MUST use
  `#!/usr/bin/env bash` as the first line.
- Existing scripts stay POSIX sh; NEVER rewrite them to Bash for style alone.
- Bash files MUST start with `set -euo pipefail`; `sh` files SHOULD carry
  `set -eu` unless `set -e` would break an expected-nonzero flow.
- Quote every expansion (`"$var"`, `"$(cmd)"`). Errors go to stderr
  (`echo "[name] msg" >&2`) with a non-zero exit. New Bash files MUST be
  shellcheck-clean.

## 12. Frontend JS (vanilla, no framework)

- New modules MUST run in strict mode (`"use strict";` or ESM). Existing
  classic-script IIFE style stays until touched.
- NEVER mutate built-in prototypes (`Array.prototype`, ...). Prefer
  `map`/`filter`/`reduce` over in-place index mutation.
- Bindings covering server-injected nodes MUST use delegated `document`
  listeners (see §7). Shared JS helpers SHOULD carry JSDoc
  `@param`/`@returns`.
