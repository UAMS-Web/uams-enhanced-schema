<?php

/**
 * uams_location_schema() reads a location's ACF fields (or its parent's), its
 * hours group, related conditions and treatments, providers and closing status,
 * and writes the MedicalClinic JSON-LD. Each test pins what the writer does today.
 */

declare(strict_types=1);

use UamsEnhancedSchema\Tests\Support\FakeWpQuery;

use function Brain\Monkey\Functions\when;

if (! class_exists('WP_Query', false)) {
    class_alias(FakeWpQuery::class, 'WP_Query');
}

/**
 * Run uams_location_schema() against a fake WordPress and return the decoded schema.
 *
 * Options: `fields` (ACF values by post id then field name), `terms` (by taxonomy then
 * term id; `error` stands for a WP_Error), `parent` (what get_post() finds for the parent),
 * `thumbs` (post id to attachment id), `attachments` (attachment id to URL), `queries`
 * (post titles a conditions or treatments query finds, by post type), `status` and
 * `titles` (by post id), `page_title`.
 *
 * @param  array<string, mixed>  $o
 * @return array<string, mixed>
 */
function location_schema_for(array $o = []): array
{
    $fields = $o['fields'] ?? [];
    $terms = $o['terms'] ?? [];
    $status = $o['status'] ?? [];
    $titles = $o['titles'] ?? [];
    $thumbs = $o['thumbs'] ?? [];
    $attachments = $o['attachments'] ?? [];

    FakeWpQuery::reset();
    FakeWpQuery::$titles = $o['queries'] ?? [];

    when('wp_json_encode')->alias('json_encode');
    when('wp_strip_all_tags')->alias('strip_tags');
    when('wp_reset_postdata')->justReturn();
    when('get_the_ID')->justReturn(10);
    when('get_post')->justReturn($o['parent'] ?? false);
    when('get_permalink')->alias(static fn ($id): string => "https://uamshealth.com/p/$id/");
    when('get_the_title')->alias(static fn ($id = null): string => $id === null ? ($o['page_title'] ?? 'Clinic') : ($titles[$id] ?? "Title $id"));
    when('get_stylesheet_directory_uri')->justReturn('https://uamshealth.com/theme');
    when('is_wp_error')->alias(static fn ($thing): bool => $thing === 'error');
    when('get_term')->alias(static fn ($id, string $taxonomy) => $terms[$taxonomy][$id] ?? false);
    when('get_post_status')->alias(static fn ($id) => $status[$id] ?? false);
    when('get_post_thumbnail_id')->alias(static fn ($id) => $thumbs[$id] ?? 0);
    when('wp_get_attachment_image_url')->alias(static fn ($id, $size = null) => $size === 'large' ? ($attachments[$id] ?? false) : false);
    when('get_field')->alias(static function (string $name, $id = null) use ($fields) {
        $set = $fields[$id] ?? [];

        return array_key_exists($name, $set) ? $set[$name] : false;
    });

    ob_start();
    uams_location_schema();
    $out = (string) ob_get_clean();

    $schema = json_decode((string) preg_replace('~^\s*<script[^>]*>|</script>\s*$~', '', $out), true);

    expect($schema['@context'])->toBe('https://schema.org')
        ->and($schema['@type'])->toBe('MedicalClinic');

    return $schema;
}

/**
 * The fields of location 10 with an hours group set.
 *
 * @param  array<string, mixed>  $group
 * @return array<string, mixed>
 */
function location_with_hours(array $group): array
{
    return ['fields' => [10 => ['location_hours_group' => $group]]];
}

const LOCATION_ALL_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

it('writes the fixed properties for a location with nothing filled in', function (): void {
    $schema = location_schema_for(['page_title' => 'Main Clinic']);

    expect($schema)->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'MedicalClinic',
        'name' => 'Main Clinic',
        'url' => 'https://uamshealth.com/p/10/',
        'logo' => 'https://uamshealth.com/theme/assets/svg/uams-logo_health_horizontal_dark_386x50.png',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '',
            'addressLocality' => '',
            'addressRegion' => '',
            'postalCode' => '',
            'addressCountry' => 'US',
        ],
        'isPartOf' => ['@type' => 'MedicalOrganization', 'name' => 'UAMS Health', 'url' => 'https://uamshealth.com'],
    ]);
});

