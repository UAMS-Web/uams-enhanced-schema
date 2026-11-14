<?php

/**
 * A stand-in for WP_Query in Unit tests, aliased to that name by the tests that need it.
 */

declare(strict_types=1);

namespace UamsEnhancedSchema\Tests\Support;

/**
 * Records the arguments it was built with and returns canned posts by post type.
 */
final class FakeWpQuery
{
    /**
     * Canned post titles by post type.
     *
     * @var array<string, list<string>>
     */
    public static array $titles = [];

    /**
     * Every argument array a query was built with, in order.
     *
     * @var list<array<string, mixed>>
     */
    public static array $built = [];

    /**
     * How many times wp_reset_postdata() ran since the last reset().
     */
    public static int $resets = 0;

    /**
     * The posts the query found.
     *
     * @var list<object>
     */
    public array $posts = [];

    /**
     * @param  array<string, mixed>  $args
     */
    public function __construct(array $args)
    {
        self::$built[] = $args;

        $type = $args['post_type'] ?? '';

        foreach (self::$titles[is_string($type) ? $type : ''] ?? [] as $title) {
            $this->posts[] = (object) ['post_title' => $title];
        }
    }

    /**
     * Forget the canned posts and recorded arguments.
     */
    public static function reset(): void
    {
        self::$titles = [];
        self::$built = [];
        self::$resets = 0;
    }
}
