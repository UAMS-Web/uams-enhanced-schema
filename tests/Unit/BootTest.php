<?php

/**
 * What the plugin file registers when WordPress loads it.
 */

declare(strict_types=1);

it('prints the schema from wp_footer late', function (): void {
    expect(load_plugin()['footer'])->toBe(99);
});

it('registers the stripping buffer on wp_loaded in the default replace mode', function (): void {
    expect(load_plugin()['stripping'])->toBeTrue();
});
