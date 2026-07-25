<?php

declare(strict_types=1);

namespace Rache\Tags;

use Illuminate\Http\Request;

/**
 * Contract every rache tag must satisfy.
 *
 * A tag turns the current request into the small set of values that make a
 * cached response unique. Two requests share a cached response only when every
 * applied tag returns equal data for both of them.
 */
interface RacheTagInterface
{
    /**
     * Create a new tag for the given request.
     *
     * @param  Request  $request  The request being handled.
     */
    public function __construct(Request $request);

    /**
     * Get the tag details of this rache tag.
     *
     * @return array<array-key, mixed>
     */
    public function getTagDetails(): array;
}
