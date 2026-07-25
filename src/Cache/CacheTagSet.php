<?php

declare(strict_types=1);

namespace Rache\Cache;

/**
 * Builds the list of cache tags a cached response is stored against.
 *
 * For every rache tag applied to a route, four increasingly specific cache tags
 * are recorded so that `Rache::flushTag()` can target any combination of
 * tag / tag+data / tag+route / tag+data+route. A global `rache` tag is always
 * present so that `Rache::flushAll()` can clear everything the package wrote.
 */
class CacheTagSet
{
    /**
     * The tag applied to every response cached by this package.
     */
    public const GLOBAL_TAG = 'rache';

    /**
     * Build the full, de-duplicated cache tag list for a response.
     *
     * @param  array<string, string>  $serializedTags  Tag name to serialized tag data.
     * @param  string  $routeName  The name of the route being cached.
     * @return list<string>
     */
    public function build(array $serializedTags, string $routeName): array
    {
        // The global tag is seeded unconditionally. Building it inside the loop
        // below would leave routes that use the middleware without any tags
        // (`->middleware('rache')`) untagged and therefore unreachable from
        // Rache::flushAll().
        $cacheTags = [self::GLOBAL_TAG, $routeName];

        foreach ($serializedTags as $tag => $data) {
            $cacheTags[] = $tag;
            $cacheTags[] = self::for($tag, $data);
            $cacheTags[] = self::for($tag, null, $routeName);
            $cacheTags[] = self::for($tag, $data, $routeName);
        }

        return array_values(array_unique($cacheTags));
    }

    /**
     * Build a single cache tag for the given tag name, data and route.
     *
     * @param  string  $tag  The rache tag name.
     * @param  string|null  $data  The serialized tag data, if the tag is data scoped.
     * @param  string|null  $routeName  The route name, if the tag is route scoped.
     */
    public static function for(string $tag, ?string $data = null, ?string $routeName = null): string
    {
        $routeName = ($routeName === null || $routeName === '') ? '' : $routeName;
        $data = ($data === null || $data === '') ? '' : $data;

        $prefix = $routeName !== '' ? ':' : '';
        $suffix = $data !== '' ? ':' : '';

        return "{$routeName}{$prefix}{$tag}{$suffix}{$data}";
    }
}
