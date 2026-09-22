# PHP Coding Standards — Laravel 12 (PHP 8.2+)


Use these standards when writing, refactoring, or reviewing PHP code.

---

## 1. Typographic Standards & Code Style

Strictly adhere to the **PSR-12** and **PER Coding Style** specifications for all PHP implementations.

### 1.1 Casing Rules
*   `camelCase` for methods, class-scoped functions, and variables.
*   `snake_case` for procedural helper functions.
*   `PascalCase` for classes, interfaces, traits, and enums.
*   `SCREAMING_SNAKE_CASE` for class constants and global constants.

### 1.2 strict_types Declarations
Every PHP file **MUST** declare strict typing at the absolute top of the file as its first execution statement (following the opening `<?php` tag). This prevents PHP's runtime from performing silent, implicit scalar type coercion across boundaries.

```php
<?php

declare(strict_types=1);

namespace App\Core;
```

*   **Indentation:** Always use standard 4 spaces for indentation (no tabs).
*   **Braces:** Opening braces for classes and methods MUST go on a new line; closing braces must also go on a new line. Braces for control structures (e.g., `if`, `for`, `foreach`) must go on the same line.

---

## 2. Eradicating Primitive Obsession via Immutable Value Objects

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

## 3. Strict Type Declarations & Type Safety

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

## 4. Docstring Standards (PHPDoc)

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
