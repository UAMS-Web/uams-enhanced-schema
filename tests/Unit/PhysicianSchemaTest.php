<?php

/**
 * uams_physician_schema() reads a provider's ACF fields, taxonomy terms,
 * related posts, education repeater, locations and ratings, and writes the
 * Physician JSON-LD. Each test pins what the writer does today.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

/**
 * A taxonomy term as the writer reads it.
 */
function physician_term(string $name, string $slug = 'a-term'): object
{
    return (object) ['name' => $name, 'slug' => $slug];
}

/**
 * Run uams_physician_schema() against a fake WordPress and return the decoded schema.
 *
 * Options: `fields` (ACF values by post id then field name), `terms` (by taxonomy then
 * term id; the string `error` stands for a WP_Error), `status` and `titles` (by post id),
 * `thumbnail` (a URL, or null for none), `meta` (the `_syndicated_api_data` value),
 * `education` (rows of the education repeater).
 *
 * @param  array<string, mixed>  $o
 * @return array<string, mixed>
 */
function physician_schema_for(array $o = []): array
{
    $fields = $o['fields'] ?? [];
    $terms = $o['terms'] ?? [];
    $status = $o['status'] ?? [];
    $titles = $o['titles'] ?? [];
    $rows = $o['education'] ?? [];
    $cursor = 0;
    $current = [];

    when('wp_json_encode')->alias('json_encode');
    when('wp_strip_all_tags')->alias('strip_tags');
    when('get_the_ID')->justReturn(10);
    when('get_permalink')->alias(static fn ($id): string => "https://uamshealth.com/p/$id/");
    when('get_the_title')->alias(static fn ($id): string => $titles[$id] ?? "Title $id");
    when('get_stylesheet_directory_uri')->justReturn('https://uamshealth.com/theme');
    when('plugins_url')->alias(static fn (string $path): string => "https://uamshealth.com/plugins/$path");
    when('has_post_thumbnail')->justReturn(isset($o['thumbnail']));
    when('get_the_post_thumbnail_url')->alias(static fn ($id, $size = null) => $size === 'large' ? ($o['thumbnail'] ?? false) : false);
    when('get_post_meta')->justReturn($o['meta'] ?? '');
    when('is_wp_error')->alias(static fn ($thing): bool => $thing === 'error');
    when('get_term')->alias(static fn ($id, string $taxonomy) => $terms[$taxonomy][$id] ?? false);
    when('get_post_status')->alias(static fn ($id) => $status[$id] ?? false);
    when('get_field')->alias(static function (string $name, $id = null) use ($fields) {
        $set = $fields[$id] ?? [];

        return array_key_exists($name, $set) ? $set[$name] : false;
    });
    when('have_rows')->alias(static function (string $name) use ($rows, &$cursor): bool {
        return $name === 'physician_education' && $cursor < count($rows);
    });
    when('the_row')->alias(static function () use ($rows, &$cursor, &$current): void {
        $current = $rows[$cursor++];
    });
    when('get_sub_field')->alias(static function (string $name) use (&$current) {
        return $current[$name] ?? false;
    });

    ob_start();
    uams_physician_schema();
    $out = (string) ob_get_clean();

    $schema = json_decode((string) preg_replace('~^\s*<script[^>]*>|</script>\s*$~', '', $out), true);

    expect($schema['@context'])->toBe('https://schema.org')
        ->and($schema['@type'])->toBe('Physician');

    return $schema;
}

/**
 * Press Ganey rating data as the API client stores it.
 */
function physician_rating(int $count, mixed $value = 4.5, int $comments = 0, string $code = '200'): object
{
    return json_decode((string) json_encode([
        'status' => ['code' => $code],
        'data' => ['entities' => [[
            'totalRatingCount' => $count,
            'overallRating' => ['value' => $value],
            'totalCommentCount' => $comments,
        ]]],
    ]));
}

