<?php

declare(strict_types=1);

namespace Rache\Routing;

use Illuminate\Http\Request;
use Rache\Exceptions\MissingRouteNameException;

/**
 * Resolves the name of the route a request is bound to.
 *
 * Laravel exposes the route as an `Illuminate\Routing\Route` object while Lumen
 * exposes it as a `[handler, action, parameters]` array, so both shapes are
 * handled here rather than at the call sites.
 */
class RouteNameResolver
{
    /**
     * Get the name of the route the given request resolved to.
     *
     * @param  Request  $request  The request being handled.
     *
     * @throws MissingRouteNameException
     */
    public function resolve(Request $request): string
    {
        $route = $request->route();

        if ($route === null) {
            throw MissingRouteNameException::noRoute();
        }

        $name = is_array($route)
            ? $this->nameFromArrayRoute($route)
            : $this->nameFromRouteObject($route);

        if (! is_string($name) || trim($name) === '') {
            throw MissingRouteNameException::forUri('/'.ltrim($request->getPathInfo(), '/'));
        }

        return $name;
    }

    /**
     * Extract the route name from a Lumen style route array.
     *
     * @param  array<array-key, mixed>  $route  The `[handler, action, parameters]` tuple.
     */
    protected function nameFromArrayRoute(array $route): ?string
    {
        $action = $route[1] ?? null;

        if (! is_array($action)) {
            return null;
        }

        $name = $action['as'] ?? null;

        return is_string($name) ? $name : null;
    }

    /**
     * Extract the route name from an Illuminate route object.
     *
     * @param  object  $route  The resolved route instance.
     */
    protected function nameFromRouteObject(object $route): ?string
    {
        if (method_exists($route, 'getName')) {
            $name = $route->getName();

            if (is_string($name)) {
                return $name;
            }
        }

        if (method_exists($route, 'getAction')) {
            $name = $route->getAction('as');

            if (is_string($name)) {
                return $name;
            }
        }

        return null;
    }
}
