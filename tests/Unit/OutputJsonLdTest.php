<?php

/**
 * uams_output_jsonld() writes JSON inside a <script> element, so a value must
 * not be able to end that element or carry an entity into the page.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

/**
 * Render the JSON-LD block for the given data.
 *
 * @param  array<string, mixed>  $data
 */
function render_jsonld(array $data): string
{
    when('wp_json_encode')->alias('json_encode');

    ob_start();
    uams_output_jsonld($data);

    return (string) ob_get_clean();
}

it('keeps a closing script tag in a value from ending the element', function (): void {
    $out = render_jsonld(['description' => 'before </script><script>alert(1)</script> after']);

    expect(substr_count($out, '</script>'))->toBe(1, 'only the wrapper closes the element')
        ->and($out)->toContain('\u003C/script\u003E\u003Cscript\u003E');
});

it('hex-escapes ampersands and leaves slashes readable', function (): void {
    $out = render_jsonld(['url' => 'https://example.test/a/b?x=1&y=2']);

    expect($out)->toContain('"https://example.test/a/b?x=1\u0026y=2"')
        ->and($out)->not->toContain('\/');
});
