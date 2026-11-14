<?php

/**
 * UAMS_SCHEMA_MODE decides whether the original inline schema is stripped:
 * 'replace' registers the output buffer on wp_loaded, 'safe' registers nothing.
 */

declare(strict_types=1);

namespace UamsEnhancedSchema\Tests\Unit;

use UamsEnhancedSchema\Tests\Support\UnitTestCase;

use function Brain\Monkey\Actions\expectAdded;
use function Brain\Monkey\Actions\has;

final class SchemaModeTest extends UnitTestCase
{
    public function test_replace_mode_registers_the_stripping_buffer(): void
    {
        expectAdded('wp_loaded')->once();

        uams_enhanced_schema_register_stripping('replace');
    }

    public function test_safe_mode_registers_nothing(): void
    {
        uams_enhanced_schema_register_stripping('safe');

        $this->assertFalse(has('wp_loaded'));
    }

    public function test_an_unknown_mode_registers_nothing(): void
    {
        uams_enhanced_schema_register_stripping('');

        $this->assertFalse(has('wp_loaded'));
    }
}