it('writes the fixed properties for a provider with nothing filled in', function (): void {
    $schema = physician_schema_for();

    expect($schema)->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'Physician',
        'name' => '',
        'givenName' => '',
        'familyName' => '',
        'url' => 'https://uamshealth.com/p/10/',
        'image' => 'https://uamshealth.com/plugins/UAMSWP-Find-a-Doc/assets/svg/no-image_3-4.jpg',
        'logo' => 'https://uamshealth.com/theme/assets/svg/uams-logo_health_horizontal_dark_386x50.png',
        'isAcceptingNewPatients' => false,
        'memberOf' => [
            '@type' => 'MedicalOrganization',
            'name' => 'UAMS Health',
            'url' => 'https://uamshealth.com',
        ],
    ]);
});

it('builds the name from first, middle and last', function (): void {
    $schema = physician_schema_for(['fields' => [10 => [
        'physician_first_name' => 'Ada',
        'physician_middle_name' => 'Byron',
        'physician_last_name' => 'Lovelace',
        'physician_prefix' => 'Dr.',
        'physician_suffix' => 'Jr.',
    ]]]);

    expect($schema['name'])->toBe('Ada Byron Lovelace')
        ->and($schema['givenName'])->toBe('Ada')
        ->and($schema['familyName'])->toBe('Lovelace')
        ->and($schema['additionalName'])->toBe('Byron')
        ->and($schema['honorificPrefix'])->toBe('Dr.')
        ->and($schema['honorificSuffix'])->toBe('Jr.');
});

it('leaves out the optional name parts that are empty', function (): void {
    $schema = physician_schema_for(['fields' => [10 => ['physician_first_name' => 'Ada', 'physician_last_name' => 'Lovelace']]]);

    expect($schema)->not->toHaveKeys(['additionalName', 'honorificPrefix', 'honorificSuffix'])
        ->and($schema['name'])->toBe('Ada  Lovelace');
});

it('appends degrees to the name and lets them replace the suffix', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => [
            'physician_first_name' => 'Ada',
            'physician_last_name' => 'Lovelace',
            'physician_suffix' => 'Jr.',
            'physician_degree' => [1, 2],
        ]],
        'terms' => ['degree' => [1 => physician_term('MD'), 2 => physician_term('PhD')]],
    ]);

    expect($schema['name'])->toBe('Ada  Lovelace, MD, PhD')
        ->and($schema['honorificSuffix'])->toBe('MD, PhD');
});

it('skips a degree whose term is missing or an error, and a degree field that is not a list', function (mixed $degrees): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_last_name' => 'Lovelace', 'physician_degree' => $degrees]],
        'terms' => ['degree' => [1 => physician_term('MD'), 2 => 'error']],
    ]);

    expect($schema['name'])->toBe('Lovelace')
        ->and($schema)->not->toHaveKey('honorificSuffix');
})->with([
    'missing term' => [[99]],
    'error term' => [[2]],
    'not a list' => ['1'],
    'empty' => [[]],
]);

it('writes the clinical title as the job title', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_title' => 5]],
        'terms' => ['clinical_title' => [5 => physician_term('Cardiologist')]],
    ]);

    expect($schema['jobTitle'])->toBe('Cardiologist');
});

it('writes no job title for a missing or error term', function (mixed $term): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_title' => 5]],
        'terms' => ['clinical_title' => [5 => $term]],
    ]);

    expect($schema)->not->toHaveKey('jobTitle');
})->with(['missing' => [false], 'error' => ['error']]);

it('describes the provider with the short bio, stripped of tags', function (): void {
    $schema = physician_schema_for(['fields' => [10 => [
        'physician_short_clinical_bio' => '<p>Short <b>bio</b></p>',
        'physician_clinical_bio' => 'Long bio',
    ]]]);

    expect($schema['description'])->toBe('Short bio');
});

it('falls back to the clinical bio, cut to 250 characters', function (): void {
    $schema = physician_schema_for(['fields' => [10 => ['physician_clinical_bio' => '<p>'.str_repeat('a', 300).'</p>']]]);

    expect($schema['description'])->toBe(str_repeat('a', 247).'...')
        ->and(mb_strlen($schema['description']))->toBe(250);
});

it('keeps a clinical bio that is short enough whole', function (): void {
    $schema = physician_schema_for(['fields' => [10 => ['physician_clinical_bio' => '<i>Brief</i>']]]);

    expect($schema['description'])->toBe('Brief');
});

