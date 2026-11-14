<?php

/**
 * UAMS_SCHEMA_MODE decides whether the original inline schema is stripped:
 * 'replace' registers the output buffer on wp_loaded, 'safe' registers nothing.
 */

declare(strict_types=1);

use function Brain\Monkey\Actions\expectAdded;
use function Brain\Monkey\Actions\has;

it('registers the stripping buffer in replace mode', function (): void {
    expectAdded('wp_loaded')->once();

    uams_enhanced_schema_register_stripping('replace');
});

it('registers nothing in safe mode', function (): void {
    uams_enhanced_schema_register_stripping('safe');

    expect(has('wp_loaded'))->toBeFalse();
});

it('registers nothing for an unknown mode', function (): void {
    uams_enhanced_schema_register_stripping('');

    expect(has('wp_loaded'))->toBeFalse();
});
