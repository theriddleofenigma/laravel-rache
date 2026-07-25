<?php

declare(strict_types=1);

namespace Rache\Tags;

/**
 * Scopes a cached response to the requested page.
 */
class Pagination extends Tag
{
    /**
     * Get the tag details of this rache tag.
     *
     * @return array{page: string|null}
     */
    public function getTagDetails(): array
    {
        $page = $this->request->input('page');

        return [
            // Cast to string so that `?page=2` and `?page[]=2`-style input, or a
            // JSON body carrying an integer, all collapse to the same tag data
            // and therefore to the same cache entry.
            'page' => is_scalar($page) ? (string) $page : null,
        ];
    }
}
