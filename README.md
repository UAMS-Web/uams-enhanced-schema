# UAMS Enhanced Schema

A WordPress plugin that writes corrected schema.org JSON-LD for the provider, location, condition and treatment pages of a site running the Find-a-Doc plugin, and an organization block for the home page. In its default mode it also removes the older inline schema those templates print. It is a stopgap until the site moves to Statamic.

The documentation is in the [wiki](https://github.com/UAMS-Web/uams-enhanced-schema/wiki): a walkthrough, the two modes, FAQ schema, the reference for every block and troubleshooting.

## Requirements

- WordPress 6.8 or later.
- PHP 8.2 or later.
- The Find-a-Doc plugin and Advanced Custom Fields, whose post types, taxonomies and fields the schema is read from.

## Installation

The plugin is one file, `uams-enhanced-schema.php`. Install it either way:

- **As a plugin:** install the GitHub download into `wp-content/plugins/` and activate it.
- **As a must-use plugin:** copy `uams-enhanced-schema.php` into `wp-content/mu-plugins/`. It loads without activation.

It has no settings screen. To keep the original inline schema, see [Choosing replace or safe mode](https://github.com/UAMS-Web/uams-enhanced-schema/wiki/Choosing-replace-or-safe-mode) in the wiki.

## Tests

```bash
composer install
vendor/bin/pest
```

The Unit suite runs on Pest 5 (PHPUnit 13) under PHP 8.4.27, set through `config.platform.php` in `composer.json`. That is the development PHP, not the supported floor; the supported floor is PHP 8.2 (`require.php` in `composer.json` and `Requires PHP` in the plugin header).

The Integration suite boots WordPress through `wp-phpunit` and runs on PHPUnit 9.6, because WordPress' test suite supports PHPUnit 9 only. It has its own Composer setup in `tests/Integration/`:

```bash
composer setup:integration
composer test:integration
```

Run `composer setup:integration` before `composer stan` too: PHPStan reads the WordPress test classes from that install.

### Mutation testing

```bash
composer mutate
```

This runs `pest tests/Unit/*Test.php --mutate --covered-only --path=uams-enhanced-schema.php` and needs a coverage driver (PCOV or Xdebug). It takes several minutes. The test files are named explicitly because a child process with no test file named crashes in Patchwork's directory wrapper and is scored as killed. The plugin is loaded from `tests/Pest.php` rather than the bootstrap, since a file the bootstrap loads is never mutated. The glob expands in a POSIX shell; the Windows form is not yet measured.

Survivors that no test can tell apart from the original are annotated in the plugin file with `@pest-mutate-ignore` and a reason. Survivors in the condition and treatment provider and location blocks, and the unused physician `$treatments` list, are dead code tracked in #74, #75 and #70.


## CI

Pull requests run the shared chain in `UAMS-Web/uams-ci-workflows` through `.github/workflows/ci.yml`. The required check is `CI`.

## Credits

Developed and maintained by UAMS Web Services.

Developers:

- Josh Daugherty
- Todd McKee
- Brent Passmore
