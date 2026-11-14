<?php

/**
 * uams_breadcrumb_schema() builds a BreadcrumbList: the site, the post type's
 * archive, a parent location for child locations, and the current page.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

/**
 * Run the breadcrumb for a post and return the decoded list items.
 *
 * @param  array<string, mixed>  $fields  ACF values by field name.
 * @return list<array<string, mixed>>
 */
function breadcrumb_for(string $postType, array $fields = [], string $title = 'Page title'): array
{
    when('wp_json_encode')->alias('json_encode');
    when('get_post_type')->justReturn($postType);
    when('get_the_ID')->justReturn(42);
    when('get_the_title')->justReturn($title);
    when('get_field')->alias(static fn (string $name) => $fields[$name] ?? false);

    ob_start();
    uams_breadcrumb_schema();
    $out = (string) ob_get_clean();

    $json = (string) preg_replace('~^\s*<script[^>]*>|</script>\s*$~', '', $out);
    $schema = json_decode($json, true);

    expect($schema['@context'])->toBe('https://schema.org')
        ->and($schema['@type'])->toBe('BreadcrumbList');

    return $schema['itemListElement'];
}

it('lists the site, the archive and the page for each post type', function (string $type, string $label, string $url): void {
    $items = breadcrumb_for($type, [], 'The page');

    expect($items)->toBe([
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'UAMS Health', 'item' => 'https://uamshealth.com'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $label, 'item' => $url],
        ['@type' => 'ListItem', 'position' => 3, 'name' => 'The page'],
    ]);
})->with([
    'location' => ['location', 'Locations', 'https://uamshealth.com/location/'],
    'condition' => ['condition', 'Conditions', 'https://uamshealth.com/condition/'],
    'treatment' => ['treatment', 'Treatments', 'https://uamshealth.com/treatment/'],
    'expertise' => ['expertise', 'Areas of Expertise', 'https://uamshealth.com/expertise/'],
]);

it('skips the archive item for a post type it does not know', function (): void {
    $items = breadcrumb_for('post', [], 'A post');

    expect($items)->toBe([
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'UAMS Health', 'item' => 'https://uamshealth.com'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'A post'],
    ]);
});

it('adds the parent location for a child location', function (): void {
    when('get_post')->justReturn((object) ['post_title' => 'Main Campus']);
    when('get_permalink')->justReturn('https://uamshealth.com/location/main-campus/');

    $items = breadcrumb_for('location', ['location_parent' => true, 'location_parent_id' => 7], 'Clinic');

    expect(array_column($items, 'name'))->toBe(['UAMS Health', 'Locations', 'Main Campus', 'Clinic'])
        ->and($items[2])->toBe([
            '@type' => 'ListItem',
            'position' => 3,
            'name' => 'Main Campus',
            'item' => 'https://uamshealth.com/location/main-campus/',
        ])
        ->and($items[3]['position'])->toBe(4);
});

it('adds no parent location unless the flag, the id and the post are all present', function (array $fields, mixed $parent): void {
    when('get_post')->justReturn($parent);
    when('get_permalink')->justReturn('https://uamshealth.com/location/x/');

    $items = breadcrumb_for('location', $fields, 'Clinic');

    expect(array_column($items, 'name'))->toBe(['UAMS Health', 'Locations', 'Clinic']);
})->with([
    'flag off' => [['location_parent' => false, 'location_parent_id' => 7], (object) ['post_title' => 'P']],
    'no id' => [['location_parent' => true], (object) ['post_title' => 'P']],
    'post missing' => [['location_parent' => true, 'location_parent_id' => 7], null],
]);

it('names a provider from the display name and degrees', function (): void {
    when('get_term')->alias(static fn ($id) => (object) ['name' => ['1' => 'MD', '2' => 'PhD'][(string) $id]]);
    when('is_wp_error')->justReturn(false);

    $items = breadcrumb_for('provider', [
        'physician_first_name' => 'Ada',
        'physician_middle_name' => 'M.',
        'physician_last_name' => 'Lovelace',
        'physician_degree' => [1, 2],
    ]);

    expect($items)->toHaveCount(3)
        ->and($items[1]['name'])->toBe('Find a Doctor')
        ->and($items[1]['item'])->toBe('https://uamshealth.com/provider/')
        ->and($items[2])->toBe(['@type' => 'ListItem', 'position' => 3, 'name' => 'Ada M. Lovelace, MD, PhD']);
});

it('names a provider without a middle name or degrees', function (mixed $degrees): void {
    when('get_term')->justReturn(false);
    when('is_wp_error')->justReturn(false);

    $items = breadcrumb_for('provider', [
        'physician_first_name' => 'Ada',
        'physician_last_name' => 'Lovelace',
        'physician_degree' => $degrees,
    ]);

    // Pinned as-is: an empty middle name leaves two spaces between first and last.
    expect(end($items)['name'])->toBe('Ada  Lovelace');
})->with([
    'no degree field' => [false],
    'empty list' => [[]],
    'not a list' => ['MD'],
    'a term that does not resolve' => [[1]],
]);

it('skips a degree term that is an error', function (): void {
    when('get_term')->justReturn((object) ['name' => 'MD']);
    when('is_wp_error')->justReturn(true);

    $items = breadcrumb_for('provider', [
        'physician_first_name' => 'Ada',
        'physician_last_name' => 'Lovelace',
        'physician_degree' => [1],
    ]);

    expect(end($items)['name'])->toBe('Ada  Lovelace');
});

it('trims a provider name that has only a first name', function (): void {
    when('is_wp_error')->justReturn(false);

    $items = breadcrumb_for('provider', ['physician_first_name' => 'Ada']);

    expect(end($items)['name'])->toBe('Ada');
});
