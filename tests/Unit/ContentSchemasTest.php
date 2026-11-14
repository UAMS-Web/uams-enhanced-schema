<?php

/**
 * uams_condition_schema() and uams_treatment_schema() are near twins: each writes
 * a name, description, alternate names, a MedlinePlus code and related posts for
 * one post type, then hands the page to the FAQ writer. Each test pins what the
 * writers do today.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\expect as expectCall;
use function Brain\Monkey\Functions\when;

/**
 * Run a content schema writer against a fake WordPress and return the decoded schema.
 *
 * Options: `fields` (ACF values by post id then field name), `terms` (degree terms by id;
 * `error` stands for a WP_Error), `status` and `titles` (by post id), `excerpt`, `content`.
 *
 * @param  array<string, mixed>  $o
 * @return array<string, mixed>
 */
function content_schema_for(string $writer, array $o = []): array
{
    $fields = $o['fields'] ?? [];
    $terms = $o['terms'] ?? [];
    $status = $o['status'] ?? [];
    $titles = $o['titles'] ?? [];

    when('wp_json_encode')->alias('json_encode');
    when('wp_strip_all_tags')->alias('strip_tags');
    when('get_the_ID')->justReturn(10);
    when('get_the_title')->alias(static fn ($id = null): string => $id === null ? 'The page' : ($titles[$id] ?? "Title $id"));
    when('get_permalink')->alias(static fn ($id): string => "https://uamshealth.com/p/$id/");
    when('get_the_excerpt')->justReturn($o['excerpt'] ?? '');
    when('get_the_content')->justReturn($o['content'] ?? '');
    when('get_post_status')->alias(static fn ($id) => $status[$id] ?? false);
    when('is_wp_error')->alias(static fn ($thing): bool => $thing === 'error');
    when('get_term')->alias(static fn ($id, string $taxonomy) => $terms[$id] ?? false);
    when('get_field')->alias(static function (string $name, $id = null) use ($fields) {
        $set = $fields[$id] ?? [];

        return array_key_exists($name, $set) ? $set[$name] : false;
    });
    when('uams_maybe_faq_schema')->justReturn();

    ob_start();
    $writer();
    $out = (string) ob_get_clean();

    $schema = json_decode((string) preg_replace('~^\s*<script[^>]*>|</script>\s*$~', '', $out), true);

    expect($schema['@context'])->toBe('https://schema.org');

    return $schema;
}

/**
 * The two writers with the names of the fields each one reads.
 *
 * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
 */
function content_writers(): array
{
    return [
        'condition' => ['uams_condition_schema', 'MedicalCondition', 'condition_alternate', 'condition_physicians', 'condition_locations'],
        'treatment' => ['uams_treatment_schema', 'MedicalProcedure', 'treatment_procedure_alternate', 'treatment_procedure_physicians', 'treatment_procedure_locations'],
    ];
}

const CONTENT_PART_OF = ['@type' => 'MedicalOrganization', 'name' => 'UAMS Health', 'url' => 'https://uamshealth.com'];

it('writes the fixed properties for a page with nothing filled in', function (string $writer, string $type): void {
    expect(content_schema_for($writer))->toBe([
        '@context' => 'https://schema.org',
        '@type' => $type,
        'name' => 'The page',
        'url' => 'https://uamshealth.com/p/10/',
        'isPartOf' => CONTENT_PART_OF,
    ]);
})->with(content_writers());

it('describes the page with the excerpt, stripped of tags', function (string $writer): void {
    $schema = content_schema_for($writer, ['excerpt' => '<p>An <b>excerpt</b></p>', 'content' => 'Body']);

    expect($schema['description'])->toBe('An excerpt');
})->with(content_writers());

it('falls back to the content, cut to 300 characters', function (string $writer): void {
    $schema = content_schema_for($writer, ['content' => '<p>'.str_repeat('a', 400).'</p>']);

    expect($schema['description'])->toBe(str_repeat('a', 297).'...');
})->with(content_writers());

