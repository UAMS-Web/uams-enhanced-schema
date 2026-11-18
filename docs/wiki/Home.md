UAMS Enhanced Schema writes schema.org JSON-LD for the provider, location, condition and treatment pages of a WordPress site that runs the Find-a-Doc plugin, and an organization block for the home page. In its default mode it also removes the older inline schema those templates print, so each page carries one corrected block instead of two.

This wiki holds the documentation: a walkthrough, task guides, the reference and troubleshooting. The [README](https://github.com/UAMS-Web/uams-enhanced-schema#readme) keeps the overview, the requirements, installation and the development notes.

## Getting started

- [Installation (README)](https://github.com/UAMS-Web/uams-enhanced-schema#installation): add the plugin to a WordPress site.
- [Checking the schema on a page](Checking-the-schema-on-a-page.md): install the plugin, open a provider page and find the JSON-LD it wrote.

## Guides

- [Choosing replace or safe mode](Choosing-replace-or-safe-mode.md): decide whether the plugin removes the original inline schema.
- [Adding FAQ schema to a page](Adding-FAQ-schema-to-a-page.md): give a condition or treatment page an FAQPage block.

## Troubleshooting

- [Troubleshooting](Troubleshooting.md): one entry per symptom, with its cause and fix.

## Reference

- [Schema by page type](Schema-by-page-type.md): every block the plugin writes, the page that gets it, and the field each property comes from.
- [Configuration](Configuration.md): the `UAMS_SCHEMA_MODE` constant and the hooks the plugin uses.

## Project

- [Project](Project.md): where the plugin is developed and how to report a problem.
