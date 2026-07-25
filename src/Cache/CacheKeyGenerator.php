<?php

declare(strict_types=1);

namespace Rache\Cache;

use Illuminate\Http\Request;
use Rache\Config\RacheConfig;

/**
 * Builds the cache key a response is stored under.
 *
 * The key is derived from the request identity (host, method and path) plus the
 * serialized data of every tag applied to the route, so that two requests only
 * share a cache entry when every tag agrees they are equivalent.
 */
class CacheKeyGenerator
{
    /**
     * The delimiter placed between the segments that make up the raw key.
     */
    protected const SEGMENT_DELIMITER = '|';

    /**
     * The delimiter placed between individual tag/data pairs.
     */
    protected const PAIR_DELIMITER = '&';

    /**
     * The delimiter placed between a tag name and its serialized data.
     */
    protected const VALUE_DELIMITER = '=';

    /**
     * Create a new cache key generator.
     *
     * @param  RacheConfig  $config  The rache config accessor.
     */
    public function __construct(protected readonly RacheConfig $config)
    {
        //
    }

    /**
     * Generate the cache key for the given request and serialized tag data.
     *
     * @param  Request  $request  The request being cached.
     * @param  array<string, string>  $serializedTags  Tag name to serialized tag data.
     */
    public function generate(Request $request, array $serializedTags): string
    {
        $pairs = [];

        foreach ($serializedTags as $tag => $data) {
            $pairs[] = $tag.self::VALUE_DELIMITER.$data;
        }

        $key = implode(self::SEGMENT_DELIMITER, [
            $request->getHost(),
            strtoupper($request->getMethod()),
            trim($request->getPathInfo(), '/'),
            implode(self::PAIR_DELIMITER, $pairs),
        ]);

        return $this->config->prefix().'-'.hash('sha256', $key);
    }
}
