<?php

/**
 * uams_output_jsonld() writes JSON inside a <script> element, so a value must
 * not be able to end that element or carry an entity into the page.
 */

declare(strict_types=1);

namespace UamsEnhancedSchema\Tests\Unit;

use UamsEnhancedSchema\Tests\Support\UnitTestCase;

use function Brain\Monkey\Functions\when;

final class OutputJsonLdTest extends UnitTestCase
{
    private function render(array $data): string
    {
        when('wp_json_encode')->alias('json_encode');

        ob_start();
        uams_output_jsonld($data);

        return (string) ob_get_clean();
    }

    public function test_a_closing_script_tag_in_a_value_cannot_end_the_element(): void
    {
        $out = $this->render(['description' => 'before </script><script>alert(1)</script> after']);

        $this->assertSame(1, substr_count($out, '</script>'), 'only the wrapper closes the element');
        $this->assertStringContainsString('\u003C/script\u003E\u003Cscript\u003E', $out);
    }

    public function test_ampersands_are_hex_escaped_and_slashes_stay_readable(): void
    {
        $out = $this->render(['url' => 'https://example.test/a/b?x=1&y=2']);

        $this->assertStringContainsString('"https://example.test/a/b?x=1\u0026y=2"', $out);
        $this->assertStringNotContainsString('\/', $out);
    }
}
