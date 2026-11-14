# UAMS Enhanced Schema

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


## CI

Pull requests run the shared chain in `UAMS-Web/uams-ci-workflows` through `.github/workflows/ci.yml`. The required check is `CI`.

## Credits

Developed and maintained by UAMS Web Services.

Developers:

- Josh Daugherty
- Todd McKee
- Brent Passmore
