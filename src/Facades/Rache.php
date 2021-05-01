<?php

namespace Rache\Facades;

use Illuminate\Http\Response;
use Rache\Rache as RacheRepository;
use Illuminate\Support\Facades\Facade;

/**
 * Class CommonService
 *
 * @package Rache\Facades
 * @method static RacheRepository initialize($request, $tags)
 * @method static bool cacheResponse(Response $response)
 * @method static bool hasCachedResponse()
 * @method static mixed getCachedResponse()
 */
class Rache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'rache';
    }
}