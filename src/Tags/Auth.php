<?php

declare(strict_types=1);

namespace Rache\Tags;

/**
 * Scopes a cached response to the authenticated user.
 */
class Auth extends Tag
{
    /**
     * Get the tag details of this rache tag.
     *
     * @param  mixed  $userId  An explicit user id, used when flushing another user's cache.
     * @return array{id: mixed}
     */
    public function getTagDetails(mixed $userId = null): array
    {
        if ($userId !== null) {
            return ['id' => $userId];
        }

        $user = $this->request->user();

        return [
            // getAuthIdentifier() honours the model's own primary key. Reading
            // ->id directly returned null for any user model keyed by something
            // other than "id", collapsing every authenticated visitor onto a
            // single cache entry.
            'id' => $user?->getAuthIdentifier(),
        ];
    }
}