it('uses the featured image when there is one', function (): void {
    $schema = physician_schema_for(['thumbnail' => 'https://uamshealth.com/uploads/ada.jpg']);

    expect($schema['image'])->toBe('https://uamshealth.com/uploads/ada.jpg');
});

it('lists languages and skips unusable terms', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_languages' => [1, 2, 3]]],
        'terms' => ['language' => [1 => physician_term('Spanish'), 2 => 'error']],
    ]);

    expect($schema['knowsLanguage'])->toBe(['Spanish']);
});

it('ignores a language field that is not a list', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_languages' => '1']],
        'terms' => ['language' => [1 => physician_term('Spanish')]],
    ]);

    expect($schema)->not->toHaveKey('knowsLanguage');
});

it('writes no languages when none resolve', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_languages' => [2]]],
        'terms' => ['language' => [2 => 'error']],
    ]);

    expect($schema)->not->toHaveKey('knowsLanguage');
});

it('writes the NPI as an identifier', function (): void {
    $schema = physician_schema_for(['fields' => [10 => ['physician_npi' => '1234567890']]]);

    expect($schema['identifier'])->toBe(['@type' => 'PropertyValue', 'propertyID' => 'NPI', 'value' => '1234567890']);
});

it('maps the accepting-patients field to a boolean unless it is unset', function (mixed $value, ?bool $expected): void {
    $schema = physician_schema_for(['fields' => [10 => ['physician_accepting_patients' => $value]]]);

    if ($expected === null) {
        expect($schema)->not->toHaveKey('isAcceptingNewPatients');

        return;
    }

    expect($schema['isAcceptingNewPatients'])->toBe($expected);
})->with([
    'one' => [1, true],
    'string one' => ['1', true],
    'zero' => [0, false],
    'string zero' => ['0', false],
    'false, which is what an unset field reads as in this fake' => [false, false],
    'null' => [null, null],
    'empty string' => ['', null],
]);

it('lists published conditions and treatments as specialties, and conditions as expertise', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => [
            'physician_conditions_cpt' => [21, 22, 23],
            'physician_treatments_cpt' => [31, 32],
        ]],
        'status' => [21 => 'publish', 22 => 'draft', 23 => 'publish', 31 => 'publish', 32 => 'private'],
        'titles' => [21 => 'Asthma', 23 => 'Angina', 31 => 'Stent'],
    ]);

    expect($schema['medicalSpecialty'])->toBe(['Asthma', 'Angina', 'Stent'])
        ->and($schema['expertiseCondition'])->toBe([
            ['@type' => 'MedicalCondition', 'name' => 'Asthma', 'url' => 'https://uamshealth.com/p/21/'],
            ['@type' => 'MedicalCondition', 'name' => 'Angina', 'url' => 'https://uamshealth.com/p/23/'],
        ]);
});

it('de-duplicates specialty titles and re-indexes them', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_conditions_cpt' => [21, 22, 23], 'physician_treatments_cpt' => [31]]],
        'status' => [21 => 'publish', 22 => 'publish', 23 => 'publish', 31 => 'publish'],
        'titles' => [21 => 'Same', 22 => 'Same', 23 => 'Other', 31 => 'Other'],
    ]);

    expect($schema['medicalSpecialty'])->toBe(['Same', 'Other']);
});

it('writes only specialties, with no expertise, when only treatments are published', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_treatments_cpt' => [31]]],
        'status' => [31 => 'publish'],
    ]);

    expect($schema['medicalSpecialty'])->toBe(['Title 31'])
        ->and($schema)->not->toHaveKey('expertiseCondition');
});

it('writes neither when no related post is published', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_conditions_cpt' => [21], 'physician_treatments_cpt' => [31]]],
        'status' => [21 => 'draft'],
    ]);

    expect($schema)->not->toHaveKeys(['medicalSpecialty', 'expertiseCondition']);
});

