# CODING_STANDARD.md

Repository coding standards for human developers and AI coding assistants. Each rule here is a hard constraint (MUST/NEVER), a scoped heuristic, or a pointer to tooling. Style already enforced by the repo's formatter or linter is deliberately not restated.

## 1. Complexity & Structure

*   **Numeric limits belong to tooling.** Nesting depth, cyclomatic complexity, and file length are configured in the repo's linter — run it instead of hand-measuring.
*   **Decompose at cohesion boundaries.** Split a function that chains unrelated concerns or narrates its flow with comments; split a file whose sections stop belonging together. Do not split working code to satisfy a line count.
*   **Small scopes:** define and initialize variables in the smallest scope that needs them.

---

## 2. Linguistic Architecture (Naming)

### 2.1 Typographic Rules & Casing
Casing is per-language. The generated document's Section 8 carries the casing table for the detected stack; the formatter/linter enforces it.

### 2.2 Grammatical Mapping
*   **Classes, types, and modules:** nouns or noun phrases (e.g., `UserAccount`, `PaymentProcessor`). Verb names are acceptable for single-action types (e.g., `Serializer`, `Migrator`, `Validator`).
*   **Methods and functions:** start with a verb naming the action performed (e.g., `getActiveUsers()`, `calculateTax()`).
*   **Booleans:** read as a predicate — an `is/has/can` prefix or an equally clear predicate (e.g., `is_active`, `has_permission`, `allowed`, `ready`).

### 2.3 Semantic Precision
*   **Intent over implementation:** names describe what an entity represents, not how it is built.
    *   ❌ `Poor:` `intList` or `doubleValue`
    *   ✅ `Good:` `flaggedCells` or `monthlyTotal`
*   Avoid noise words that carry no context (e.g., `data`, `info`, `process`, `temp`) and numbered names (e.g., `user1`, `user2`).
*   Singular for single values, plural for collections (e.g., `user`, `scores`).
*   Paired operations read as pairs (e.g., `open/close`, `start/stop`, `next/previous`) — a preference, not a rename mandate.

---

## 3. Structural Design & Flow Control

*   **Guard clauses:** validate boundary inputs and raise at the top of the function; keep the happy path flat and un-nested.
*   **Composition over straight-line step mutation:** do not push one variable through successive reassignment — compute the components, compose once.
    ```typescript
    // ❌ Step mutation
    let total = 0;
    total += getSalary();
    total -= getTaxes();

    // ✅ Composition
    const totalPay = getSalary() - getTaxes();
    ```
    Accumulating in a loop or reducer (`total += item`) is idiomatic and exempt.
*   **No silent fallbacks (hard constraint):**
    *   Invalid states, missing config, or failed operations MUST throw an error, NEVER silently fall back to a default, empty value, or alternate path.
    *   Fallback behavior (defaults, downgrade retries, catch-and-continue) MUST appear only when explicitly requested.

---

## 4. Type Systems as Living Documentation

*   **Wrap primitives only where they lie.** Use value objects or branded types when a raw scalar admits invalid states or unit confusion (money as `float`, user id vs. order id). Plain `string`/`int` is fine for plainly valid values — do not wrap every parameter.
*   **Static nominal boundaries, zero runtime cost:** TypeScript branded types, Python `NewType`, PHP immutable value objects — examples in Section 8.
*   **Plain JavaScript:** JSDoc `@typedef` annotations plus `// @ts-check` (or `checkJs`) for autocomplete and static checking.

---

## 5. API Contracts & Substitutability

*   **Validate where inputs enter.** Preconditions are checked at the API boundary; callers must not depend on undocumented states.
*   **Honor the parent contract.** An implementation must not narrow the inputs a caller may pass or weaken the guarantees a caller may rely on behind an existing interface.

---

## 6. Comment & Documentation Hygiene

Comments explain **why**, never **how**; the best code needs the fewest.

*   No redundant comments that mechanically repeat the code:
    *   ❌ `Bad:` `i += 1; // Increment i by 1`
    *   ✅ `Good:` `i += 1; // Offset for zero-indexed display arrays`
*   Update or delete comments when the logic they describe changes — a stale comment is worse than none.
*   NEVER commit commented-out code; Git remembers everything.
*   Git owns authorship and history — no author, ticket, or date metadata in comments.
*   Public API boundaries carry doc comments (JSDoc, PHPDoc, docstrings) for intent, parameters, return values, and thrown errors — when behavior is not already obvious from the signature.
*   TODOs use a searchable `TODO:` prefix with actionable text; include an assignee only when the repo already does.

---

## 7. Commit Messages

*   Commits follow **Conventional Commits**: `<type>(<scope>): <imperative summary>`, with a body that explains **why**, not how. Where the repo provides a commit skill, hook, or commitlint config, that configuration is authoritative.
*   Breaking changes MUST be declared — `!` after the type/scope, or a `BREAKING CHANGE:` footer describing the migration path.

