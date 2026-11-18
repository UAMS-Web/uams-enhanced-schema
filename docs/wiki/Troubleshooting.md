Written against v1.0.2.

One entry per symptom, as you meet it: what you see, its cause, and the fix.

- [A provider or location page has two schema blocks](#a-provider-or-location-page-has-two-schema-blocks)
- [The original block is still there in replace mode](#the-original-block-is-still-there-in-replace-mode)
- [A provider has no aggregateRating](#a-provider-has-no-aggregaterating)
- [A condition or treatment page has no FAQPage block](#a-condition-or-treatment-page-has-no-faqpage-block)
- [A location lists fewer providers than its page shows](#a-location-lists-fewer-providers-than-its-page-shows)
- [A location has no opening hours](#a-location-has-no-opening-hours)
- [Fixed](#fixed)

## A provider or location page has two schema blocks

The page source holds an inline block with `"@context": "http://www.schema.org"` and, near the end, the plugin's `Physician` or `MedicalClinic` block.

**Cause:** the site is in safe mode. Safe mode keeps the original block and still writes the plugin's own.

**Fix:** remove the `UAMS_SCHEMA_MODE` definition from `wp-config.php`, or set it to `'replace'`. See [Choosing replace or safe mode](Choosing-replace-or-safe-mode.md).

## The original block is still there in replace mode

The site is in replace mode, and a page still carries the template's own inline block.

**Cause:** the buffer removes only blocks written as `<script type="application/ld+json">` whose `@context` is exactly `http://www.schema.org`, and only on single provider and location pages. A block with another context (`https://schema.org`, or no `www`), with more attributes on the script tag, or on another page type is left alone. A cache that stored the page before the plugin ran also serves the old markup.

**Fix:** clear the page cache first. If the block is still there, compare its opening tag and `@context` with the pattern above; a template that writes it differently needs the pattern in the plugin changed, so [report it](https://github.com/UAMS-Web/uams-enhanced-schema/issues).

## A provider has no aggregateRating

The `Physician` block has no `aggregateRating`, although the provider has ratings.

**Cause:** the rating is written only when all of these hold: the provider has an NPI, the stored Press Ganey data reports a `200` status, the provider has at least 30 ratings, and the data carries an overall rating value.

**Fix:** none in the plugin when the provider has fewer than 30 ratings; that threshold is deliberate. Otherwise check the NPI field and that the Press Ganey data on the post is current.

## A condition or treatment page has no FAQPage block

The page shows questions and answers, but its source has no `FAQPage` block.

**Cause:** the block is built only from an ACF repeater named `faq` or `faqs`, with `question` and `answer` (or `faq_question` and `faq_answer`) sub-fields. Questions written into the page content, under headings, are not read.

**Fix:** move the questions into the repeater, as [Adding FAQ schema to a page](Adding-FAQ-schema-to-a-page.md) describes.

## A location lists fewer providers than its page shows

The `availableService` list on a `MedicalClinic` block is shorter than the list of providers on the page.

**Cause:** the list holds only published providers, and at most 50 of them.

**Fix:** none needed when the location has more than 50 providers. Otherwise check that the missing providers are published.

## A location has no opening hours

The `MedicalClinic` block has no `openingHours`.

**Cause:** the location's hours are marked as variable, every day is marked closed, or a row is missing its day, opening time or closing time.

**Fix:** fill in the hours rows completely, or accept that a location with variable hours publishes none.

## Fixed

Problems fixed in a release stay here, with the release that fixed them, instead of being deleted.
