<?php

declare(strict_types=1);

namespace Rache\Tags;

/**
 * Scopes a cached response to the full input of the request.
 */
class Request extends Tag
{
    /**
     * Get the tag details of this rache tag.
     *
     * @return array<array-key, mixed>
     */
    public function getTagDetails(): array
    {
        return $this->request->all();
    }
}
