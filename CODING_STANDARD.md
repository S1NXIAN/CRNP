# CODING_STANDARD.md

This document establishes the repository-level coding standards and quality gates for human developers and AI coding assistants. 

## 1. Code Complexity & Cognitive Budget

Writing functional code is only half the battle; maintaining readability, simplicity, and low cognitive overhead is the standard for professional engineering.

### 1.1 Cognitive vs. Algorithmic Complexity
*   **Algorithmic Complexity (Big-O):** Quantifies mathematical runtime and storage requirements as input sizes scale.
*   **Code Complexity:** Measures the cognitive effort required for a human developer to read, maintain, and reason about code. Because developers spend up to **70% of their time reading and parsing code** to reconstruct intent, we actively optimize for minimal cognitive load.
*   **AI Density Guardrail:** To combat the quality degradation of automated "vibe coding" (which research shows increases code duplication by **4x** and code complexity by **41%**), all modifications must be rigorously reviewed for structural cohesion and formatting consistency.

### 1.2 Structural Rules
*   **Nesting Limit:** Nesting must not exceed **3 levels**. Deeply nested conditional structures represent a failure of logical decomposition.
*   **Cyclomatic Complexity Limit:** No single function may exceed a cyclomatic complexity score of **10** (measured by branches/paths).
*   **Small Scopes:** Define and initialize variables in the smallest scope possible. Keep file sizes capped at **300–500 lines**; files exceeding this must be decomposed into logical submodules.

---

## 2. Linguistic Architecture (Naming Conventions)

Since code primarily consists of custom names, naming is our most powerful abstraction tool. Consistent, descriptive, and unambiguous names act as a self-documenting guide.

### 2.1 Typographic Rules & Casing
Casing is per-language. The generated document's Section 8 carries the casing table for the detected stack.

### 2.2 Grammatical Mapping
*   **Classes and Objects:** Must be nouns or noun phrases (e.g., `UserAccount`, `PaymentProcessor`). Never use verbs.
*   **Methods and Functions:** Must start with a verb or verb phrase representing the action performed (e.g., `getActiveUsers()`, `calculateTax()`).
*   **Boolean Variables and Functions:** Must carry a distinct prefix indicating an interrogative state (e.g., `is/has/can` such as `is_active`, `has_permission`, `can_write`).

### 2.3 Semantic Hygiene
*   **Intent Over Implementation:** Names must describe *why* an entity exists and *what* it represents semantically, not how it is implemented. 
    *   ❌ `Poor:` `intList` or `doubleValue`
    *   ✅ `Good:` `flaggedCells` or `monthlyTotal`
*   **Differentiate with Meaning:** Avoid vague noise words that add zero context (e.g., `data`, `info`, `process`, `temp`). Avoid naming variables sequentially (e.g., `user1`, `user2`).
*   **Singular vs. Plural:** Single values must use singular nouns; collections (arrays, lists, sets) must use pluralized names (e.g., `scores`, `users`). Use collective nouns where appropriate (e.g., `calendar` instead of `appointments`).
*   **Precise Word Pairs (Antonyms):** Always pair operations symmetrically:
    *   `get / set` | `create / destroy` | `open / close` | `start / stop` | `minimum / maximum` | `next / previous`

---

## 3. Structural Design & Flow Control

Our architectural goal is to write \"sentence-like\" linear code that reads cleanly like prose.

### 3.1 Flattening Nesting via Guard Clauses
Eliminate deeply nested `if-else` blocks and "train wreck" conditional structures by applying the **Guard Clause (Early Return) Pattern**.
Validate boundary inputs and execute exception handlers immediately at the top of the function, leaving the happy path flat and un-nested.

```typescript
// ❌ Bad (Nested "If-Else" Hell)
async function registerUser(user: UserSpec) {
  if (validateInput(user)) {
    const existing = await findUser(user.email);
    if (!existing) {
      const hash = await hashPassword(user.password);
      return saveUser({ ...user, password: hash });
    } else {
      throw new Error("User exists");
    }
  } else {
    throw new Error("Invalid input");
  }
}

// ✅ Better (Early Returns / Guard Clauses)
async function registerUser(user: UserSpec) {
  if (!validateInput(user)) throw new Error("Invalid input");
  
  const existing = await findUser(user.email);
  if (existing) throw new Error("User exists");

  const hash = await hashPassword(user.password);
  return saveUser({ ...user, password: hash });
}
```

### 3.2 Composition Over Step Mutation
Within functions, do not repeatedly mutate or reassign the same variable over successive steps. Instead, compute distinct components and compose them cleanly in a single, final step.

```typescript
// ❌ Bad (Step Mutation)
let total = 0;
total += getSalary();
total -= getTaxes();

// ✅ Better (Single-Step Composition)
const salary = getSalary();
const taxes = getTaxes();
const totalPay = salary - taxes;
```

### 3.3 No Silent Fallbacks
*   **Fail loudly:** invalid states, missing config, or failed operations MUST throw an error, NEVER silently fall back to a default, empty value, or alternate path.
*   Fallback behavior (default values, retries with downgrade, catch-and-continue) MUST only be added when explicitly requested.

---

## 4. Type Systems as Living Documentation

Primitives (strings, numbers) are weak boundaries that allow logic bugs to slip past. We utilize modern type features to declare invariants programmatically.

### 4.1 Eradicating Primitive Obsession
Do not pass raw primitives to model highly specialized business concepts. Wrap them in dedicated, immutable **Value Objects** that guarantee data validity inside their constructors.

```typescript
// ❌ Bad (Primitive Obsession)
function shipPackage(weight: number) { ... } // What unit is this? Grams? Pounds?

// ✅ Better (Value Object)
class Weight {
  private constructor(public readonly grams: number) {}
  
  static fromKilograms(kg: number): Weight {
    if (kg <= 0) throw new ArgumentError("Weight must be positive");
    return new Weight(kg * 1000);
  }
}
function shipPackage(weight: Weight) { ... }
```

### 4.2 Compile-Time Nominal Type Checking
Leverage language features to establish strict, compile-time nominal boundaries without runtime performance costs:
*   **TypeScript Branded Types:** Ensure type safety by attaching unique symbols.
*   **Python NewType:** Create helper subtypes that type checkers (such as `mypy`) validate statically.

### 4.3 JSDoc Static Typing (Pure JS Environments)
If working in plain JavaScript, enable IDE autocomplete and nominal typechecking by writing comprehensive JSDoc annotations and enabling static JS checks:

```javascript
/** @typedef {{ id: number, email: string, role: string }} User */

/**
 * Upgrades a validated user's system role.
 * @param {User} user - The system user
 * @param {string} newRole - The role to assign
 * @returns {Promise<User>} The updated user object
 */
async function upgradeRole(user, newRole) { ... }
```

---

## 5. API Contracts & Liskov Substitutability

An API is a binding contract between caller and implementation. We apply the **Design by Contract (DbC)** framework to secure system boundaries.

