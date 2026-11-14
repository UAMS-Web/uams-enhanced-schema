<?php

/**
 * The four schema writers read the current post's id, which WordPress reports as false
 * when there is no current post; each writes nothing then instead of reading fields for it.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

it('writes nothing when there is no current post', function (string $writer): void {
    when('get_the_ID')->justReturn(false);

    if (! is_callable($writer)) {
        throw new InvalidArgumentException("$writer is not callable.");
    }

    ob_start();
    $writer();

    expect((string) ob_get_clean())->toBe('');
})->with([
    'physician' => ['uams_physician_schema'],
    'location' => ['uams_location_schema'],
    'condition' => ['uams_condition_schema'],
    'treatment' => ['uams_treatment_schema'],
]);