it('writes education as credentials, with the type falling back to Medical Education', function (): void {
    $schema = physician_schema_for([
        'education' => [
            ['school' => 1, 'education_type' => 2, 'description' => '2005'],
            ['school' => 1],
        ],
        'terms' => [
            'school' => [1 => physician_term('UAMS')],
            'educationtype' => [2 => physician_term('Residency')],
        ],
    ]);

    expect($schema['hasCredential'])->toBe([
        [
            '@type' => 'EducationalOccupationalCredential',
            'educationalLevel' => 'Residency',
            'recognizedBy' => ['@type' => 'EducationalOrganization', 'name' => 'UAMS'],
            'description' => '2005',
        ],
        [
            '@type' => 'EducationalOccupationalCredential',
            'educationalLevel' => 'Medical Education',
            'recognizedBy' => ['@type' => 'EducationalOrganization', 'name' => 'UAMS'],
        ],
    ]);
});

it('drops an education row whose school does not resolve', function (mixed $school): void {
    $schema = physician_schema_for([
        'education' => [['school' => $school, 'education_type' => 2]],
        'terms' => [
            'school' => [1 => 'error'],
            'educationtype' => [2 => physician_term('Residency')],
        ],
    ]);

    expect($schema)->not->toHaveKey('hasCredential');
})->with(['no school' => [false], 'missing term' => [9], 'error term' => [1]]);

it('writes board certifications after the education credentials', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_boards' => [1, 2, 3]]],
        'education' => [['school' => 1]],
        'terms' => [
            'school' => [1 => physician_term('UAMS')],
            'boards' => [1 => physician_term('Cardiology'), 2 => 'error'],
        ],
    ]);

    expect(array_column($schema['hasCredential'], 'name'))->toBe(['Cardiology'])
        ->and($schema['hasCredential'][0]['educationalLevel'])->toBe('Medical Education')
        ->and($schema['hasCredential'][1])->toBe([
            '@type' => 'EducationalOccupationalCredential',
            'credentialCategory' => 'Board Certification',
            'name' => 'Cardiology',
        ]);
});

it('writes board certifications alone when there is no education', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_boards' => [1]]],
        'terms' => ['boards' => [1 => physician_term('Cardiology')]],
    ]);

    expect($schema['hasCredential'])->toBe([[
        '@type' => 'EducationalOccupationalCredential',
        'credentialCategory' => 'Board Certification',
        'name' => 'Cardiology',
    ]]);
});

it('ignores a board field that is not a list', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_boards' => '1']],
        'terms' => ['boards' => [1 => physician_term('Cardiology')]],
    ]);

    expect($schema)->not->toHaveKey('hasCredential');
});

it('writes no credentials when no board resolves', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_boards' => [2]]],
        'terms' => ['boards' => [2 => 'error']],
    ]);

    expect($schema)->not->toHaveKey('hasCredential');
});

it('writes a single location as one address and as a clinic', function (): void {
    $schema = physician_schema_for([
        'fields' => [
            10 => ['physician_locations' => [50]],
            50 => [
                'location_address_1' => '4301 W Markham',
                'location_address_2' => 'Room 4',
                'location_city' => 'Little Rock',
                'location_state' => 'AR',
                'location_zip' => '72205',
                'location_phone' => '(501) 555-0100',
            ],
        ],
        'status' => [50 => 'publish'],
        'titles' => [50 => 'Main Clinic'],
    ]);

    $address = [
        '@type' => 'PostalAddress',
        'streetAddress' => '4301 W Markham Room 4',
        'addressLocality' => 'Little Rock',
        'addressRegion' => 'AR',
        'postalCode' => '72205',
    ];

    expect($schema['address'])->toBe($address)
        ->and($schema['workLocation'])->toBe([[
            '@type' => 'MedicalClinic',
            'name' => 'Main Clinic',
            'url' => 'https://uamshealth.com/p/50/',
            'address' => $address,
            'telephone' => '+1-501-555-0100',
        ]]);
});

it('writes a list of addresses for several locations and skips unpublished ones', function (): void {
    $schema = physician_schema_for([
        'fields' => [
            10 => ['physician_locations' => [50, 51, 52]],
            50 => ['location_city' => 'Little Rock'],
            51 => ['location_city' => 'Hidden'],
            52 => ['location_city' => 'Conway'],
        ],
        'status' => [50 => 'publish', 51 => 'draft', 52 => 'publish'],
    ]);

    expect(array_column($schema['address'], 'addressLocality'))->toBe(['Little Rock', 'Conway'])
        ->and(array_column($schema['workLocation'], 'name'))->toBe(['Title 50', 'Title 52']);
});