it('writes the address, with the second line from address_2 when there is no building or suite', function (): void {
    $schema = location_schema_for(['fields' => [10 => [
        'location_address_1' => '4301 W Markham',
        'location_address_2' => 'Room 4',
        'location_city' => 'Little Rock',
        'location_state' => 'AR',
        'location_zip' => '72205',
    ]]]);

    expect($schema['address'])->toBe([
        '@type' => 'PostalAddress',
        'streetAddress' => '4301 W Markham Room 4',
        'addressLocality' => 'Little Rock',
        'addressRegion' => 'AR',
        'postalCode' => '72205',
        'addressCountry' => 'US',
    ]);
});

it('prefers the building and suite over address_2', function (): void {
    $schema = location_schema_for([
        'fields' => [10 => [
            'location_address_1' => '4301 W Markham',
            'location_building' => 7,
            'location_suite' => 'Suite 200',
            'location_address_2' => 'Ignored',
        ]],
        'terms' => ['building' => [7 => (object) ['name' => 'Jones Building', 'slug' => 'jones']]],
    ]);

    expect($schema['address']['streetAddress'])->toBe('4301 W Markham Jones Building Suite 200');
});

it('ignores the placeholder building and a building term that does not resolve', function (mixed $term): void {
    $schema = location_schema_for([
        'fields' => [10 => ['location_address_1' => '1 Main', 'location_building' => 7, 'location_suite' => 'Suite 9']],
        'terms' => ['building' => [7 => $term]],
    ]);

    expect($schema['address']['streetAddress'])->toBe('1 Main Suite 9');
})->with([
    'placeholder' => [(object) ['name' => 'None', 'slug' => '_none']],
    'error' => ['error'],
    'missing' => [false],
]);

it('reads the address, map and phone source from the parent when a child location has a parent post', function (): void {
    $schema = location_schema_for([
        'fields' => [
            10 => ['location_parent' => true, 'location_parent_id' => 60, 'location_city' => 'Child City', 'location_phone' => '5015550100'],
            60 => ['location_city' => 'Parent City', 'location_map' => ['lat' => 1.5, 'lng' => 2.5]],
        ],
        'parent' => (object) ['ID' => 60],
    ]);

    expect($schema['address']['addressLocality'])->toBe('Parent City')
        ->and($schema['geo']['latitude'])->toBe(1.5)
        ->and($schema['telephone'])->toBe('+1-501-555-0100');
});

it('keeps the own address unless the flag, the id and the parent post are all present', function (array $set, mixed $parent): void {
    $schema = location_schema_for([
        'fields' => [10 => $set + ['location_city' => 'Child City'], 60 => ['location_city' => 'Parent City']],
        'parent' => $parent,
    ]);

    expect($schema['address']['addressLocality'])->toBe('Child City');
})->with([
    'flag only' => [['location_parent' => true], (object) ['ID' => 60]],
    'id only' => [['location_parent_id' => 60], (object) ['ID' => 60]],
    'parent post missing' => [['location_parent' => true, 'location_parent_id' => 60], false],
]);

it('writes the geo coordinates only when both are present', function (mixed $map, bool $written): void {
    $schema = location_schema_for(['fields' => [10 => ['location_map' => $map]]]);

    if ($written) {
        expect($schema['geo'])->toBe(['@type' => 'GeoCoordinates', 'latitude' => 34.7, 'longitude' => -92.3]);

        return;
    }

    expect($schema)->not->toHaveKey('geo');
})->with([
    'both' => [['lat' => 34.7, 'lng' => -92.3], true],
    'latitude only' => [['lat' => 34.7], false],
    'longitude only' => [['lng' => -92.3], false],
    'no map' => [false, false],
]);

it('writes the featured image of the address post', function (): void {
    $schema = location_schema_for([
        'thumbs' => [10 => 5],
        'attachments' => [5 => 'https://uamshealth.com/uploads/clinic.jpg'],
    ]);

    expect($schema['image'])->toBe('https://uamshealth.com/uploads/clinic.jpg');
});

it('writes no image when the location has no featured image', function (): void {
    expect(location_schema_for())->not->toHaveKey('image');
});

it('takes the image from the parent unless the child overrides it with its own featured image', function (array $set, string $expected): void {
    $schema = location_schema_for([
        'fields' => [10 => ['location_parent' => true, 'location_parent_id' => 60] + $set],
        'parent' => (object) ['ID' => 60],
        'thumbs' => [10 => 5, 60 => 6],
        'attachments' => [5 => 'child.jpg', 6 => 'parent.jpg'],
    ]);

    expect($schema['image'])->toBe($expected);
})->with([
    'both override flags' => [['location_image_override_parent' => true, 'location_image_override_parent_featured' => true], 'child.jpg'],
    'no override' => [[], 'parent.jpg'],
    'override without featured' => [['location_image_override_parent' => true], 'parent.jpg'],
    'featured without override' => [['location_image_override_parent_featured' => true], 'parent.jpg'],
]);

