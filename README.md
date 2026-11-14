# UAMS Enhanced Schema

## Tests

```bash
composer install
vendor/bin/pest
```

The Unit suite runs on Pest 5 (PHPUnit 13) under PHP 8.4.27, set through `config.platform.php` in `composer.json`. That is the development PHP, not the supported floor; the declared floor stays PHP 7.4.

The Integration suite boots WordPress through `wp-phpunit` and runs on PHPUnit 9.6, because WordPress' test suite supports PHPUnit 9 only. It has its own Composer setup in `tests/Integration/`:

```bash
composer setup:integration
composer test:integration
```


## Local CI

```bash
node .claude/skills/local-ci/local-ci.mjs --dry-run
node .claude/skills/local-ci/local-ci.mjs --all
```

Private repositories validate with this runner rather than billed Actions minutes.

## Credits

Developed and maintained by UAMS Web Services.

Developers:

- Josh Daugherty
- Todd McKee
- Brent Passmore