it('writes no telephone for a location without a phone', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_locations' => [50]], 50 => ['location_city' => 'Little Rock']],
        'status' => [50 => 'publish'],
    ]);

    expect($schema['workLocation'][0])->not->toHaveKey('telephone');
});

it('reads the address from the parent location when a child location has one', function (): void {
    $schema = physician_schema_for([
        'fields' => [
            10 => ['physician_locations' => [50]],
            50 => ['location_parent' => true, 'location_parent_id' => 60, 'location_city' => 'Child City', 'location_phone' => '5015550100'],
            60 => ['location_city' => 'Parent City'],
        ],
        'status' => [50 => 'publish'],
    ]);

    expect($schema['address']['addressLocality'])->toBe('Parent City')
        ->and($schema['workLocation'][0]['telephone'])->toBe('+1-501-555-0100')
        ->and($schema['workLocation'][0]['name'])->toBe('Title 50');
});

it('keeps the location own address unless both the parent flag and id are set', function (array $set): void {
    $schema = physician_schema_for([
        'fields' => [
            10 => ['physician_locations' => [50]],
            50 => $set + ['location_city' => 'Child City'],
            60 => ['location_city' => 'Parent City'],
        ],
        'status' => [50 => 'publish'],
    ]);

    expect($schema['address']['addressLocality'])->toBe('Child City');
})->with([
    'flag only' => [['location_parent' => true]],
    'id only' => [['location_parent_id' => 60]],
]);

it('writes the building and suite as the second address line', function (): void {
    $schema = physician_schema_for([
        'fields' => [
            10 => ['physician_locations' => [50]],
            50 => [
                'location_address_1' => '4301 W Markham',
                'location_building' => 7,
                'location_suite' => 'Suite 200',
                'location_address_2' => 'Ignored',
            ],
        ],
        'status' => [50 => 'publish'],
        'terms' => ['building' => [7 => physician_term('Jones Building', 'jones')]],
    ]);

    expect($schema['address']['streetAddress'])->toBe('4301 W Markham Jones Building Suite 200');
});

it('ignores the placeholder building and a building term that does not resolve', function (mixed $term): void {
    $schema = physician_schema_for([
        'fields' => [
            10 => ['physician_locations' => [50]],
            50 => ['location_address_1' => '1 Main', 'location_building' => 7, 'location_suite' => 'Suite 9'],
        ],
        'status' => [50 => 'publish'],
        'terms' => ['building' => [7 => $term]],
    ]);

    expect($schema['address']['streetAddress'])->toBe('1 Main Suite 9');
})->with([
    'placeholder' => [physician_term('None', '_none')],
    'error' => ['error'],
    'missing' => [false],
]);

it('writes a rating with a review count for 30 or more ratings', function (): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_npi' => '123']],
        'meta' => physician_rating(30, 4.5, 12),
    ]);

    expect($schema['aggregateRating'])->toBe([
        '@type' => 'AggregateRating',
        'ratingValue' => '4.5',
        'bestRating' => '5',
        'ratingCount' => '30',
        'reviewCount' => '12',
    ]);
});

it('leaves the review count out when there are no comments', function (int $comments): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_npi' => '123']],
        'meta' => physician_rating(45, 4.8, $comments),
    ]);

    expect($schema['aggregateRating'])->toBe([
        '@type' => 'AggregateRating',
        'ratingValue' => '4.8',
        'bestRating' => '5',
        'ratingCount' => '45',
    ]);
})->with(['zero' => [0], 'negative' => [-1]]);

it('writes no rating below 30 ratings, without a value, without a 200 status, or without an NPI', function (string $npi, object|string $meta): void {
    $schema = physician_schema_for([
        'fields' => [10 => ['physician_npi' => $npi]],
        'meta' => $meta,
    ]);

    expect($schema)->not->toHaveKey('aggregateRating');
})->with(fn (): array => [
    'below the floor' => ['123', physician_rating(29)],
    'no value' => ['123', physician_rating(40, 0)],
    'status not 200' => ['123', physician_rating(40, 4.5, 0, '500')],
    'no npi' => ['', physician_rating(40)],
    'no data' => ['123', ''],
    'no status' => ['123', (object) ['data' => (object) []]],
]);