it('does not honor the image override on a location with no parent', function (): void {
    $schema = location_schema_for([
        'fields' => [10 => ['location_image_override_parent' => true, 'location_image_override_parent_featured' => true]],
        'thumbs' => [10 => 5],
        'attachments' => [5 => 'own.jpg'],
    ]);

    expect($schema['image'])->toBe('own.jpg');
});

it('describes the location with the short description, stripped of tags', function (): void {
    $schema = location_schema_for(['fields' => [10 => [
        'location_short_desc' => '<p>Short <b>text</b></p>',
        'location_about' => 'Long',
    ]]]);

    expect($schema['description'])->toBe('Short text');
});

it('falls back to the about text, cut to 250 characters', function (): void {
    $schema = location_schema_for(['fields' => [10 => ['location_about' => '<p>'.str_repeat('a', 300).'</p>']]]);

    expect($schema['description'])->toBe(str_repeat('a', 247).'...');
});

it('keeps a short about text whole', function (): void {
    $schema = location_schema_for(['fields' => [10 => ['location_about' => '<i>Brief</i>']]]);

    expect($schema['description'])->toBe('Brief');
});

it('formats the phone number', function (): void {
    $schema = location_schema_for(['fields' => [10 => ['location_phone' => '(501) 555-0100']]]);

    expect($schema['telephone'])->toBe('+1-501-555-0100');
});

it('writes weekly opening hours, skipping closed days and incomplete rows', function (): void {
    $schema = location_schema_for(location_with_hours(['location_hours' => [
        ['day' => 'Monday', 'open' => '8:00 am', 'close' => '5:00 pm'],
        ['day' => 'Mon - Fri', 'open' => '9:30 am', 'close' => '4:15 pm'],
        ['day' => 'Saturday', 'open' => '1:00 pm', 'close' => '11:45 pm'],
        ['day' => 'Tuesday', 'open' => '8:00 am', 'close' => '5:00 pm', 'closed' => true],
        ['day' => 'Wednesday', 'open' => '8:00 am'],
        ['day' => 'Thursday', 'close' => '5:00 pm'],
        ['day' => 'Someday', 'open' => '8:00 am', 'close' => '5:00 pm'],
        ['open' => '8:00 am', 'close' => '5:00 pm'],
    ]]));

    expect($schema['openingHours'])->toBe(['Mo 08:00-17:00', 'Mo-Fr 09:30-16:15', 'Sa 13:00-23:45']);
});

it('writes round-the-clock hours for a 24/7 location', function (): void {
    $schema = location_schema_for(location_with_hours([
        'location_24_7' => true,
        'location_hours' => [['day' => 'Monday', 'open' => '8:00 am', 'close' => '5:00 pm']],
    ]));

    expect($schema['openingHours'])->toBe(['Mo-Su 00:00-23:59']);
});

it('writes no hours when they vary, even for a 24/7 or modified-hours location', function (): void {
    $schema = location_schema_for(location_with_hours([
        'location_hours_variable' => true,
        'location_24_7' => true,
        'location_modified_hours' => true,
        'location_modified_hours_group' => [['location_modified_hours_24_7' => true]],
    ]));

    expect($schema)->not->toHaveKeys(['openingHours', 'openingHoursSpecification']);
});

it('writes no hours when the hours are not a list or there is no hours group', function (mixed $group): void {
    $schema = location_schema_for(['fields' => [10 => ['location_hours_group' => $group]]]);

    expect($schema)->not->toHaveKey('openingHours');
})->with([
    'not a list' => [['location_hours' => 'closed']],
    'empty group' => [[]],
    'no group' => [false],
]);

it('writes a 24/7 modified-hours specification with its dates', function (): void {
    $schema = location_schema_for(location_with_hours([
        'location_modified_hours' => true,
        'location_modified_hours_start_date' => '2030-12-24',
        'location_modified_hours_end' => true,
        'location_modified_hours_end_date' => '2030-12-26',
        'location_modified_hours_group' => [['location_modified_hours_24_7' => true]],
    ]));

    expect($schema['openingHoursSpecification'])->toBe([[
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => LOCATION_ALL_DAYS,
        'opens' => '00:00',
        'closes' => '23:59',
        'validFrom' => '2030-12-24',
        'validThrough' => '2030-12-26',
    ]]);
});

