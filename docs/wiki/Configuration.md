Written against v1.0.2.

The plugin has no settings screen and no options. It is configured by one constant, and it acts through three WordPress hooks.

## `UAMS_SCHEMA_MODE`

| | |
| --- | --- |
| Type | string |
| Default | `'replace'` |
| Values | `'replace'`: remove the original inline schema on provider and location pages. Anything else: remove nothing. |
| Where | `wp-config.php`, before WordPress loads the plugin |

```php
define('UAMS_SCHEMA_MODE', 'safe');
```

The plugin defines the constant as `'replace'` only when nothing has defined it first. The mode changes the removal and nothing else; [Choosing replace or safe mode](Choosing-replace-or-safe-mode.md) compares the two.

## Hooks the plugin uses

| Hook | When | What it does |
| --- | --- | --- |
| `wp_loaded` | replace mode only | starts an output buffer for the page |
| the output buffer | replace mode, on single provider and location pages | replaces each inline `application/ld+json` block whose `@context` is `http://www.schema.org` with the comment `UAMS Enhanced Schema: original schema replaced` |
| `wp_footer`, priority 99 | every page | writes the blocks for the current page, as listed on [Schema by page type](Schema-by-page-type.md) |

Blocks whose `@context` is `https://schema.org`, or that sit on any other page, are not touched by the buffer.

## Functions a template can call

| Function | What it writes |
| --- | --- |
| `uams_output_faq_schema(array $questions)` | an `FAQPage` block for a list of `['question' => ..., 'answer' => ...]` pairs; nothing for an empty list |
| `uams_maybe_faq_schema($post_id = null)` | the `FAQPage` block for a post's `faq` or `faqs` repeater, as condition and treatment pages get |

Every block is printed by `uams_output_jsonld()`, which encodes `<`, `>` and `&` in values so that a value cannot end the script element.

## Requirements

The plugin reads the post types, taxonomies and ACF fields of the Find-a-Doc plugin, so it does nothing useful on a site without them. The WordPress and PHP versions it needs are in the [README](https://github.com/UAMS-Web/uams-enhanced-schema#requirements).