---

## 8. Language-Specific Standards

### PHP / Laravel 8.2+
Gold standards fetched: 2026-09-22

**Types & strictness**
- `declare(strict_types=1)` in every file. — https://www.php.net/manual/en/language.types.declarations.php · 2026-09-22
- Prefer `===`/`!==`; never rely on type juggling. — https://www.php.net/manual/en/language.types.type-juggling.php · 2026-09-22
- Model fixed domain value sets as `enum`. — https://www.php.net/manual/en/language.enumerations.overview.php · 2026-09-22

**Blade output safety**
- [HARD] All user-supplied data is emitted via `{{ }}`; `{!! !!}` is XSS. — https://laravel.com/docs/12.x/blade · 2026-09-22
- [HARD] Never interpolate `$data` elements directly into a component `render()` Blade string — template injection → RCE. — https://laravel.com/docs/12.x/blade · 2026-09-22
- Use `Js::from()` instead of `json_encode`, only for already-existing view variables; avoid `__DIR__`/`__FILE__` in Blade views. — https://laravel.com/docs/12.x/blade · 2026-09-22
- Escaping escape-hatches only: `@verbatim`, `@{{ }}`, `Blade::withoutDoubleEncoding()`. — https://laravel.com/docs/12.x/blade · 2026-09-22

**Validation & boundaries**
- [HARD] Validate at the request boundary before any processing, then consume only `$request->validated()` — never raw `all()`. — https://laravel.com/docs/12.x/validation · 2026-09-22
- [HARD] Declare allowed array keys explicitly; unbounded input arrays are rejected. — https://laravel.com/docs/12.x/validation · 2026-09-22
- A `regex` containing `|` is expressed as an array of rules. — https://laravel.com/docs/12.x/validation · 2026-09-22
- `date` and `date_format` are mutually exclusive (XOR). — https://laravel.com/docs/12.x/validation · 2026-09-22

**Structure & autoloading**
- [HARD] A file declares symbols or side effects, not both. — https://www.php-fig.org/psr/psr-1/ · 2026-09-22
- [HARD] One class per file, in a namespace of at least the vendor level. — https://www.php-fig.org/psr/psr-1/ · 2026-09-22
- [HARD] FQCN carries a vendor namespace; class references are case-sensitive; directory/file case matches namespace/class case. — https://www.php-fig.org/psr/psr-4/ · 2026-09-22
- Controllers, middleware, and form requests live in `app/Http`; no application logic in `Console/` or `Http/` beyond orchestration. — https://laravel.com/docs/12.x/structure · 2026-09-22
- Default-valued parameters go last in the argument list; intentional `switch` case fall-through carries `// no break`. — https://www.php-fig.org/psr/psr-12/ · 2026-09-22

**HTTP clients & logging**
- [HARD] A well-formed 4xx/5xx response is a result, not an error — never throw. Throw `ClientExceptionInterface` only for send/parse failure, `RequestExceptionInterface` for a malformed request, `NetworkExceptionInterface` for network failure. — https://www.php-fig.org/psr/psr-18/ · 2026-09-22
- [HARD] Log placeholder `{name}` matches a context key; exceptions are passed under the `exception` key. — https://www.php-fig.org/psr/psr-3/ · 2026-09-22
- Never pre-escape placeholder values; context construction must not throw or warn. — https://www.php-fig.org/psr/psr-3/ · 2026-09-22

**Queues**
- [HARD] Async jobs implement `ShouldQueue`. — https://laravel.com/docs/12.x/queues · 2026-09-22
- [HARD] Jobs unique across multiple servers use a shared central cache. — https://laravel.com/docs/12.x/queues · 2026-09-22
- `base64_encode` binary job payloads; `ShouldBeUnique` for non-concurrent work; `ShouldBeEncrypted` for sensitive payloads. — https://laravel.com/docs/12.x/queues · 2026-09-22

**Tests**
- Test classes are suffixed `Test`; `tests/Unit` never boots the app; overridden `setUp`/`tearDown` call `parent::` first/last. — https://laravel.com/docs/12.x/testing · 2026-09-22

**Tooling pointers**
- Pint owns PSR-12 formatting, identifier casing, and numeric/complexity limits (config, not prose).
- Static analysis: PHPStan via **larastan**. Tests: Pest (`vendor/bin/pest`, `--parallel`, `--coverage`).
- Blade escaping tools: `@verbatim`, `@{{ }}`.

**Deliberately out of scope**
- Eloquent mass-assignment rules — this repo has no Eloquent/SQL (Firebase RTDB persistence); over-posting/privilege escalation is covered by the validation rules above (explicit `validated()` + allowed keys).
- PSR-3 unknown-log-level-throws and PSR-4 autoloader-implementer contracts — implementer-side, owned by Laravel/Monolog and Composer respectively.
