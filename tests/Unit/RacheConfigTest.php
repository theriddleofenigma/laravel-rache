<?php

declare(strict_types=1);

namespace Rache\Tests\Unit;

use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rache\Config\RacheConfig;
use Rache\Tags\Auth;

/**
 * Covers the normalising behaviour of the config accessor.
 */
class RacheConfigTest extends TestCase
{
    /**
     * Build a config accessor over the given `rache` config array.
     *
     * @param  array<string, mixed>  $rache  The rache config entries.
     */
    protected function config(array $rache): RacheConfig
    {
        return new RacheConfig(new Repository(['rache' => $rache]));
    }

    #[Test]
    public function it_falls_back_to_defaults_when_nothing_is_configured(): void
    {
        $config = $this->config([]);

        $this->assertTrue($config->enabled());
        $this->assertSame(RacheConfig::DEFAULT_LIFETIME, $config->lifetime());
        $this->assertSame(RacheConfig::DEFAULT_PREFIX, $config->prefix());
        $this->assertNull($config->cacheStore());
        $this->assertSame([], $config->tags());
        $this->assertSame(['GET', 'HEAD'], $config->cacheableMethods());
        $this->assertTrue($config->stripSetCookieHeader());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function booleanProvider(): array
    {
        return [
            'true bool' => [true, true],
            'false bool' => [false, false],
            'string false' => ['false', false],
            'string true' => ['true', true],
            'string zero' => ['0', false],
            'string one' => ['1', true],
            'string off' => ['off', false],
            'string on' => ['on', true],
            'garbage falls back to default' => ['banana', true],
        ];
    }

    #[Test]
    #[DataProvider('booleanProvider')]
    public function it_coerces_env_style_booleans(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->config(['enabled' => $value])->enabled());
    }

    #[Test]
    public function it_rejects_a_zero_or_non_numeric_lifetime(): void
    {
        $this->assertSame(RacheConfig::DEFAULT_LIFETIME, $this->config(['lifetime' => 0])->lifetime());
        $this->assertSame(RacheConfig::DEFAULT_LIFETIME, $this->config(['lifetime' => -5])->lifetime());
        $this->assertSame(RacheConfig::DEFAULT_LIFETIME, $this->config(['lifetime' => 'soon'])->lifetime());
        $this->assertSame(90, $this->config(['lifetime' => '90'])->lifetime());
    }

    #[Test]
    public function it_trims_the_prefix_and_falls_back_when_blank(): void
    {
        $this->assertSame('acme', $this->config(['prefix' => '  acme  '])->prefix());
        $this->assertSame(RacheConfig::DEFAULT_PREFIX, $this->config(['prefix' => '   '])->prefix());
    }

    #[Test]
    public function it_treats_a_blank_cache_store_as_the_application_default(): void
    {
        $this->assertNull($this->config(['cache_store' => ''])->cacheStore());
        $this->assertNull($this->config(['cache_store' => '   '])->cacheStore());
        $this->assertSame('redis', $this->config(['cache_store' => ' redis '])->cacheStore());
    }

    #[Test]
    public function it_uppercases_trims_and_dedupes_cacheable_methods(): void
    {
        $config = $this->config([
            'cacheable_methods' => [' get ', 'GET', 'head', 'Options', '', 42],
        ]);

        $this->assertSame(['GET', 'HEAD', 'OPTIONS'], $config->cacheableMethods());
    }

    #[Test]
    public function it_falls_back_when_cacheable_methods_is_empty_or_invalid(): void
    {
        $this->assertSame(['GET', 'HEAD'], $this->config(['cacheable_methods' => []])->cacheableMethods());
        $this->assertSame(['GET', 'HEAD'], $this->config(['cacheable_methods' => 'GET'])->cacheableMethods());
    }

    #[Test]
    public function it_normalises_the_tag_map(): void
    {
        $config = $this->config([
            'tags' => [
                ' auth ' => '\\'.Auth::class,
                '' => Auth::class,
                'bad' => 123,
            ],
        ]);

        $this->assertSame(['auth' => Auth::class], $config->tags());
    }
}
