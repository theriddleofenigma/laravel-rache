<?php

declare(strict_types=1);

namespace Rache\Exceptions;

/**
 * Thrown when a tag is referenced that has no entry in the rache config.
 */
class TagNotRegisteredException extends RacheException
{
    /**
     * Create an exception for the given unknown tag name.
     *
     * @param  string  $tag  The tag name that could not be resolved.
     * @param  list<string>  $registered  The tag names that are registered.
     */
    public static function for(string $tag, array $registered = []): self
    {
        $message = "Tag name is not declared in the rache config for $tag";

        if ($registered !== []) {
            $message .= '. Registered tags: '.implode(', ', $registered);
        }

        return new self($message);
    }
}
