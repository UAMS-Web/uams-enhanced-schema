<?php

/**
 * The output buffer callback that replace mode registers on wp_loaded drops the
 * original http://www.schema.org ld+json block from provider and location pages.
 */

declare(strict_types=1);

use function Brain\Monkey\Actions\expectAdded;
use function Brain\Monkey\Functions\when;

const UAMS_OLD_BLOCK = '<script type="application/ld+json">{"@context": "http://www.schema.org", "@type": "Physician"}</script>';
const UAMS_PLACEHOLDER = '<!-- UAMS Enhanced Schema: original schema replaced -->';

/**
 * Register replace mode, run the wp_loaded callback and pass a page through the buffer it starts.
 */
function through_buffer(string $page, string $type): string
{
    when('is_singular')->alias(static fn ($requested): bool => $requested === $type);

    $callback = null;
    expectAdded('wp_loaded')->once()->whenHappen(function ($registered) use (&$callback): void {
        $callback = $registered;
    });

    uams_enhanced_schema_register_stripping('replace');
    expect($callback)->toBeCallable();

    ob_start();
    $callback();
    echo $page;
    ob_end_flush();

    return (string) ob_get_clean();
}

it('replaces the old schema block on provider and location pages', function (string $type): void {
    $page = '<body>before'.UAMS_OLD_BLOCK.'after</body>';

    expect(through_buffer($page, $type))->toBe('<body>before'.UAMS_PLACEHOLDER.'after</body>');
})->with(['provider', 'location']);

it('leaves every other page untouched', function (): void {
    $page = '<body>'.UAMS_OLD_BLOCK.'</body>';

    expect(through_buffer($page, 'post'))->toBe($page);
});

it('leaves a block that already uses the https context', function (): void {
    $page = '<script type="application/ld+json">{"@context": "https://schema.org", "@type": "Physician"}</script>';

    expect(through_buffer($page, 'provider'))->toBe($page);
});

it('leaves a provider page with no schema block untouched', function (): void {
    expect(through_buffer('<body>plain</body>', 'provider'))->toBe('<body>plain</body>');
});
