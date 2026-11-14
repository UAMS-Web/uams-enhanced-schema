<?php

/**
 * Pest configuration, for the Unit suite.
 *
 * The Integration suite is class-based PHPUnit 9.6 under tests/Integration, run
 * from its own Composer setup; Pest never loads it.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Load the plugin file once per process, inside the current Brain Monkey session.
 *
 * @return array{footer: int|bool, stripping: int|bool} the hooks it registered at load time
 *
 * Its file-scope add_action() calls and plugin_dir_path() style calls need the
 * session and these stubs, and loading it here rather than in the bootstrap is what
 * lets a mutation run mutate it (tests/bootstrap.php says why). Do not define
 * permanent WordPress function stubs outside a session: Patchwork treats that as
 * too early and Brain Monkey cannot redefine them in tests (DefinedTooEarly).
 */
function load_plugin(): array
{
    static $loaded = false;
    static $boot = [];

    if ($loaded) {
        return $boot;
    }

    $loaded = true;

    when('plugin_dir_path')->justReturn(__DIR__.'/../');
    when('plugin_dir_url')->justReturn('https://example.test/wp-content/plugins/'.basename(dirname(__DIR__)).'/');
    when('plugin_basename')->alias(static fn ($file): string => basename(dirname($file)).'/'.basename($file));
    when('is_multisite')->justReturn(false);
    when('get_site_option')->alias(static fn ($key, $default = false) => $default);
    when('get_option')->alias(static fn ($key, $default = false) => $default);
    when('update_option')->justReturn();
    when('register_activation_hook')->justReturn();
    when('register_deactivation_hook')->justReturn();
    when('register_uninstall_hook')->justReturn();
    when('load_plugin_textdomain')->justReturn();
    when('is_admin')->justReturn(false);
    when('wp_enqueue_script')->justReturn();
    when('wp_enqueue_style')->justReturn();
    when('wp_register_script')->justReturn();
    when('wp_register_style')->justReturn();
    when('wp_localize_script')->justReturn();
    when('esc_html')->returnArg();
    when('esc_attr')->returnArg();
    when('esc_url')->returnArg();
    when('__')->returnArg();
    when('_e')->justReturn();
    when('esc_html__')->returnArg();
    when('esc_attr__')->returnArg();

    require_once __DIR__.'/../uams-enhanced-schema.php';

    // The hooks the file registers at load time, read inside the same session.
    $boot = [
        'footer' => has_action('wp_footer', 'uams_enhanced_schema_output'),
        'stripping' => has_action('wp_loaded'),
    ];

    return $boot;
}

/*
 * Brain Monkey around every test.
 *
 * setUp() defines WordPress' hook functions and arms Patchwork; tearDown()
 * verifies every expectation the test set (a `->once()` that never happened
 * fails here) and removes every mock, so nothing leaks into the next test.
 *
 * The assertion count: Brain Monkey's expectations are Mockery expectations,
 * which PHPUnit does not count. Without adding them, a test whose only check is
 * `Functions\expect(...)->once()` reports as risky, and failOnRisky fails it.
 */
uses()
    ->beforeEach(function (): void {
        setUp();
        load_plugin();
    })
    ->afterEach(function (): void {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());

        tearDown();
    })
    ->in('Unit');
