<?php

declare(strict_types=1);

namespace Rache\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rache\Cache\CacheKeyGenerator;
use Rache\Config\RacheConfig;

/**
 * Covers the derivation of the cache key from a request and its tag data.
 */
class CacheKeyGeneratorTest extends TestCase
{
    /**
     * Build a generator over the given rache config.
     *
     * @param  array<string, mixed>  $rache  The rache config entries.
     */
    protected function generator(array $rache = []): CacheKeyGenerator
    {
        return new CacheKeyGenerator(new RacheConfig(new Repository(['rache' => $rache])));
    }

    /**
     * Build a request for the given method and URI.
     *
     * @param  string  $uri  The absolute request URI.
     * @param  string  $method  The HTTP method.
     */
    protected function request(string $uri, string $method = 'GET'): Request
    {
        return Request::create($uri, $method);
    }

    #[Test]
    public function it_prefixes_the_key_and_hashes_the_rest(): void
    {
        $key = $this->generator(['prefix' => 'acme'])->generate($this->request('http://example.test/posts'), []);

        $this->assertStringStartsWith('acme-', $key);
        $this->assertSame(64, strlen(substr($key, strlen('acme-'))));
    }

    #[Test]
    public function it_is_stable_for_identical_requests(): void
    {
        $generator = $this->generator();

        $this->assertSame(
            $generator->generate($this->request('http://example.test/posts'), ['auth' => 'A']),
            $generator->generate($this->request('http://example.test/posts'), ['auth' => 'A']),
        );
    }

    #[Test]
    public function it_varies_by_host_path_method_and_tag_data(): void
    {
        $generator = $this->generator();
        $base = $generator->generate($this->request('http://example.test/posts'), ['auth' => 'A']);

        $this->assertNotSame($base, $generator->generate($this->request('http://other.test/posts'), ['auth' => 'A']));
        $this->assertNotSame($base, $generator->generate($this->request('http://example.test/pages'), ['auth' => 'A']));
        $this->assertNotSame($base, $generator->generate($this->request('http://example.test/posts', 'HEAD'), ['auth' => 'A']));
        $this->assertNotSame($base, $generator->generate($this->request('http://example.test/posts'), ['auth' => 'B']));
    }

    #[Test]
    public function it_does_not_collide_when_a_tag_name_and_its_data_are_ambiguous(): void
    {
        // Regression: tag/data pairs were concatenated with no delimiter, so
        // ["a" => "b.c"] and ["a.b" => "c"] hashed to the same key.
        $generator = $this->generator();
        $request = $this->request('http://example.test/posts');

        $this->assertNotSame(
            $generator->generate($request, ['a' => 'b.c']),
            $generator->generate($request, ['a.b' => 'c']),
        );
    }

    #[Test]
    public function it_ignores_the_query_string_when_no_tag_covers_it(): void
    {
        // The `request` tag is what makes query input part of the key; without
        // it, two query strings intentionally share a cache entry.
        $generator = $this->generator();

        $this->assertSame(
            $generator->generate($this->request('http://example.test/posts?a=1'), []),
            $generator->generate($this->request('http://example.test/posts?a=2'), []),
        );
    }
}
