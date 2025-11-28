# Git Hooks Setup

This project uses Git hooks for automatic code formatting and quality checks before commits.

## Initial Setup

After cloning the repository, run:

```bash
# Install dependencies
npm install
composer install

# Configure Git to use .husky for hooks
git config core.hooksPath .husky
```

## What Hooks Do

### Pre-commit Hook

Automatically runs before every commit and:

1. **PHP Files** - Runs PHPCBF/PHPCS to auto-format and check WordPress Coding Standards
    - Auto-fixes: spacing, indentation, operators, brackets
    - Checks: PHPDoc comments, naming conventions, file structure
    - Standard: WordPress-Core, WordPress-Docs, WordPress-Extra
2. **JS/CSS/SCSS Files** - Runs Prettier to auto-format code
    - Standard: WordPress Prettier config

### What Gets Auto-Fixed

**PHP (PHPCBF):**

-   ✅ Spacing around operators (`$a+$b` → `$a + $b`)
-   ✅ Spacing in control structures (`if($x)` → `if ( $x )`)
-   ✅ Spacing in function parameters (`function($a,$b)` → `function( $a, $b )`)
-   ✅ Indentation (tabs for PHP)
-   ✅ Array formatting
-   ❌ PHPDoc comments (you must add manually)
-   ❌ Naming conventions (you must follow conventions)

**JS/CSS/SCSS (Prettier):**

-   ✅ Spacing, indentation, quotes
-   ✅ Semicolons, commas
-   ✅ Line breaks and formatting

### Skip Checks

If you need to skip checks temporarily:

```bash
# Skip PHP checks only
SKIP_PHPCBF=1 git commit -m "message"

# Skip all hooks (not recommended)
git commit --no-verify -m "message"
```

## Troubleshooting

If hooks don't run, check:

```bash
# Verify hooks path is set
git config --get core.hooksPath
# Should output: .husky

# Verify pre-commit is executable
ls -la .husky/pre-commit
# Should have -rwxr-xr-x permissions

# Test hook manually
bash .husky/pre-commit
```

## What Gets Formatted

-   **JS**: `src/**/*.js` - WordPress Prettier config
-   **CSS/SCSS**: `src/**/*.{css,scss}` - WordPress Prettier config
-   **PHP**: `app/**/*.php` - WordPress Coding Standards (WPCS)