it('keeps short content whole', function (string $writer): void {
    $schema = content_schema_for($writer, ['content' => '<i>Brief</i>']);

    expect($schema['description'])->toBe('Brief');
})->with(content_writers());

it('lists alternate names without commas and skips empty ones', function (string $writer, string $type, string $field): void {
    $schema = content_schema_for($writer, ['fields' => [10 => [$field => [
        ['text' => 'Heart, attack'],
        ['text' => ''],
        ['other' => 'x'],
        ['text' => 'MI'],
    ]]]]);

    expect($schema['alternateName'])->toBe(['Heart attack', 'MI']);
})->with(content_writers());

it('writes no alternate names for an empty list or a field that is not a list', function (string $writer, string $type, string $field, mixed $value): void {
    $schema = content_schema_for($writer, ['fields' => [10 => [$field => $value]]]);

    expect($schema)->not->toHaveKey('alternateName');
})->with(function (): array {
    $cases = [];
    foreach (content_writers() as $name => $writer) {
        $cases["$name, only empty rows"] = [$writer[0], $writer[1], $writer[2], [['text' => '']]];
        $cases["$name, not a list"] = [$writer[0], $writer[1], $writer[2], 'x'];
    }

    return $cases;
});

it('writes a MedlinePlus code and link for a topic or an encyclopedia article', function (string $writer, string $kind, string $path): void {
    $schema = content_schema_for($writer, ['fields' => [10 => ['medline_code_type' => $kind, 'medline_code_id' => 'a b']]]);

    expect($schema['code'])->toBe(['@type' => 'MedicalCode', 'codingSystem' => 'MedlinePlus', 'codeValue' => 'a b'])
        ->and($schema['sameAs'])->toBe("https://medlineplus.gov/{$path}a%20b.htm");
})->with(function (): array {
    $cases = [];
    foreach (content_writers() as $name => $writer) {
        $cases["$name, topic"] = [$writer[0], 'topic', ''];
        $cases["$name, article"] = [$writer[0], 'article', 'ency/article/'];
    }

    return $cases;
});

it('writes no MedlinePlus code without a type, with the none type, or without a code', function (string $writer, array $set): void {
    $schema = content_schema_for($writer, ['fields' => [10 => $set]]);

    expect($schema)->not->toHaveKeys(['code', 'sameAs']);
})->with(function (): array {
    $cases = [];
    foreach (content_writers() as $name => $writer) {
        $cases["$name, no type"] = [$writer[0], ['medline_code_id' => '123']];
        $cases["$name, none"] = [$writer[0], ['medline_code_type' => 'none', 'medline_code_id' => '123']];
        $cases["$name, no code"] = [$writer[0], ['medline_code_type' => 'topic']];
    }

    return $cases;
});

it('hands the page to the FAQ writer after its own schema', function (string $writer): void {
    when('wp_json_encode')->alias('json_encode');
    when('get_the_ID')->justReturn(10);
    when('get_the_title')->justReturn('The page');
    when('get_permalink')->justReturn('https://uamshealth.com/p/10/');
    when('get_the_excerpt')->justReturn('');
    when('get_the_content')->justReturn('');
    when('get_field')->justReturn(false);
    expectCall('uams_maybe_faq_schema')->once()->with(10);

    ob_start();
    $writer();
    ob_end_clean();
})->with(array_map(static fn (array $w): array => [$w[0]], content_writers()));

it('lists published treatments as possible treatments of a condition', function (): void {
    $schema = content_schema_for('uams_condition_schema', [
        'fields' => [10 => ['condition_treatments' => [31, 32, 33]]],
        'status' => [31 => 'publish', 32 => 'draft', 33 => 'publish'],
        'titles' => [31 => 'Stent', 33 => 'Bypass'],
    ]);

    expect($schema['possibleTreatment'])->toBe([
        ['@type' => 'MedicalTherapy', 'name' => 'Stent', 'url' => 'https://uamshealth.com/p/31/'],
        ['@type' => 'MedicalTherapy', 'name' => 'Bypass', 'url' => 'https://uamshealth.com/p/33/'],
    ]);
});