### 5.1 Preconditions, Postconditions, and Invariants
*   **Preconditions:** Explicit requirements that the caller *must* satisfy before executing a method. If violated, the method is not obligated to execute correctly.
*   **Postconditions:** Absolute guarantees that the method *must* satisfy upon successful execution, provided preconditions were met.
*   **Class Invariants:** Global state constraints that must remain completely undisturbed before and after any public execution loop.

### 5.2 The Liskov Substitution Principle (LSP)
Any subclass or implementation of an interface must be completely substitutable for its parent type without breaking client flow.
1.  **Preconditions Cannot Be Strengthened:** Subclasses must accept *at least* the same range of inputs as the parent.
2.  **Postconditions Cannot Be Weakened:** Subclasses must return *at most* the same range of outputs and preserve all parent guarantees.
3.  **Invariants Must Be Preserved:** Class state invariants established by parent types must remain fully unbroken.

---

## 6. Comment & Documentation Hygiene

Code communicates implementation (the \"how\"), whereas comments are strictly reserved for communicating business intent and trade-offs (the \"why\"). The best code requires minimal comments because its names and structure are self-explanatory.

### 6.1 The C1–C5 Quality Rules
To prevent context pollution and documentation rot, apply strict hygiene to all commentary:
*   **C1: No Inappropriate Information:** Never include administrative metadata (such as authors, ticket numbers, revision histories, or dates) in comments. Version control (Git) owns this information.
*   **C2: Delete Obsolete Comments:** Outdated comments that describe altered or deleted logic are dangerous misdirections. Comments must be updated or deleted immediately when logic changes.
*   **C3: No Redundant Comments:** Never write comments that mechanically repeat what the code obviously shows.
    *   ❌ `Bad:` `i += 1; // Increment i by 1`
    *   ✅ `Good:` `i += 1; // Offset for zero-indexed display arrays`
*   **C4: Write Comments Well:** Write clearly, concisely, and with correct grammar and spelling.
*   **C5: Never Commit Commented-Out Code (Zombie Code):** Commented-out blocks create visual noise and cognitive friction. If code is unused, delete it; Git remembers everything.

### 6.2 Docstring Formats (Public APIs)
Public API boundaries must carry structured documentation comments to generate auto-documentation (e.g., JSDoc, Javadoc, docstrings, or Doxygen):

```typescript
/**
 * Processes an order refund through our external payment provider.
 * 
 * Assumes order status is already validated as 'delivered'.
 * See Stripe API RFC-3986 for billing structures.
 * 
 * @param {string} orderId - Canonical ID of the order
 * @param {number} amount - Non-negative refund value
 * @returns {Promise<string>} Stripe transaction confirmation reference
 * @throws {PaymentException} If payment gateway is unreachable
 */
async function processRefund(orderId: string, amount: number): Promise<string> { ... }
```

### 6.3 Standardized TODO Comments
Use a structured, searchable format to flag outstanding tasks:
```typescript
// TODO: (@David) Implement a sliding-window algorithm to handle dataset sizes > 1M
// FIXME: Handle memory leakage occurring on rapid socket reconnection events
```

---

## 7. Commit Message Standards (Conventional Commits)

To maintain a highly readable, structured, and machine-parsable commit history, all changes checked into the repository must adhere to the **Conventional Commits 1.0.0 Specification** and the industry-standard **Seven Rules of Git Commits**.

### 7.1 Commit Message Structure
A standard commit message must follow this structural layout:
```text
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

### 7.2 The Seven Rules of Great Git Commits
1.  **Separate the Subject from the Body:** Insert a single blank line between the subject line (header) and the explanatory body text.
2.  **Limit the Subject Line:** Restrict the subject line (including type and scope) to **50 characters or less** for clean readability, with a strict hard ceiling of 72 characters.
3.  **Capitalize the Subject Line:** Start the description after `type(scope):` in lowercase (matching the §7.6 examples and the spec samples), unless the repo mandates capitals — repo convention wins. (Beams' capitalize rule predates the `type:` prefix and cannot apply literally to the whole line.)
4.  **No Trailing Period:** Do not end the subject line with a trailing period.
5.  **Use the Imperative Mood:** Write the subject line in the imperative present tense (e.g., "Add billing module" instead of "Added billing module" or "Adds billing module"). A simple test: *If applied, this commit will "Add billing module"*.
6.  **Wrap the Body at 72 Characters:** Set hard line wraps at 72 characters in the optional body text to prevent horizontal overflow in standard git terminal outputs.
7.  **Explain the What and Why (Not the How):** Focus the body text on explaining *why* the change was made, *what* problem it addresses, and the reasoning behind it, rather than repeating *how* the implementation is coded.

### 7.3 Standard Commit Types
The `<type>` prefix describes the exact nature and intent of the change, which automatically dictates Semantic Versioning (SemVer) increments and changelog generation:
*   **`feat`:** Introduces a new feature to the codebase (correlates with a `MINOR` SemVer version bump).
*   **`fix`:** Patches a bug in the codebase (correlates with a `PATCH` SemVer version bump).
*   **`docs`:** Documentation-only changes (e.g., updating README, API references, or markdown standards).
*   **`style`:** Coding style or formatting changes (e.g., whitespaces, semicolons, spacing) that do not alter execution or business logic.
*   **`refactor`:** A code modification that neither fixes a bug nor adds a feature, but improves internal code structure.
*   **`perf`:** A performance-critical change designed to optimize execution speed or reduce memory consumption.
*   **`test`:** Appending missing tests or correcting existing unit, integration, or regression tests.
*   **`build` / `ci`:** Updates to build processes, auxiliary compile tools, or CI/CD pipelines/configurations.
*   **`chore`:** Auxiliary or administrative changes that do not modify production files or source code.
*   **`revert`:** Reverts a prior commit.

### 7.4 Scope and Context (Optional)
The optional `<scope>` SHOULD be a lowercase noun wrapped in parentheses following the commit type (e.g., `feat(auth):`, `fix(parser):`). It specifies the functional area or module affected by the commit, enabling effortless directory-scoped change tracking. The spec treats units as case-insensitive; lowercase is convention, not validity.

### 7.5 Documenting Breaking Changes (SemVer Major)
Breaking changes introduce incompatible API modifications requiring a `MAJOR` SemVer version bump. They must be declared by:
1.  An exclamation mark (`!`) immediately after the type/scope (e.g., `feat(billing)!: require Stripe SCA compliance`), or
2.  A `BREAKING CHANGE:` footer at the bottom of the commit message followed by a brief explanation of the breaking shift.
`BREAKING-CHANGE:` (hyphenated) is the equivalent footer form — footer tokens MUST use `-` in place of spaces, followed by `:` or ` #` (e.g., `Acked-by:`, `Refs #123`).

### 7.6 Architectural Examples
*   **Simple Feature with Scope:**
    ```text
    feat(auth): implement JWT token verification
    ```
*   **Bug Fix with Body and Footer:**
    ```text
    fix(button): correct alignment of CTA buttons on mobile layouts
    ```
*   **Breaking Change with Explanatory Body and Footer:**
    ```text
    feat(payment)!: upgrade Stripe integration to v2026-08-16

    This updates our billing checkout system to comply with Stripe's
    updated Payment Intents API, replacing direct credit card inputs.

    BREAKING CHANGE: The `chargeUser()` endpoint has been deprecated in 
    favor of the new secure `createPaymentIntent()` orchestration flow.
    ```

---

## 8. Language-Specific Standards

Detected stack for this repository (`README.md` §2): PHP 8.2+ / Laravel 12, vanilla JavaScript in the browser (no build step), Bash, plain CSS with design tokens, and Firebase Realtime Database as the sole datastore. Each subsection carries its own casing/naming rules; §2.1's casing table resolves to §8.1 (PHP), §8.3 (Bash), and §8.4 (CSS). Standards for stacks not present in this repository (TypeScript, Python, Supabase, external API design) are deliberately out of scope.

---

### 8.1 PHP (Laravel 12, PHP 8.2+)

Use these standards when writing, refactoring, or reviewing PHP code.

---

#### 1. Typographic Standards & Code Style

Strictly adhere to the **PSR-12** and **PER Coding Style** specifications for all PHP implementations.

##### 1.1 Casing Rules
*   `camelCase` for methods, class-scoped functions, and variables.
*   `snake_case` for procedural helper functions.
*   `PascalCase` for classes, interfaces, traits, and enums.
*   `SCREAMING_SNAKE_CASE` for class constants and global constants.

##### 1.2 strict_types Declarations
Every PHP file **MUST** declare strict typing at the absolute top of the file as its first execution statement (following the opening `<?php` tag). This prevents PHP's runtime from performing silent, implicit scalar type coercion across boundaries.

```php
<?php

declare(strict_types=1);

namespace App\Core;
```

*   **Indentation:** Always use standard 4 spaces for indentation (no tabs).
*   **Braces:** Opening braces for classes and methods MUST go on a new line; closing braces must also go on a new line. Braces for control structures (e.g., `if`, `for`, `foreach`) must go on the same line.

---

#### 2. Eradicating Primitive Obsession via Immutable Value Objects

Since PHP has a fully nominal runtime type system, the primary mechanism to solve primitive obsession (e.g., passing raw strings/ints for complex business domains) is wrapping scalar types in dedicated, immutable **Value Objects** (or **Readonly Classes** in PHP 8.2+).

```php
// ❌ FAIL (Primitive Obsession - raw integer can represent anything and allows invalid values)
function deleteUser(int $userId): void { ... }

// ✅ PASS (Nominal Value Object enforcing type safety and invariants in constructor)
readonly class UserId
{
    public function __construct(public int $id)
    {
        if ($this->id <= 0) {
            throw new \InvalidArgumentException("User ID must be a positive integer.");
        }
    }
}

function deleteUser(UserId $userId): void { ... }
```

---

#### 3. Strict Type Declarations & Type Safety

PHP 8.x supports powerful, static-ready type hints. All class methods and functions **MUST** carry strict parameter type hints and return type hints.

*   **Avoid Raw Array Typing for Objects:** Avoid using the generic `array` type hint when modeling structured domain models—use typed classes or dedicated collection classes instead.
*   **Procedures vs. Functions:** Use `void` strictly for procedures (methods that perform side effects but return no value). Use `never` for methods that always throw an exception or terminate execution.
*   **Union & Intersection Types:** Leverage union types (`string|int`) and intersection types (`Renderable&Loggable`) strictly to define precise compile-time and runtime contracts.

```php
// ❌ FAIL (Weak typing - accepts anything and returns anything)
function processPayment($amount) { ... }

// ✅ PASS (Strict nominal boundaries)
readonly class PaymentAmount
{
    public function __construct(public float $value)
    {
        if ($this->value <= 0.0) {
            throw new \InvalidArgumentException("Payment amount must be positive.");
        }
    }
}

function processPayment(PaymentAmount $amount): TransactionId { ... }
```

---

#### 4. Docstring Standards (PHPDoc)

Public API boundaries and complex methods **MUST** carry structured `/** ... */` block comments (PHPDocs) to detail business intent, parameter descriptions, return descriptions, and potential thrown exceptions.

```php
/**
 * Calculate the accrued compound interest.
 *
 * This function applies compound interest over designated periods. It assumes
 * the caller has already validated the tax and compliance rules.
 *
 * @param float $principal Non-negative starting capital.
 * @param float $rate Decimal rate representing interest (e.g., 0.05 for 5%).
 * @param int $periods Positive compound intervals.
 * @return float The accrued final value.
 * @throws \InvalidArgumentException If principal is negative or periods is non-positive.
 */
function calculateCompoundInterest(float $principal, float $rate, int $periods): float
{
    if ($principal < 0.0) {
        throw new \InvalidArgumentException("Principal must be non-negative.");
    }
    if ($periods <= 0) {
        throw new \InvalidArgumentException("Periods must be positive.");
    }
    return $principal * ((1.0 + $rate) ** $periods);
}
```

---

### 8.2 JavaScript (vanilla browser JS, no build step)

Use these standards when working in pure JavaScript environments without TypeScript compilation.

---

#### 1. Strict Mode

All plain JavaScript modules **MUST** operate under strict mode to prevent silent runtime errors (such as writing to read-only properties or implicit global variables). Add the directive at the absolute top of the module or rely on ESM module standards:
```javascript
"use strict";
```

---

#### 2. JSDoc Static Typing (checkJs)

To achieve strict static type checking and comprehensive IDE autocompletion in vanilla JavaScript without compiling files, write robust JSDoc block comments and enable TypeScript checks (`// @ts-check` at the top of the file, or `checkJs: true` in `tsconfig.json`).

```javascript
// @ts-check

/** 
 * @typedef {Object} UserAccount
 * @property {number} id - Unique database index
 * @property {string} email - Canonical email address
 * @property {boolean} isActive - Account state flag
 */

/**
 * Deactivates a validated user account.
 * 
 * @param {UserAccount} user - The system user object
 * @returns {UserAccount} The deactivated user
 */
function deactivateAccount(user) {
  user.isActive = false;
  return user;
}
```

---

#### 3. Safe Boundaries

*   **No Prototype Mutation:** Never add properties or modify prototype chains of built-in global objects (e.g., `Array.prototype`, `Object.prototype`, `String.prototype`). This is a critical source of runtime vulnerabilities and third-party library conflicts.
*   **Immutable Composition:** Prefer shallow copying and array transformations (`map`, `filter`, `reduce`) over in-place index mutations to prevent unexpected state leaks.

---

### 8.3 Bash

Use these standards when writing, refactoring, or reviewing shell scripts.

---

#### 1. Safety & Robustness Gates

Always write defensive shell scripts that fail loudly and handle errors deterministically.

*   **The Safety Prefix:** Every `.sh` script **MUST** start with the safety header directly below the shebang to ensure the execution halts on any error or uninitialized variable:
    ```bash
    #!/usr/bin/env bash
    set -euo pipefail
    ```
    *   `-e`: Exit immediately if any command exits with a non-zero status.
    *   `-u`: Treat unset variables as an error and exit immediately.
    *   `-o pipefail`: Ensure that the return value of a pipeline is the status of the last command to exit with a non-zero status, preventing errors in piped streams from being swallowed silently.

---

#### 2. Portable Shebangs

Avoid hardcoding path binaries (like `#!/bin/bash` or `#!/bin/sh`) which differ across operating systems (macOS, Linux, BSD). Always resolve standard shell binaries through `/usr/bin/env` for maximum portability:
```bash
#!/usr/bin/env bash
```

---

#### 3. Quoting and Word Splitting

To prevent unexpected word splitting and glob expansion, always quote variable expansions, command substitutions, and array references.

```bash
### ❌ FAIL (Vulnerable to word-splitting if $filepath contains spaces)
cat $filepath

### ✅ PASS (Quoted variable expansion)
cat "$filepath"

### ✅ PASS (Command substitution quoted)
output="$(git status --porcelain)"
```

---

#### 4. Conditional Evaluators

Prefer the modern, robust double-bracket conditional evaluator `[[ ... ]]` over legacy `[ ... ]` or `test` commands. It supports safer operators (no word splitting inside brackets) and cleaner regex matching.

```bash
### ❌ FAIL (Legacy single bracket)
if [ $name = "admin" ]; then ... fi

### ✅ PASS (Modern double bracket)
if [[ "$name" == "admin" ]]; then ... fi
```

---

#### 5. Standardized Error Logging & Exit Codes

Always route standard error messages to the standard error file descriptor (`stderr`) and exit with appropriate, documented, non-zero codes on failure.

```bash
log_error() {
    echo "[ERROR] $1" >&2
}

if [[ ! -f "$config_file" ]]; then
    log_error "Configuration file not found at: $config_file"
    exit 1  # Standard general error
fi
```

---

#### 6. Static Analysis (ShellCheck)

All shell scripts **MUST** pass strict static analysis checks via `shellcheck` with zero warnings before declaring a task complete.

---

#### 7. Casing Conventions

*   Environment variables and exported constants: `UPPER_SNAKE_CASE`.
*   Functions and locals: `snake_case`.

---

### 8.4 CSS (plain stylesheet, zero build step)

Provenance: inlined from [`research/css-coding-standards-2026.md`](research/css-coding-standards-2026.md).

All URLs accessed 2026-09-22. Sources are primary: W3C WCAG 2.2 Understanding documents and MDN, plus one company style guide explicitly labeled first-party where it is used. Project decisions cite `README.md` (§2 "Stack — decided", §3 "Design system") as their owner. Every rule below is checkable as written: a MUST/SHOULD/NEVER keyword, a numeric limit, a named pair, or a required structure.

Scope: plain CSS, zero build step, one `public/css/app.css`, design tokens + component classes (README §2). No preprocessor, no npm/Node toolchain, no animation library (README §2 "Explicitly rejected").

#### 1. File, layers, formatting

- All project CSS MUST live in `public/css/app.css`; it MUST be the only project-authored stylesheet linked from layouts (README §2: "One `public/css/app.css`").
- The first statement of `app.css` MUST be `@layer tokens, base, components, utilities;`, and every rule MUST sit inside one of those four layers — `tokens` (custom-property declarations), `base` (element defaults), `components` (component classes), `utilities` (small overrides) (project structure; layer mechanics per [MDN: `@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer)).
- Rules MUST be placed in the correct layer instead of relying on source order: once layer order is established, the first-declared layer has the lowest priority and the last the highest, and styles NOT in a layer always override layered styles — so an unlayered stray rule beats every component ([MDN: `@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer)).
- Conflicts SHOULD be resolved by layer position, never by raising specificity or adding weight — layered order beats specificity, which "enables using simpler CSS selectors" ([MDN: `@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer)).
- Indentation MUST be 2 spaces; tabs MUST NOT be used (first-party: [Google HTML/CSS Style Guide, Indentation](https://google.github.io/styleguide/htmlcssguide.html)).

#### 2. Naming and casing

- Class names MUST match `^[a-z][a-z0-9]*(-[a-z0-9]+)*$` — all lowercase, words separated by hyphens only (first-party: [Google HTML/CSS Style Guide, "Separate words in class names by a hyphen"](https://google.github.io/styleguide/htmlcssguide.html)).
- Class names MUST be meaningful and as short as possible but as long as necessary — name purpose, not appearance (first-party: [Google HTML/CSS Style Guide, "Use meaningful or generic class names"](https://google.github.io/styleguide/htmlcssguide.html)):

  ```css
  /* Good */  .btn-checkout { … }
  /* Bad */   .btn-green-big { … }
  ```

- Classes MUST NOT be qualified with type selectors (`button.btn`); the rule targets the class alone (first-party: [Google HTML/CSS Style Guide, Type Selectors](https://google.github.io/styleguide/htmlcssguide.html)).
- Custom properties MUST begin with `--`, with words separated by hyphens (`--space-2` not `--space2`) ([MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties)).
- Every design-token custom property MUST carry exactly one group prefix from this fixed set: `--color-`, `--space-`, `--radius-`, `--shadow-`, `--font-`, `--duration-`, `--ease-`; a new token group MUST be added to this list before use (project token namespaces under README §2 "design tokens + component classes").
- Custom property names are case-sensitive — `--my-color` and `--My-color` are different properties — so every reference MUST match the declared case exactly, and mixing cases MUST NOT be relied upon to fall back ([MDN: Using CSS custom properties, "Custom property names are case sensitive"](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties)).

#### 3. Design tokens (custom properties)

- Global tokens MUST be declared once, on `:root`, so they are referenced globally; component-local tokens MUST be declared on that component's own class only when the value is genuinely scoped there ([MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties): "`:root` … so that it can be referenced globally").
- Component rules MUST consume colors, spacing, radii, fonts, shadows, durations, and easings via `var()`; a raw color literal (`#…`, `rgb(…)`) MUST appear only inside `tokens` declarations, NEVER in a component or base rule (README §2 tokens; README §5 roadmap item 7 dark-mode audit depends on one place to change; rationale per [MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties): repeated values otherwise need search-and-replace across the stylesheet).
- `var()` MUST NOT be used in media queries or container queries, in property names, or in selectors — only in property values ([MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties)).
- Because custom properties cascade and inherit from the parent, a token scoped to a child element MUST NOT be referenced by any ancestor ([MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties)).

#### 4. Selectors and specificity

- ID selectors MUST NOT be used — they are reserved for anchors, and one ID (`1-0-0`) outweighs any number of classes (first-party: [Google HTML/CSS Style Guide, "Avoid ID selectors"](https://google.github.io/styleguide/htmlcssguide.html); weight columns per [MDN: Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity)).
- A component rule SHOULD be exactly one class selector, i.e. specificity `0-1-0`; selectors SHOULD keep specificity "down to a minimum" ([MDN: Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity)):

  ```css
  /* Good */  .ticket.is-late { … }   /* 0-2-0 */
  /* Bad */   #kitchen .list .ticket.late { … }  /* 1-3-0 */
  ```

- State and variant styling MUST be expressed with classes or pseudo-classes on the component class (`.btn:hover`, `.badge--promo`), never with an ancestor ID chain (derived from the three-column model, [MDN: Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity)).
- `!important` MUST NOT be used anywhere — it "break[s] the natural cascade of CSS"; override via a later layer instead (first-party: [Google HTML/CSS Style Guide, "Avoid using `!important` declarations"](https://google.github.io/styleguide/htmlcssguide.html); layer escape hatch per [MDN: `@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer)).
- When grouping selectors where the shared part should add no weight, `:where()` SHOULD be used — `:where()` and its parameters count as `0-0-0` ([MDN: Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity)).

#### 5. Motion: transitions vs. keyframes vs. Web Animations API

Decision table — one mechanism per situation (mechanism split from README §2 "Motion"; behavior per the cited MDN pages):

| Situation | Mechanism |
|---|---|
| Two-state property change on hover / focus / active / toggle | CSS `transition` ([MDN: Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using): transitions control the speed of changing CSS properties between states) |
| Multi-step or looping timeline authored in CSS (toast entrance) | `@keyframes` + `animation` ([MDN: Using CSS animations](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Animations/Using): keyframes mark start/end states and intermediate waypoints) |
| Choreography sequenced from JS across elements (badge bump, card stagger) | native WAAPI `element.animate()` ([MDN: Web Animations API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Animations_API): `Element.animate()` creates and plays an `Animation` with playback controls) |

- An animation library MUST NOT be added; CSS + WAAPI covers all motion (README §2 "Explicitly rejected": Framer Motion / `motion` / any animation library).
- A `transition` MUST name each animated property explicitly; `transition: all` MUST NOT be used ([MDN: Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using): you decide which properties animate "by listing them explicitly").
- Animations SHOULD touch only `transform` and `opacity` (plus color) when the effect allows; properties that affect the box model SHOULD NOT be animated ([MDN: Using CSS animations](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Animations/Using): "changing any properties that impact the box model negatively impacts performance").
- Transitions MUST NOT animate to or from `auto`; the behavior is unspecified across engines ([MDN: Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using)).
- Entering/exiting elements hidden with `display: none` MUST use `transition-behavior: allow-discrete` together with `@starting-style`; that pair is the required structure for fade-in/out of `display` ([MDN: Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using)).
- Every duration and delay MUST use a `--duration-*` token and every easing a `--ease-*` token; literal `ms`/`s` values MUST NOT appear in component or motion rules (project token rule, README §2).
- All non-essential motion that moves or scales content MUST be reduced or removed under `@media (prefers-reduced-motion: reduce)` — fades in place of translation/scale are acceptable ([MDN: `prefers-reduced-motion`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion): the feature lets users request minimal non-essential motion; [MDN: Web Animations API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Animations_API) applies the same note to JS-driven animation).
- The kitchen new-order flash MUST NEVER flash more than 3 times in any one-second period (WCAG 2.2 Level A, flashing content can trigger seizures) ([WCAG 2.3.1 Three Flashes or Below Threshold](https://www.w3.org/WAI/WCAG22/Understanding/three-flashes-or-below-threshold)).
- Blinking content (e.g., an attention blink) MUST stop by itself within 5 seconds or MUST provide a mechanism to pause, stop, or hide it ([WCAG 2.2.2 Pause, Stop, Hide](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide); sufficient techniques G11/G152/SCR22 and failure F112 are listed on that page).

#### 6. Responsive layout: mobile-first

- Every layout MUST ship `<meta name="viewport" content="width=device-width, initial-scale=1.0">` (MDN's recommended setting: [MDN: `<meta name="viewport">`](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/meta/name/viewport); first-party: [Google HTML/CSS Style Guide](https://google.github.io/styleguide/htmlcssguide.html) prescribes the same content value).
- Layouts MUST NOT disable user zoom: `user-scalable=no` and `maximum-scale` below `2` MUST NOT be set ([MDN: `<meta name="viewport">`](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/meta/name/viewport): disabling zoom prevents low-vision users from reading content; WCAG requires at least 2× scaling).
- Mobile-first structure is a named pair: unadorned base rules MUST define the narrow/mobile layout, and widening MUST happen only through `min-width` media queries — `max-width` media queries MUST NOT be used (README §3 "Mobile-first breakpoints"; method per [MDN: Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design): single-column base, then "check for wider screens").

  ```css
  /* Good (mobile-first) */  @media (min-width: 48rem) { … }
  /* Bad (desktop-first) */  @media (max-width: 47.99rem) { … }
  ```

- Breakpoints MUST use relative units (`rem`), not device pixel widths, and MUST be placed where the content starts to look bad, not at a named device size ([MDN: Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design)).
- Content images MUST be fluid: `max-width: 100%` on the image, so they never force horizontal overflow ([MDN: Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design): fluid images "have their `max-width` property set to `100%`").
- New layout code SHOULD use flexbox or grid, which are responsive by default, instead of floats ([MDN: Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design)).

#### 7. Accessibility (WCAG 2.2)

##### 7.1 Focus

- Every keyboard-operable control MUST have a visible focus indicator, styled via `:focus-visible` ([WCAG 2.4.7 Focus Visible (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible); sufficient technique C45 uses `:focus-visible`; also [MDN: `:focus-visible`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Selectors/:focus-visible): removing focus styles "makes keyboard navigation inaccessible for sighted users").
- `outline: none` / `outline: 0` MUST NOT remove the default indicator without a replacement visible at ≥3:1 contrast against adjacent colors ([MDN: `:focus-visible`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Selectors/:focus-visible) citing [WCAG 1.4.11 Non-Text Contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast): the focus indicator must be at least 3:1).
- Focus styling SHOULD target `:focus-visible`, not `:focus`, so pointer clicks do not draw a ring while keyboard focus still shows one ([MDN: `:focus-visible`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Selectors/:focus-visible)).

##### 7.2 Contrast and color

- Normal text MUST have a contrast ratio of ≥4.5:1 against its background; large-scale text (≥18 pt, ≈24 px, or 14 pt bold) MUST have ≥3:1 ([WCAG 1.4.3 Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum)).
- Computed contrast MUST NOT be rounded when checking thresholds — 4.499:1 fails 4.5:1 ([WCAG 1.4.3 Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum)).
- Non-text UI components (input borders, control states), focus indicators, and meaningful graphical objects MUST have ≥3:1 contrast against adjacent colors ([WCAG 1.4.11 Non-Text Contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast)).
- State and meaning (error, promo, low stock, valid/invalid) MUST NOT be conveyed by color alone — each MUST also carry text or a shape/icon cue ([WCAG 1.4.1 Use of Color (Level A)](https://www.w3.org/WAI/WCAG22/Understanding/use-of-color); failure F81 on that page covers required/error fields identified by color only).
- The kitchen ticket's 12-minute "late" state MUST NOT be conveyed by red alone — the running numeric age MUST remain visible alongside the color change ([WCAG 1.4.1](https://www.w3.org/WAI/WCAG22/Understanding/use-of-color); project feature: README §3/§5 "age timers (red at 12 min)").

##### 7.3 Reflow and text

- Every page MUST present content without loss of information or functionality and without scrolling in two dimensions at a width equivalent to 320 CSS px (≈1280 px viewport at 400% zoom), except content that genuinely requires 2D layout (maps, data tables, video) ([WCAG 1.4.10 Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow)).
- Text containers MUST NOT clip or overlap text when a user overrides spacing to line height ≥1.5× font size, paragraph spacing ≥2× font size, letter spacing ≥0.12 em, and word spacing ≥0.16 em — i.e., no fixed-height boxes around copy (WCAG failure F104 is exactly this clipping/overlap) ([WCAG 1.4.12 Text Spacing](https://www.w3.org/WAI/WCAG22/Understanding/text-spacing)).

##### 7.4 Touch targets

- Every pointer-input target MUST be at least 24×24 CSS px ([WCAG 2.5.8 Target Size (Minimum) (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum)).
- Cashier/POS touch targets MUST be ≥44 px (project rule, README §3 "cashier touch targets ≥44 px" — stricter than the 24 px WCAG floor; its spacing exception must not be needed on POS tiles).

#### 8. Source list

- W3C, *Understanding WCAG 2.2* success criteria: [1.4.1 Use of Color](https://www.w3.org/WAI/WCAG22/Understanding/use-of-color), [1.4.3 Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum), [1.4.10 Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow), [1.4.11 Non-Text Contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast), [1.4.12 Text Spacing](https://www.w3.org/WAI/WCAG22/Understanding/text-spacing), [2.2.2 Pause, Stop, Hide](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide), [2.3.1 Three Flashes or Below Threshold](https://www.w3.org/WAI/WCAG22/Understanding/three-flashes-or-below-threshold), [2.4.7 Focus Visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible), [2.5.8 Target Size (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum) (accessed 2026-09-22).
- MDN (W3C-adjacent web platform docs): [`@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer), [Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties), [Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity), [Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using), [Using CSS animations](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Animations/Using), [Web Animations API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Animations_API), [`prefers-reduced-motion`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion), [Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design), [`<meta name="viewport">`](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/meta/name/viewport), [`:focus-visible`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Selectors/:focus-visible) (accessed 2026-09-22).
- First-party style guide (company-authored, used only for naming/formatting rules): [Google HTML/CSS Style Guide](https://google.github.io/styleguide/htmlcssguide.html) (accessed 2026-09-22).
- Project decisions: `README.md` §2 "Stack — decided" (plain CSS, one stylesheet, motion mechanism split, rejected libraries), §3 "Design system" (mobile-first, 44 px POS targets, kitchen display), §5 roadmap item 7 (dark-mode audit).

---

### 8.5 Firebase Realtime Database (sole datastore)

Provenance: inlined from [`research/firebase-rtdb-2026.md`](research/firebase-rtdb-2026.md).

Status: Reviewed against primary sources, 2026-09-22.
Scope: this repo's `RtdbService` (Laravel 12 / PHP 8.2+, RTDB REST API as sole datastore, service-account OAuth, kitchen page polling 5–10s, stock decrement-after-insert). Every source link is an official Firebase or Google document.

#### 1. Flat over deep

- Data models MUST stay as flat as practical: fetching a location retrieves *all* of its child nodes, and granting read/write at a node grants it to everything under it, so nesting costs bandwidth and widens the rules blast radius ([Structure Your Database](https://firebase.google.com/docs/database/web/structure-data)).
- Data nodes MUST NOT nest deeper than 4 levels below the root (RTDB physically allows up to 32 levels, but the official guidance is "in practice, it's best to keep your data structure as flat as possible" — [Structure Your Database](https://firebase.google.com/docs/database/web/structure-data)).
- A list/meta path MUST NOT contain bulk payload children (e.g. messages under a chat-list node): the docs' counter-example shows listing conversation titles then requiring "potentially downloading hundreds of megabytes of messages" — [Structure Your Database](https://firebase.google.com/docs/database/web/structure-data).
- List children MUST be keyed by server push key (`POST` generates a unique child name — [Saving Data](https://firebase.google.com/docs/database/rest/save-data)) or by stable entity ID; keys MUST NOT be array indices from JSON arrays.
- Two-way relationships MUST be stored as index maps `{"$id": true}` at both ends; membership checks MUST read one path (`/users/$uid/groups/$group_id` is null or not) instead of scanning — the docs call the resulting duplication "a necessary redundancy for two-way relationships" ([Structure Your Database](https://firebase.google.com/docs/database/web/structure-data)).

Bad (one fetch drags every message):

```json
{ "chats": { "one": { "title": "...", "messages": { "m1": { "message": "..." }, "...": {} } } } }
```

Good (meta, members, messages in separate paths; each fetch is narrow):

```json
{
  "chats":    { "one": { "title": "...", "lastMessage": "...", "timestamp": 1459361875666 } },
  "members":  { "one": { "ghopper": true, "alovelace": true } },
  "messages": { "one": { "m1": { "message": "...", "timestamp": 1459361875337 } } }
}
```

([Structure Your Database](https://firebase.google.com/docs/database/web/structure-data))

#### 2. Denormalization and duplicated copies

- Any fact stored at N paths (roadmap item 3: product names in carts *and* line items) MUST be written by exactly ONE multi-path request; writing the copies in N sequential requests is NEVER allowed — simultaneous multi-path updates are documented as atomic: "either all updates succeed or all updates fail" ([Read and write data](https://firebase.google.com/docs/database/web/read-and-write)).
- The multi-path request MUST be a single REST `PATCH` whose body is a flat map of slash-delimited path keys relative to the request URL, issued at the common ancestor of all targets ([Saving Data](https://firebase.google.com/docs/database/rest/save-data), [REST API reference](https://firebase.google.com/docs/reference/rest/database)).

Good (one atomic `PATCH …/users.json`):

```json
{ "alanisawesome/nickname": "Alan The Machine", "gracehopper/nickname": "Amazing Grace" }
```

Bad (nested objects are NOT multi-path keys — this overwrites the entire parent node):

```json
{ "alanisawesome": { "nickname": "Alan The Machine" }, "gracehopper": { "nickname": "Amazing Grace" } }
```

([Saving Data](https://firebase.google.com/docs/database/rest/save-data))

- Fan-out MUST NOT span database instances: "Realtime Database doesn't support queries across database instances" and sharded data should have "no sharing or duplication of data across database instances" ([Scale with Multiple Databases](https://firebase.google.com/docs/database/usage/sharding)).
- A stock delta and the records it belongs to MUST ride in the same request body where they must change together: a `".sv"` server value inside a multi-path `PATCH` is resolved by the database server in that single atomic write ([Saving Data](https://firebase.google.com/docs/database/rest/save-data), [REST API reference — Server Values](https://firebase.google.com/docs/reference/rest/database)).
- Note: multi-path updates across top-level trees *within one instance* are supported and atomic (the docs' own example writes `/posts/$id` and `/user-posts/$uid/$id` from the root — [Read and write data](https://firebase.google.com/docs/database/web/read-and-write)); sequential per-copy writes and any fan-out across instance URLs are NEVER allowed.

#### 3. `.indexOn` for every query path

- Every query `RtdbService` issues MUST be covered by an `.indexOn` rule at or above the queried node; a REST query whose index is not defined fails with HTTP 400, and the docs are explicit that "indexes are not required for development *unless you are using the REST API*" ([Index your data](https://firebase.google.com/docs/database/security/indexing-data), [REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database)).
- The rules file MUST ship an `.indexOn` entry for each query path in the codebase; adding a query without its index entry is a review blocker ("Before launching your app … it is important to specify indexes for any queries you have" — [Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)).
- Queries MUST use exactly one `orderBy` per request — "Queries can only filter by one key at a time. Using the `orderBy` parameter multiple times on the same request throws an error" ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data)).
- Every `orderBy` MUST be combined with at least one of `startAt`, `endAt`, `limitToFirst`, `limitToLast`, `equalTo` ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data)).
- Ordering by a node's own key needs no index (keys are auto-indexed: "A node's key is indexed automatically" — [Index your data](https://firebase.google.com/docs/database/security/indexing-data)); ordering by *value* MUST declare `".indexOn": ".value"` ([Index your data](https://firebase.google.com/docs/database/security/indexing-data)).
- `.indexOn` MUST live in the deployed security-rules JSON — rules are enforced on the Firebase servers at all times, on every read and write ([Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)).

#### 4. Security-rules posture: locked rules, backend-only writes

- The database MUST run in locked mode: `".read": false, ".write": false` at the root. Locked mode is defined as "Denies all reads and writes from mobile and web clients. Your authenticated application servers can still access your database." ([Installation & Setup for REST API](https://firebase.google.com/docs/database/rest/start)); by default "your rules do not allow anyone access to your database" ([Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)).
- Shared/production databases MUST NOT use test mode, which "allows anyone to read and overwrite your data" ([Installation & Setup for REST API](https://firebase.google.com/docs/database/rest/start)).
- All reads and writes MUST originate from the Laravel backend authenticated with a Google OAuth2 access token from a service account; that credential is the documented way to "grant that server full read and write access" and to "bypass your Realtime Database Rules" ([Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth)).
- An unauthenticated request to the database URL MUST be denied (smoke check: `GET <db>.firebaseio.com/.json` with no token → 401 when locked). Unauthenticated REST calls succeed only if rules allow public access, and rule violations return 401 ([REST API reference — Authenticate requests / Error Conditions](https://firebase.google.com/docs/reference/rest/database)).
- The rules file MUST contain only deny-all `.read`/`.write` plus `.indexOn` entries. `.read`/`.write` cascade and shallower rules override deeper ones, so a single `true` branch anywhere re-opens the tree ([Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)).
- Data validation MUST NOT rely on `.validate` rules: OAuth-authenticated requests bypass the rules layer entirely ([Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth)), and even where rules apply, "validation rules do not cascade — all relevant validation rules must evaluate to true" ([Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)). Integrity checks belong in the PHP domain layer (§8.1).
- Legacy database secrets MUST NOT be used; they are long-lived credentials and the official guidance is to migrate to OAuth2 access tokens or ID tokens ([Authenticate REST Requests — Legacy tokens](https://firebase.google.com/docs/database/rest/auth)).

#### 5. Service-account OAuth tokens

- Token minting MUST request exactly the two required scopes: `https://www.googleapis.com/auth/userinfo.email` and `https://www.googleapis.com/auth/firebase.database` ([Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth)).
- Requests MUST carry the token as `Authorization: Bearer <ACCESS_TOKEN>`; the `access_token=` query-string form MUST NOT be used (it is documented only as an alternative — [Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth), [REST API reference](https://firebase.google.com/docs/reference/rest/database)) because URLs end up in logs.
- Access tokens MUST be cached in the PHP process and reused for their full lifetime: they expire at `expires_in: 3600` seconds, and "access tokens can be reused during the duration window specified by the `expires_in` value" ([Using OAuth 2.0 for Server to Server Applications](https://developers.google.com/identity/protocols/oauth2/service-account)). Minting a token per request is NEVER allowed; Google's own example flags direct per-request token use as "(not recommended)" in favor of a library-managed session ([Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth)).
- A cached token MUST be refreshed when its age reaches 55 minutes (3300s), before the 3600s expiry; when a token expires the application "should generate another JWT, sign it, and request another access token" ([Using OAuth 2.0 for Server to Server Applications](https://developers.google.com/identity/protocols/oauth2/service-account)).
- A 401 response ("auth token has expired", "auth token … invalid", "authenticating with an access_token failed" — [REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database)) MUST invalidate the cached token and retry the request exactly once after refreshing; a second 401 MUST throw.
- JWT construction MUST go through Google's client libraries, not hand-rolled signing: libraries are "strongly recommended due to the complexity and security risks associated with creating and signing JSON Web Tokens" ([Using OAuth 2.0 for Server to Server Applications](https://developers.google.com/identity/protocols/oauth2/service-account)). For PHP this means one of Google's official client libraries (linked from the OAuth guide above) or one of the PHP helper libraries listed in the official REST setup docs ([Installation & Setup for REST API](https://firebase.google.com/docs/database/rest/start)).
- The service-account JSON key file MUST be stored outside the repo and outside the web root; it MUST NOT be committed ("Don't submit service account keys to source code repositories … always store the service account key separate from the source code" — [Best practices for managing service account keys](https://cloud.google.com/iam/docs/best-practices-for-managing-service-account-keys)).
- Keys SHOULD be rotated routinely and SHOULD carry an expiry time ([Best practices for managing service account keys](https://cloud.google.com/iam/docs/best-practices-for-managing-service-account-keys)).
- JWT `exp` MUST be exactly `iat + 3600`; `invalid_grant` responses caused by clock skew MUST be treated as a host-clock fault, not retried blindly — the OAuth error table says to "use a clock with skew to account for clock differences between systems" ([Using OAuth 2.0 for Server to Server Applications](https://developers.google.com/identity/protocols/oauth2/service-account)).

#### 6. Writes: atomicity, counters, stock

- All writes MUST go to `<db>.firebaseio.com/<path>.json` over HTTPS — "HTTPS is required. Firebase only responds to encrypted traffic" ([REST API reference](https://firebase.google.com/docs/reference/rest/database)).
- Updates to existing records MUST use `PATCH` (omitted children are preserved); `PUT` MUST only be used where overwriting the whole path is intended, because PUT replaces the path with the payload ([Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- Every timestamp written MUST be the server value `{".sv": "timestamp"}`; PHP clocks (`time()`, `now()`) MUST NOT appear in write payloads — server timestamps are the documented accurate option ([Saving Data](https://firebase.google.com/docs/database/rest/save-data), [Enabling Offline Capabilities](https://firebase.google.com/docs/database/web/offline-capabilities)).
- Stock quantity changes MUST use the atomic server value `{".sv": {"increment": <delta>}}` (negative delta for decrement): it is applied "directly on the database server, [so] there is no chance of a conflict", initializes a missing node to the delta, and follows IEEE 754 semantics on overflow ([REST API reference — Server Values](https://firebase.google.com/docs/reference/rest/database), [Read and write data](https://firebase.google.com/docs/database/web/read-and-write)).
- Stock MUST NEVER be updated by read-modify-write (GET quantity → subtract in PHP → PUT absolute value); the docs identify incremental counters as data "that could be corrupted by concurrent modifications", which is what transactions/conditional requests exist for ([Read and write data](https://firebase.google.com/docs/database/web/read-and-write)).
- Note: there is no `/.info/serverValue` location — official docs document only `/.info/connected` and `/.info/serverTimeOffset` under `.info/` ([Enabling Offline Capabilities](https://firebase.google.com/docs/database/web/offline-capabilities)). Server values are `".sv"` placeholders inside write payloads; code MUST NOT reference `/.info/serverValue`.

Bad (two racing writers both read 5, both write 4, one sale vanishes):

```php
$stock = $rtdb->get("products/$id/stock");          // GET
$rtdb->put("products/$id/stock", $stock - $qty);    // PUT — lost update
```

Good (no read needed; server applies the delta atomically):

```php
$rtdb->patch("items/$cartId/$lineNo", [
    'product' => $productId, 'qty' => $qty,          // duplicated copy
]);
$rtdb->patch('/', ["products/$productId/stock" => ['.sv' => ['increment' => -$qty]]]);
```

- The decrement-after-insert flow MUST be: (1) `POST` the order/line item, which returns `{"name": "<key>"}` with 200; (2) only on success, apply duplicated copies plus the stock delta in ONE multi-path `PATCH` (atomic — [Read and write data](https://firebase.google.com/docs/database/web/read-and-write), [REST API reference](https://firebase.google.com/docs/reference/rest/database)). If step 1 fails, step 2 MUST NOT run (§8).
- Where a write MUST depend on the current stored value (e.g. refuse to oversell), it MUST use a conditional request, "the REST equivalent to transactions": `GET` with `X-Firebase-ETag: true` → `PUT`/`DELETE` with `if-match: <etag>` → on `412 Precondition Failed`, take the ETag/value returned in the failure response and retry manually; "Realtime Database does not automatically retry conditional requests", so retries MUST be bounded (≤ 3 attempts) and MUST then throw ([Saving Data — Conditional Requests](https://firebase.google.com/docs/database/rest/save-data)).
- Conditional requests MUST NOT be attempted with `PATCH` (returns 400) and MUST supply exactly one ETag value ([REST API reference — Conditional Requests](https://firebase.google.com/docs/reference/rest/database), [Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- `X-Firebase-ETag` MUST be sent only where an ETag is needed (conditional flow): Realtime Database returns ETags only for requests carrying that header, which "reduces billing costs for standard requests" ([Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- Writes MUST fit the instance's default `writeSizeLimit` (`tiny`=1s/`small`=10s/`medium`=30s/`large`=60s target; new databases default to `large`); `writeSizeLimit=unlimited` MUST NOT be used — it allows payloads up to 256MB that can block subsequent requests and "writes cannot be canceled once they reach the server" ([REST API reference — writeSizeLimit](https://firebase.google.com/docs/reference/rest/database)).

#### 7. Kitchen polling read hygiene (5–10s refresh)

- Each poll tick MUST request ONE narrow, pre-scoped query path (e.g. open tickets via `orderBy` + `limitToLast`) and MUST NOT poll the root or a parent node — fetching a location retrieves all children ([Structure Your Database](https://firebase.google.com/docs/database/web/structure-data)).
- Every poll GET MUST send `timeout` with a value ≤ 10s (the poll interval): a read that exceeds the timeout terminates with HTTP 400, and the default maximum is 15 minutes — far too long to let a stuck read pile up behind a 5–10s loop ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data)).
- Every poll MUST bound its result set with `limitToFirst`/`limitToLast` ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data)).
- `shallow=true` MAY be used for keys-only/existence checks, but MUST NOT be combined with any other query parameter ([REST API reference — shallow](https://firebase.google.com/docs/reference/rest/database)).
- Change detection MUST be done in PHP by comparing successive payloads; conditional GETs MUST NOT be attempted — `if-match` is supported only on `PUT`/`DELETE`, and `GET` with `if-match` returns 400 ([REST API reference — Expected responses](https://firebase.google.com/docs/reference/rest/database)).
- Pollers MUST NOT rely on HTTP caches: the RTDB response headers documented in the official flow carry `Cache-Control: no-cache` ([Saving Data — Conditional Requests](https://firebase.google.com/docs/database/rest/save-data)); every tick performs a real request.
- If polling is ever replaced by SSE streaming, the client MUST handle the documented `cancel` event (rules revoked, or payload over the 512MB stream limit) and the `auth_revoked` event (expired credential) ([REST API reference — Streaming](https://firebase.google.com/docs/reference/rest/database)).
- Capacity: the single-instance ceiling that triggers sharding is 200,000 simultaneous connections and 1,000 write operations/second; poll-plus-write load MUST be monitored against it ([Scale with Multiple Databases](https://firebase.google.com/docs/database/usage/sharding)). If sharding is adopted, each query MUST target exactly one instance and MUST NOT share or duplicate data across instances ([Scale with Multiple Databases](https://firebase.google.com/docs/database/usage/sharding)).

#### 8. Error handling: fail loudly

- `RtdbService` MUST throw on every non-2xx response and MUST NOT return `null`, `[]`, `0`, or any placeholder on failure. The documented failure surface is: 400 (unparseable/too-large payload, invalid child name, missing query index, unrecognized server value, unsupported query combination, read/write timeouts), 401 (expired/invalid token or rule violation), 404, 412 (ETag mismatch outside the bounded conditional flow), 500, 503 ([REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database), [Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- Exceptions MUST carry the HTTP status and response body into Laravel's exception handler; `catch` blocks around `RtdbService` calls MUST NOT swallow the exception (empty catch / `catch {} then continue` is a review blocker).
- A 401 MUST be logged at error level and surfaced: per the error table it means an expired or invalid credential, a failed `access_token` authentication, or a rules violation — all configuration faults, not transient noise ([REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database)).
- Automatic retries MUST be limited to idempotent `GET`s. 503 alone MAY be retried with backoff even for writes, because it means "the request was not attempted"; 500 MUST NOT be retried for writes because the server-side outcome is unknown ([REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database)).
- A `print=silent` write MUST treat `204 No Content` as success and MUST NOT attempt to JSON-decode the empty body ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data), [REST API reference — print](https://firebase.google.com/docs/reference/rest/database)).
- A `412` inside the conditional flow MUST NOT be treated as a hard failure until the bounded retry budget (≤ 3, §6) is exhausted, after which it MUST throw with the last ETag/value attached ([Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- A 400 whose body names a missing query index MUST be treated as a deploy bug (rules file out of sync with code, §3), never worked around by client-side filtering ([Index your data](https://firebase.google.com/docs/database/security/indexing-data)).

---

All sources are official Firebase/Google documentation, accessed 2026-09-22.
