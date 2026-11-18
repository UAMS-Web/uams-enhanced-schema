Written against v1.0.2.

At the end of this walkthrough the plugin is running on a site, and you have found the `Physician` JSON-LD it wrote on one provider page and confirmed that the original inline block is gone.

1. **Install the plugin** as the [README](https://github.com/UAMS-Web/uams-enhanced-schema#installation) describes. As a regular plugin it needs activating; as a must-use plugin it loads on its own. Nothing appears in the admin either way: the plugin has no settings screen.
2. **Open a published provider page** on the site, and view the page source (not the inspector, which shows the page after scripts have run).
3. **Search the source for `application/ld+json`.** Near the end of the page, just before `</body>`, there is a block whose `"@type"` is `"Physician"`, followed by a `"BreadcrumbList"` block. The plugin writes both in the footer.
4. **Search the source for `UAMS Enhanced Schema: original schema replaced`.** That comment sits where the template's own inline schema block used to be. If you find the comment, replace mode removed the original; if you find an `"@context": "http://www.schema.org"` block instead, see [Choosing replace or safe mode](Choosing-replace-or-safe-mode.md).
5. **Check the block with a validator.** Paste the page URL, or the block itself, into the [Schema Markup Validator](https://validator.schema.org/) or Google's [Rich Results Test](https://search.google.com/test/rich-results). Each property the validator lists is described on [Schema by page type](Schema-by-page-type.md#provider-pages), with the field it was read from.

**Note:** a property is left out, rather than written empty, when its field is empty. A provider with no NPI, for example, has no `identifier`. That is expected.

## Going further

- [Schema by page type](Schema-by-page-type.md): what each of the other page types gets.
- [Adding FAQ schema to a page](Adding-FAQ-schema-to-a-page.md): an FAQPage block on a condition or treatment page.
- [Troubleshooting](Troubleshooting.md): when a block or a property is missing.