it('writes no possible treatments when none is published or the field is not a list', function (mixed $ids): void {
    $schema = content_schema_for('uams_condition_schema', [
        'fields' => [10 => ['condition_treatments' => $ids]],
        'status' => [31 => 'draft'],
    ]);

    expect($schema)->not->toHaveKey('possibleTreatment');
})->with(['unpublished' => [[31]], 'not a list' => ['31']]);

it('writes neither providers nor locations for a condition', function (): void {
    $schema = content_schema_for('uams_condition_schema', [
        'fields' => [
            10 => ['condition_physicians' => [101, 102], 'condition_locations' => [201]],
            101 => ['physician_degree' => [1, 2]],
        ],
        'status' => [101 => 'publish', 102 => 'draft', 201 => 'publish'],
        'terms' => [1 => (object) ['name' => 'MD'], 2 => 'error'],
    ]);

    expect(array_keys($schema))->toBe(['@context', '@type', 'name', 'url', 'isPartOf']);
});

it('lists published conditions as indications of a treatment', function (): void {
    $schema = content_schema_for('uams_treatment_schema', [
        'fields' => [10 => ['treatment_conditions' => [21, 22, 23]]],
        'status' => [21 => 'publish', 22 => 'draft', 23 => 'publish'],
        'titles' => [21 => 'Asthma', 23 => 'Angina'],
    ]);

    expect($schema['indication'])->toBe([
        ['@type' => 'MedicalCondition', 'name' => 'Asthma', 'url' => 'https://uamshealth.com/p/21/'],
        ['@type' => 'MedicalCondition', 'name' => 'Angina', 'url' => 'https://uamshealth.com/p/23/'],
    ]);
});

it('writes no indications when none is published or the field is not a list', function (mixed $ids): void {
    $schema = content_schema_for('uams_treatment_schema', [
        'fields' => [10 => ['treatment_conditions' => $ids]],
        'status' => [21 => 'draft'],
    ]);

    expect($schema)->not->toHaveKey('indication');
})->with(['unpublished' => [[21]], 'not a list' => ['21']]);

it('lists published locations of a treatment, at most the first 20', function (): void {
    $ids = range(201, 225);

    $schema = content_schema_for('uams_treatment_schema', [
        'fields' => [10 => ['treatment_procedure_locations' => $ids]],
        'status' => array_replace(array_fill_keys($ids, 'publish'), [202 => 'draft']),
    ]);

    expect($schema['availableService'])->toHaveCount(19)
        ->and($schema['availableService'][0])->toBe(['@type' => 'MedicalClinic', 'name' => 'Title 201', 'url' => 'https://uamshealth.com/p/201/'])
        ->and($schema['availableService'][18]['url'])->toBe('https://uamshealth.com/p/220/');
});

it('writes no locations for a treatment when none is published or the field is not a list', function (mixed $ids): void {
    $schema = content_schema_for('uams_treatment_schema', [
        'fields' => [10 => ['treatment_procedure_locations' => $ids]],
        'status' => [201 => 'draft'],
    ]);

    expect($schema)->not->toHaveKey('availableService');
})->with(['unpublished' => [[201]], 'not a list' => ['201']]);

it('writes no providers for a treatment', function (): void {
    $schema = content_schema_for('uams_treatment_schema', [
        'fields' => [
            10 => ['treatment_procedure_physicians' => [101, 102]],
            101 => ['physician_degree' => [1, 2]],
        ],
        'status' => [101 => 'publish', 102 => 'draft'],
        'terms' => [1 => (object) ['name' => 'MD'], 2 => 'error'],
    ]);

    expect(array_keys($schema))->toBe(['@context', '@type', 'name', 'url', 'isPartOf']);
});
