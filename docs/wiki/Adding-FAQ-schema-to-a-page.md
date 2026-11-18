Written against v1.0.2.

A condition or treatment page gets an `FAQPage` block, after its own `MedicalCondition` or `MedicalProcedure` block, when the post has an ACF repeater of questions and answers. No other page type reads the repeater.

1. **Give the post a repeater field named `faq`.** The plugin reads `faq` first and, only when that has no rows, `faqs`.
2. **Give the repeater two sub-fields:** the question as `question` (or `faq_question`) and the answer as `answer` (or `faq_answer`).
3. **Fill in at least one row** with both a question and an answer. A row missing either is skipped, and a repeater whose rows are all skipped writes no block.
4. **Open the page's source** and find the block whose `"@type"` is `"FAQPage"`. Each row is a `Question` with its `acceptedAnswer`. HTML in a question or an answer is stripped to plain text.

## From a template instead

A template can write the block for any list of questions by calling the function directly:

```php
uams_output_faq_schema([
    ['question' => '<question text>', 'answer' => '<answer text>'],
]);
```

An empty list writes nothing.

**Note:** the comment above the FAQ functions in the plugin file also mentions detecting questions from `<h3>` headings in the content. The code does not do that; only the repeater and a direct call write the block.

## Reference

- [Schema by page type](Schema-by-page-type.md#faq-block): the FAQPage block's properties.
- [Configuration](Configuration.md#functions-a-template-can-call): the functions a template can call.
