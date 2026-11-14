<?php

/**
 * What the plugin file registers when WordPress loads it.
 *
 * One test, and this file sorts before every other test file: the plugin loads once
 * per process, so a coverage run credits its file-scope lines to the first test that
 * runs, and a mutation run only re-runs the tests credited with a line.
 */

declare(strict_types=1);

it('prints the schema from wp_footer late and registers the stripping buffer on wp_loaded', function (): void {
    $boot = load_plugin();

    expect($boot['footer'])->toBe(99)
        ->and($boot['stripping'])->toBeTrue();
});
