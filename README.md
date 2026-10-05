# UAMS Enhanced Schema

## Tests

```bash
composer install
composer test:unit
```

PHP floor is 7.4, so the harness is PHPUnit 9 (Pest needs PHP 8.2+).


## Local CI

```bash
node .claude/skills/local-ci/local-ci.mjs --dry-run
node .claude/skills/local-ci/local-ci.mjs --all
```

Private repositories validate with this runner rather than billed Actions minutes.
