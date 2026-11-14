<?php

/**
 * Unit bootstrap.
 *
 * The Unit suite runs with no WordPress: WordPress functions are mocked per
 * test with Brain Monkey. The Integration suite does not come through here; it
 * has its own Composer setup and bootstrap under tests/Integration.
 */

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

/*
 * Patchwork first: it can only redefine functions declared in files loaded
 * after it, so loading it before the plugin is what lets a test mock one of the
 * plugin's own functions, not only WordPress'.
 */
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

/*
 * Plugin files open with an ABSPATH guard. Define it so they load, pointing at
 * a directory that is not a WordPress install: code that requires a core file
 * through ABSPATH fails loudly here, which is the sign it belongs in the
 * Integration suite.
 */
if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir().'/uams_enhanced_schema-tests-no-wordpress/');
}

/*
 * The plugin file is NOT loaded here. A file loaded by the bootstrap is already in
 * memory before a mutation run installs its runner, so none of its mutants would
 * take effect and every one would be scored as killed. tests/Pest.php loads it
 * from the first test's `beforeEach`, after the runner is in place (see
 * `load_plugin()` there).
 */

// cspell:ignore ABSPATH autoloaded
