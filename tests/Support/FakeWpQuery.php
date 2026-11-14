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

        foreach (self::$titles[(string) ($args['post_type'] ?? '')] ?? [] as $title) {
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
    }
}
