<?php

/**
 * Pest configuration, for the Unit suite.
 *
 * The Integration suite is class-based PHPUnit 9.6 under tests/Integration, run
 * from its own Composer setup; Pest never loads it.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * The hooks the plugin registered when it loaded, kept for later tests; null until it has loaded.
 */
final class PluginBoot
{
    /**
     * @var array{footer: int|bool, stripping: int|bool}|null
     */
    public static ?array $hooks = null;
}

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
    if (PluginBoot::$hooks !== null) {
        return PluginBoot::$hooks;
    }

    PluginBoot::$hooks = ['footer' => false, 'stripping' => false];

    when('plugin_dir_path')->justReturn(__DIR__.'/../');
    when('plugin_dir_url')->justReturn('https://example.test/wp-content/plugins/'.basename(dirname(__DIR__)).'/');
    when('plugin_basename')->alias(static fn (string $file): string => basename(dirname($file)).'/'.basename($file));
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
    PluginBoot::$hooks = [
        'footer' => has_action('wp_footer', 'uams_enhanced_schema_output'),
        'stripping' => has_action('wp_loaded'),
    ];

    return PluginBoot::$hooks;
}

/**
 * Decode the JSON-LD a writer printed: the script element is stripped and the JSON read as an array.
 *
 * @return array<array-key, mixed>
 */
function decode_jsonld(string $out): array
{
    $json = json_decode((string) preg_replace('~^\s*<script[^>]*>|</script>\s*$~', '', $out), true);

    return is_array($json) ? $json : throw new UnexpectedValueException('The output holds no JSON object.');
}

/**
 * Read one value out of decoded schema by a dot path such as `address.streetAddress` or `itemListElement.0.name`.
 *
 * A step that is missing fails the test with the path, which `$schema['a']['b']` would only warn about.
 *
 * @param  array<array-key, mixed>  $schema
 */
function schema_at(array $schema, string $path): mixed
{
    $node = $schema;
    foreach (explode('.', $path) as $key) {
        if (! is_array($node) || ! array_key_exists($key, $node)) {
            throw new OutOfBoundsException("The schema has no $path.");
        }

        $node = $node[$key];
    }

    return $node;
}

/**
 * Read a string out of decoded schema by a dot path, failing the test when the value is not a string.
 *
 * @param  array<array-key, mixed>  $schema
 */
function schema_text(array $schema, string $path): string
{
    $value = schema_at($schema, $path);

    return is_string($value) ? $value : throw new UnexpectedValueException("The schema holds no text at $path.");
}

/**
 * Read the rows of a list in decoded schema by a dot path, failing the test when it is not a list of objects.
 *
 * @param  array<array-key, mixed>  $schema
 * @return list<array<array-key, mixed>>
 */
function schema_rows(array $schema, string $path): array
{
    $node = schema_at($schema, $path);
    if (! is_array($node)) {
        throw new UnexpectedValueException("The schema holds no list at $path.");
    }

    $rows = [];
    foreach ($node as $row) {
        $rows[] = is_array($row) ? $row : throw new UnexpectedValueException("A row at $path is not an object.");
    }

    return $rows;
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
        if (! $this instanceof TestCase) {
            throw new LogicException('Pest runs this hook on a test case.');
        }

        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());

        tearDown();
    })
    ->in('Unit');
