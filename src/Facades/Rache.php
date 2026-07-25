<?php

declare(strict_types=1);

namespace Rache\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Rache\Rache as RacheRepository;
use Rache\Tags\RacheTagInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Facade for the Rache response cache.
 *
 * @method static RacheRepository initialize(Request $request, array $tags = [])
 * @method static RacheRepository reset()
 * @method static bool isInitialized()
 * @method static bool racheEnabled()
 * @method static bool shouldCacheRequest(Request $request)
 * @method static bool hasCachedResponse()
 * @method static Response|null getCachedResponse()
 * @method static bool cacheResponse(Response $response)
 * @method static bool flushTag(string $tag, array $options = [])
 * @method static bool flushAll()
 * @method static array getTagData(string $tag)
 * @method static RacheTagInterface getTagInstance(string $tag)
 * @method static string getCacheKey()
 * @method static array getCacheTags()
 * @method static string getRouteName()
 * @method static int getLifetime()
 *
 * @see RacheRepository
 */
class Rache extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'rache';
    }
}