it('leaves the end date out unless the end is known and dated', function (array $set): void {
    $schema = location_schema_for(location_with_hours([
        'location_modified_hours' => true,
        'location_modified_hours_group' => [['location_modified_hours_24_7' => true]],
    ] + $set));

    expect($schema['openingHoursSpecification'][0])->not->toHaveKey('validThrough');
})->with([
    'end not known' => [['location_modified_hours_end_date' => '2030-12-26']],
    'no end date' => [['location_modified_hours_end' => true]],
]);

it('leaves the start date out when there is none', function (): void {
    $schema = location_schema_for(location_with_hours([
        'location_modified_hours' => true,
        'location_modified_hours_group' => [['location_modified_hours_24_7' => true]],
    ]));

    expect($schema['openingHoursSpecification'][0])->not->toHaveKey('validFrom');
});

it('writes a specification per modified-hours row', function (): void {
    $schema = location_schema_for(location_with_hours([
        'location_modified_hours' => true,
        'location_modified_hours_start_date' => '2030-07-01',
        'location_modified_hours_end' => true,
        'location_modified_hours_end_date' => '2030-07-05',
        'location_modified_hours_group' => [[
            'location_modified_hours_times' => [
                ['location_modified_hours_day' => 'Mon - Fri', 'location_modified_hours_open' => '9:00 am', 'location_modified_hours_close' => '3:00 pm'],
                ['location_modified_hours_day' => 'Saturday', 'location_modified_hours_closed' => true],
            ],
        ]],
    ]));

    expect($schema['openingHoursSpecification'])->toBe([
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'opens' => '09:00',
            'closes' => '15:00',
            'validFrom' => '2030-07-01',
            'validThrough' => '2030-07-05',
        ],
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'Saturday',
            'opens' => '00:00',
            'closes' => '00:00',
            'validFrom' => '2030-07-01',
            'validThrough' => '2030-07-05',
        ],
    ]);
});

it('writes the modified hours of an afternoon opening in 24-hour time', function (): void {
    $schema = location_schema_for(location_with_hours([
        'location_modified_hours' => true,
        'location_modified_hours_group' => [[
            'location_modified_hours_times' => [
                ['location_modified_hours_day' => 'Sunday', 'location_modified_hours_open' => '1:00 pm', 'location_modified_hours_close' => '11:30 pm'],
            ],
        ]],
    ]));

    expect($schema['openingHoursSpecification'][0])->toMatchArray(['opens' => '13:00', 'closes' => '23:30']);
});

it('leaves the end date out of a modified-hours row unless the end is known and dated', function (array $set): void {
    $schema = location_schema_for(location_with_hours([
        'location_modified_hours' => true,
        'location_modified_hours_group' => [[
            'location_modified_hours_times' => [['location_modified_hours_day' => 'Sunday', 'location_modified_hours_closed' => true]],
        ]],
    ] + $set));

    expect($schema['openingHoursSpecification'][0])->not->toHaveKey('validThrough');
})->with([
    'end not known' => [['location_modified_hours_end_date' => '2030-12-26']],
    'no end date' => [['location_modified_hours_end' => true]],
]);

it('writes modified hours next to the weekly hours', function (): void {
    $schema = location_schema_for(location_with_hours([
        'location_hours' => [['day' => 'Monday', 'open' => '8:00 am', 'close' => '5:00 pm']],
        'location_modified_hours' => true,
        'location_modified_hours_group' => [['location_modified_hours_24_7' => true]],
    ]));

    expect($schema['openingHours'])->toBe(['Mo 08:00-17:00'])
        ->and($schema['openingHoursSpecification'])->toHaveCount(1);
});

it('writes no specification for modified hours that are off or malformed', function (array $group): void {
    $schema = location_schema_for(location_with_hours($group));

    expect($schema)->not->toHaveKey('openingHoursSpecification');
})->with([
    'flag off' => [['location_modified_hours_group' => [['location_modified_hours_24_7' => true]]]],
    'groups not a list' => [['location_modified_hours' => true, 'location_modified_hours_group' => 'x']],
    'times not a list' => [['location_modified_hours' => true, 'location_modified_hours_group' => [['location_modified_hours_times' => 'x']]]],
    'no groups' => [['location_modified_hours' => true]],
]);

it('lists published condition and treatment titles as specialties', function (): void {
    $schema = location_schema_for([
        'fields' => [10 => ['location_conditions_cpt' => [1, 2], 'location_treatments_cpt' => [3]]],
        'queries' => ['condition' => ['Asthma', 'Angina'], 'treatment' => ['Stent']],
    ]);

    expect($schema['medicalSpecialty'])->toBe(['Asthma', 'Angina', 'Stent'])
        ->and(FakeWpQuery::$built)->toBe([
            ['post_type' => 'condition', 'post_status' => 'publish', 'posts_per_page' => -1, 'post__in' => [1, 2], 'orderby' => 'title', 'order' => 'ASC'],
            ['post_type' => 'treatment', 'post_status' => 'publish', 'posts_per_page' => -1, 'post__in' => [3], 'orderby' => 'title', 'order' => 'ASC'],
        ]);
});

