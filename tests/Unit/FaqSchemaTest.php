<?php

/**
 * uams_maybe_faq_schema() reads an ACF repeater named `faq` or `faqs` and
 * uams_output_faq_schema() writes the FAQPage JSON-LD for the rows it finds.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

/**
 * Fake ACF repeater functions over the given rows, keyed by repeater name.
 *
 * @param  array<string, list<array<string, string>>>  $repeaters
 */
function fake_repeaters(array $repeaters): void
{
    $cursor = 0;
    $current = [];

    when('have_rows')->alias(static function (string $field) use ($repeaters, &$cursor): bool {
        return isset($repeaters[$field]) && $cursor < count($repeaters[$field]);
    });
    when('the_row')->alias(static function () use ($repeaters, &$cursor, &$current): void {
        $field = array_key_first(array_filter($repeaters, static fn (array $rows): bool => $rows !== []));
        $current = $repeaters[$field][$cursor++];
    });
    when('get_sub_field')->alias(static function (string $name) use (&$current) {
        return $current[$name] ?? false;
    });
}

/**
 * Run uams_maybe_faq_schema() and return the decoded questions, or null when nothing was written.
 *
 * @return list<array{name: string, text: string}>|null
 */
function faq_questions(?int $postId = 7): ?array
{
    when('wp_json_encode')->alias('json_encode');
    when('wp_strip_all_tags')->alias('strip_tags');
    when('get_the_ID')->justReturn(99);

    ob_start();
    uams_maybe_faq_schema($postId);
    $out = (string) ob_get_clean();

    if ($out === '') {
        return null;
    }

    $schema = json_decode((string) preg_replace('~^\s*<script[^>]*>|</script>\s*$~', '', $out), true);

    expect($schema['@context'])->toBe('https://schema.org')
        ->and($schema['@type'])->toBe('FAQPage');

    return array_map(
        static fn (array $q): array => ['name' => $q['name'], 'text' => $q['acceptedAnswer']['text']],
        $schema['mainEntity']
    );
}

it('reads the faq repeater', function (): void {
    fake_repeaters(['faq' => [
        ['question' => 'Q1', 'answer' => 'A1'],
        ['question' => 'Q2', 'answer' => 'A2'],
    ]]);

    expect(faq_questions())->toBe([
        ['name' => 'Q1', 'text' => 'A1'],
        ['name' => 'Q2', 'text' => 'A2'],
    ]);
});

it('falls back to the faqs repeater', function (): void {
    fake_repeaters(['faqs' => [['question' => 'Q', 'answer' => 'A']]]);

    expect(faq_questions())->toBe([['name' => 'Q', 'text' => 'A']]);
});

it('falls back to the faq_question and faq_answer sub-fields', function (): void {
    fake_repeaters(['faq' => [['faq_question' => 'Q', 'faq_answer' => 'A']]]);

    expect(faq_questions())->toBe([['name' => 'Q', 'text' => 'A']]);
});

it('strips tags from questions and answers', function (): void {
    fake_repeaters(['faq' => [['question' => '<b>Q</b>', 'answer' => '<p>A <a href="#">link</a></p>']]]);

    expect(faq_questions())->toBe([['name' => 'Q', 'text' => 'A link']]);
});

it('skips a row missing its question or its answer', function (array $row): void {
    fake_repeaters(['faq' => [$row, ['question' => 'Kept', 'answer' => 'Yes']]]);

    expect(faq_questions())->toBe([['name' => 'Kept', 'text' => 'Yes']]);
})->with([
    'no answer' => [['question' => 'Q']],
    'no question' => [['answer' => 'A']],
    'empty strings' => [['question' => '', 'answer' => '']],
]);

it('writes nothing when no row qualifies', function (): void {
    fake_repeaters(['faq' => [['question' => 'Q']]]);

    expect(faq_questions())->toBeNull();
});

it('writes nothing when the post has no repeater', function (): void {
    fake_repeaters([]);

    expect(faq_questions())->toBeNull();
});

it('uses the current post when no id is passed', function (): void {
    $asked = [];
    when('have_rows')->alias(static function (string $field, $postId) use (&$asked): bool {
        $asked[] = $postId;

        return false;
    });

    faq_questions(null);

    expect($asked)->toBe([99, 99]);
});

it('writes nothing for an empty list of questions', function (): void {
    when('wp_json_encode')->alias('json_encode');

    ob_start();
    uams_output_faq_schema([]);

    expect((string) ob_get_clean())->toBe('');
});
