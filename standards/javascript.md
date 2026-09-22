# JavaScript Coding Standards — vanilla browser JS, no build step


Use these standards when working in pure JavaScript environments without TypeScript compilation.

---

## 1. Strict Mode

All plain JavaScript modules **MUST** operate under strict mode to prevent silent runtime errors (such as writing to read-only properties or implicit global variables). Add the directive at the absolute top of the module or rely on ESM module standards:
```javascript
"use strict";
```

---

## 2. JSDoc Static Typing (checkJs)

To achieve strict static type checking and comprehensive IDE autocompletion in vanilla JavaScript without compiling files, write robust JSDoc block comments and enable TypeScript checks with `// @ts-check` at the top of the file.

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

## 3. Safe Boundaries

*   **No Prototype Mutation:** Never add properties or modify prototype chains of built-in global objects (e.g., `Array.prototype`, `Object.prototype`, `String.prototype`). This is a critical source of runtime vulnerabilities and third-party library conflicts.
*   **Immutable Composition:** Prefer shallow copying and array transformations (`map`, `filter`, `reduce`) over in-place index mutations to prevent unexpected state leaks.