it('de-duplicates and re-indexes specialty titles', function (): void {
    $schema = location_schema_for([
        'fields' => [10 => ['location_conditions_cpt' => [1], 'location_treatments_cpt' => [3]]],
        'queries' => ['condition' => ['Same', 'Same', 'Other'], 'treatment' => ['Other']],
    ]);

    expect($schema['medicalSpecialty'])->toBe(['Same', 'Other']);
});

it('runs no query for a related-posts field that is not a list', function (): void {
    $schema = location_schema_for([
        'fields' => [10 => ['location_conditions_cpt' => '1', 'location_treatments_cpt' => '3']],
        'queries' => ['condition' => ['Asthma'], 'treatment' => ['Stent']],
    ]);

    expect($schema)->not->toHaveKey('medicalSpecialty')
        ->and(FakeWpQuery::$built)->toBe([]);
});

it('lists published providers with their degrees', function (): void {
    $schema = location_schema_for([
        'fields' => [
            10 => ['physician_locations' => [101, 102, 103]],
            101 => ['physician_degree' => [1, 2]],
            103 => ['physician_degree' => [9, 3]],
        ],
        'status' => [101 => 'publish', 102 => 'draft', 103 => 'publish'],
        'titles' => [101 => 'Ada Lovelace', 103 => 'Grace Hopper'],
        'terms' => ['degree' => [1 => (object) ['name' => 'MD'], 2 => (object) ['name' => 'PhD'], 3 => 'error']],
    ]);

    expect($schema['availableService'])->toBe([
        ['@type' => 'Physician', 'name' => 'Ada Lovelace, MD, PhD', 'url' => 'https://uamshealth.com/p/101/'],
        ['@type' => 'Physician', 'name' => 'Grace Hopper', 'url' => 'https://uamshealth.com/p/103/'],
    ]);
});

it('ignores a provider degree field that is not a list', function (): void {
    $schema = location_schema_for([
        'fields' => [10 => ['physician_locations' => [101]], 101 => ['physician_degree' => '1']],
        'status' => [101 => 'publish'],
        'titles' => [101 => 'Ada Lovelace'],
        'terms' => ['degree' => [1 => (object) ['name' => 'MD']]],
    ]);

    expect($schema['availableService'][0]['name'])->toBe('Ada Lovelace');
});

it('lists at most the first 50 providers', function (): void {
    $ids = range(101, 160);

    $schema = location_schema_for([
        'fields' => [10 => ['physician_locations' => $ids]],
        'status' => array_fill_keys($ids, 'publish'),
    ]);

    expect($schema['availableService'])->toHaveCount(50)
        ->and($schema['availableService'][49]['url'])->toBe('https://uamshealth.com/p/150/');
});

it('writes no providers when none are published or the field is not a list', function (mixed $ids): void {
    $schema = location_schema_for(['fields' => [10 => ['physician_locations' => $ids]], 'status' => [101 => 'draft']]);

    expect($schema)->not->toHaveKey('availableService');
})->with(['unpublished' => [[101]], 'not a list' => ['101']]);

it('marks a location temporarily closed once the closing date has passed', function (): void {
    $schema = location_schema_for(['fields' => [10 => [
        'location_closing' => true,
        'location_closing_length' => 'temporary',
        'location_closing_date' => '2020-01-01',
    ]]]);

    expect($schema['specialOpeningHoursSpecification'])->toBe([
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => LOCATION_ALL_DAYS,
        'opens' => '00:00',
        'closes' => '00:00',
        'description' => 'Temporarily closed',
    ]);
});

it('does not mark a location closed for a permanent closing, a future date, no date, or closing off', function (array $set): void {
    $schema = location_schema_for(['fields' => [10 => $set]]);

    expect($schema)->not->toHaveKey('specialOpeningHoursSpecification');
})->with([
    'permanent' => [['location_closing' => true, 'location_closing_length' => 'permanent', 'location_closing_date' => '2020-01-01']],
    'future date' => [['location_closing' => true, 'location_closing_length' => 'temporary', 'location_closing_date' => '2999-01-01']],
    'no date' => [['location_closing' => true, 'location_closing_length' => 'temporary']],
    'closing off' => [['location_closing' => false, 'location_closing_length' => 'temporary', 'location_closing_date' => '2020-01-01']],
]);
