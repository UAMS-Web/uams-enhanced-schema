Written against v1.0.2.

The plugin runs in one of two modes, set by the `UAMS_SCHEMA_MODE` constant. The mode decides only one thing: whether the original inline schema on provider and location pages is removed.

| | Replace mode (default) | Safe mode |
| --- | --- | --- |
| Original inline schema on provider and location pages | removed | kept |
| The plugin's own blocks | written | written |
| Result on a provider or location page | the plugin's blocks only | the original block and the plugin's blocks |

**Note:** the comment at the top of the plugin file describes safe mode as adding only the BreadcrumbList, FAQPage and Organization blocks. The code does not do that: in safe mode every block on [Schema by page type](Schema-by-page-type.md) is still written, and only the removal is skipped. This page describes what the code does.

## Use replace mode

Replace mode is the default, so a site that does not define the constant is already in it.

1. Confirm that `wp-config.php` does not define `UAMS_SCHEMA_MODE`, or defines it as `'replace'`.
2. Open a provider page's source and find the comment `UAMS Enhanced Schema: original schema replaced` where the original block was.

## Use safe mode

Use safe mode when another plugin or the theme already depends on the original inline block, or while comparing the two blocks side by side.

1. Add this line to `wp-config.php`, above the line that loads `wp-settings.php`:

   ```php
   define('UAMS_SCHEMA_MODE', 'safe');
   ```

2. Open a provider page's source. The original block, with `"@context": "http://www.schema.org"`, is back, and the plugin's `Physician` block still follows it in the footer.

Any other string behaves as safe mode does: nothing is removed. The value must be a string; a `true`, `false` or number stops the plugin with a type error when it loads.

## Reference

- [Configuration](Configuration.md#uams_schema_mode): the constant and what reads it.
- [Schema by page type](Schema-by-page-type.md): the blocks written in both modes.
