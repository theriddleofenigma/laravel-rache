<?php

declare(strict_types=1);

namespace Rache\Tags;

use Illuminate\Http\Request;

/**
 * Convenience base class that stores the request for concrete tags.
 *
 * Extending this is optional; any class implementing `RacheTagInterface` works.
 */
abstract class Tag implements RacheTagInterface
{
    /**
     * Create a new tag for the given request.
     *
     * @param  Request  $request  The request being handled.
     */
    public function __construct(protected readonly Request $request)
    {
        //
    }

    /**
     * Get the tag details of this rache tag.
     *
     * @return array<array-key, mixed>
     */
    abstract public function getTagDetails(): array;
}
