<?php

/**
 * First unit test pinning harness load for UAMS Enhanced Schema.
 */

declare(strict_types=1);

it('loads the plugin surface', function (): void {
    expect(dirname(__DIR__, 2).'/uams-enhanced-schema.php')->toBeFile();
});
