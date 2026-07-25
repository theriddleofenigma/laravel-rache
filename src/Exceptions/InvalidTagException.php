<?php

declare(strict_types=1);

namespace Rache\Exceptions;

use Rache\Tags\RacheTagInterface;

/**
 * Thrown when a configured tag cannot be turned into a usable tag instance.
 */
class InvalidTagException extends RacheException
{
    /**
     * Create an exception for a tag whose configured class does not exist.
     *
     * @param  string  $tag  The tag name as registered in the config.
     * @param  string  $class  The class name the config points at.
     */
    public static function missingClass(string $tag, string $class): self
    {
        return new self(
            "The class [$class] configured for the [$tag] rache tag does not exist."
        );
    }

    /**
     * Create an exception for a tag class that does not implement the contract.
     *
     * @param  string  $tag  The tag name as registered in the config.
     */
    public static function notATag(string $tag): self
    {
        return new self(
            'All the tags should implement the '.class_basename(RacheTagInterface::class).
            ". Check the $tag Tag class whether it implements ".class_basename(RacheTagInterface::class).'.'
        );
    }
}
