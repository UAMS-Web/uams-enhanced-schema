<?php

/**
 * First unit test pinning harness load for UAMS Enhanced Schema.
 */

declare(strict_types=1);

namespace UamsEnhancedSchema\Tests\Unit;

use UamsEnhancedSchema\Tests\Support\UnitTestCase;

final class HarnessSmokeTest extends UnitTestCase
{
    /**
     * @return void
     */
    public function test_harness_loads_plugin_surface(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/uams-enhanced-schema.php');
        $this->assertTrue(true);
    }
}
