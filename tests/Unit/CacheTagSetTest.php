<?php

declare(strict_types=1);

namespace Rache\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rache\Cache\CacheTagSet;

/**
 * Covers the cache tag list a cached response is stored against.
 */
class CacheTagSetTest extends TestCase
{
    #[Test]
    public function it_formats_a_single_cache_tag(): void
    {
        $this->assertSame('auth', CacheTagSet::for('auth'));
        $this->assertSame('auth:DATA', CacheTagSet::for('auth', 'DATA'));
        $this->assertSame('posts.index:auth', CacheTagSet::for('auth', null, 'posts.index'));
        $this->assertSame('posts.index:auth:DATA', CacheTagSet::for('auth', 'DATA', 'posts.index'));
    }

    #[Test]
    public function it_treats_empty_strings_like_null(): void
    {
        $this->assertSame('auth', CacheTagSet::for('auth', '', ''));
    }

    #[Test]
    public function it_always_includes_the_global_tag_even_without_any_rache_tags(): void
    {
        // Regression: the global `rache` tag used to be appended inside the
        // per-tag loop, so a route using `->middleware('rache')` with no tags
        // was never reachable from Rache::flushAll().
        $tags = (new CacheTagSet)->build([], 'posts.index');

        $this->assertContains(CacheTagSet::GLOBAL_TAG, $tags);
        $this->assertSame([CacheTagSet::GLOBAL_TAG, 'posts.index'], $tags);
    }

    #[Test]
    public function it_builds_every_flushable_combination_for_each_tag(): void
    {
        $tags = (new CacheTagSet)->build(['auth' => 'A', 'page' => 'P'], 'posts.index');

        $this->assertSame([
            'rache',
            'posts.index',
            'auth',
            'auth:A',
            'posts.index:auth',
            'posts.index:auth:A',
            'page',
            'page:P',
            'posts.index:page',
            'posts.index:page:P',
        ], $tags);
    }

    #[Test]
    public function it_does_not_repeat_tags(): void
    {
        // Regression: `rache` used to be appended once per configured tag.
        $tags = (new CacheTagSet)->build(['auth' => 'A', 'page' => 'P', 'request' => 'R'], 'posts.index');

        $this->assertSame($tags, array_values(array_unique($tags)));
        $this->assertSame(1, count(array_keys($tags, CacheTagSet::GLOBAL_TAG, true)));
    }
}
