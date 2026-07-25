<?php

declare(strict_types=1);

namespace Rache\Exceptions;

/**
 * Thrown when the request being cached is not bound to a named route.
 *
 * Rache derives its cache tags from the route name, so an unnamed route cannot
 * be tagged (and therefore cannot be flushed) in a way that is distinguishable
 * from any other unnamed route.
 */
class MissingRouteNameException extends RacheException
{
    /**
     * Create an exception for a request whose route carries no name.
     *
     * @param  string  $uri  The request URI, used to make the message actionable.
     */
    public static function forUri(string $uri): self
    {
        return new self(
            "The rache middleware requires a named route, but the route for [$uri] has no name. ".
            'Add ->name(\'your.route.name\') to the route definition.'
        );
    }

    /**
     * Create an exception for a request that is not bound to a route at all.
     */
    public static function noRoute(): self
    {
        return new self(
            'The rache middleware requires a named route, but the request is not bound to a route. '.
            'Make sure the middleware is applied as route middleware and not as global middleware.'
        );
    }
}
