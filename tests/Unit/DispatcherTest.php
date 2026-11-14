<?php

/**
 * uams_enhanced_schema_output() picks the schema writer for the current singular
 * post type, adds the breadcrumb on five types, and adds the organization schema
 * on the front page.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\expect as expectCall;
use function Brain\Monkey\Functions\when;

const UAMS_SCHEMA_WRITERS = [
    'uams_physician_schema',
    'uams_location_schema',
    'uams_condition_schema',
    'uams_treatment_schema',
    'uams_breadcrumb_schema',
    'uams_organization_schema',
];

/**
 * Run the dispatcher as the given singular post type (empty for none) and front-page flag.
 *
 * @param  list<string>  $expected  The writers that must run once; every other writer must not run.
 */
function dispatch_as(string $type, bool $frontPage, array $expected): void
{
    when('is_singular')->alias(
        static fn ($types): bool => $type !== '' && in_array($type, (array) $types, true)
    );
    when('is_front_page')->justReturn($frontPage);

    foreach (UAMS_SCHEMA_WRITERS as $writer) {
        if (in_array($writer, $expected, true)) {
            expectCall($writer)->once();
        } else {
            expectCall($writer)->never();
        }
    }

    uams_enhanced_schema_output();
}

it('writes the matching schema and the breadcrumb for a post type', function (string $type, string $writer): void {
    dispatch_as($type, false, [$writer, 'uams_breadcrumb_schema']);
})->with([
    'provider' => ['provider', 'uams_physician_schema'],
    'location' => ['location', 'uams_location_schema'],
    'condition' => ['condition', 'uams_condition_schema'],
    'treatment' => ['treatment', 'uams_treatment_schema'],
]);

it('writes only the breadcrumb for an expertise page', function (): void {
    dispatch_as('expertise', false, ['uams_breadcrumb_schema']);
});

it('writes the organization schema on the front page only', function (): void {
    dispatch_as('', true, ['uams_organization_schema']);
});

it('writes nothing on any other page', function (): void {
    dispatch_as('', false, []);
});
