# Bash Coding Standards


Use these standards when writing, refactoring, or reviewing shell scripts.

---

## 1. Safety & Robustness Gates

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

## 2. Portable Shebangs

Avoid hardcoding path binaries (like `#!/bin/bash` or `#!/bin/sh`) which differ across operating systems (macOS, Linux, BSD). Always resolve standard shell binaries through `/usr/bin/env` for maximum portability:
```bash
#!/usr/bin/env bash
```

---

## 3. Quoting and Word Splitting

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

## 4. Conditional Evaluators

Prefer the modern, robust double-bracket conditional evaluator `[[ ... ]]` over legacy `[ ... ]` or `test` commands. It supports safer operators (no word splitting inside brackets) and cleaner regex matching.

```bash
### ❌ FAIL (Legacy single bracket)
if [ $name = "admin" ]; then ... fi

### ✅ PASS (Modern double bracket)
if [[ "$name" == "admin" ]]; then ... fi
```

---

## 5. Standardized Error Logging & Exit Codes

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

## 6. Static Analysis (ShellCheck)

All shell scripts **MUST** pass strict static analysis checks via `shellcheck` with zero warnings before declaring a task complete.

---

## 7. Casing Conventions

*   Environment variables and exported constants: `UPPER_SNAKE_CASE`.
*   Functions and locals: `snake_case`.
