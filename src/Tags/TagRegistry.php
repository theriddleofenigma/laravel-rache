<?php

declare(strict_types=1);

namespace Rache\Tags;

use Illuminate\Http\Request;
use Rache\Config\RacheConfig;
use Rache\Exceptions\InvalidTagException;
use Rache\Exceptions\TagNotRegisteredException;

/**
 * Resolves configured tag names into `RacheTagInterface` instances.
 *
 * Instances are memoised per request so that a tag whose `getTagDetails()` does
 * real work (a database lookup, for example) is only constructed once even when
 * both the middleware and a manual `Rache::getTagData()` call ask for it.
 */
class TagRegistry
{
    /**
     * The resolved tag instances, keyed by tag name.
     *
     * @var array<string, RacheTagInterface>
     */
    protected array $resolved = [];

    /**
     * The request handed to every tag instance.
     */
    protected ?Request $request = null;

    /**
     * Create a new tag registry.
     *
     * @param  RacheConfig  $config  The rache config accessor.
     */
    public function __construct(protected readonly RacheConfig $config)
    {
        //
    }

    /**
     * Bind the request that subsequent tag instances are constructed with.
     *
     * Changing the request discards any previously resolved instances, since a
     * tag's data is derived from the request it was built for.
     *
     * @param  Request  $request  The request being handled.
     * @return $this
     */
    public function setRequest(Request $request): static
    {
        if ($this->request !== $request) {
            $this->resolved = [];
        }

        $this->request = $request;

        return $this;
    }

    /**
     * Get the request that tag instances are constructed with.
     */
    public function getRequest(): Request
    {
        return $this->request ??= Request::createFromGlobals();
    }

    /**
     * Determine whether the given tag name is registered in the config.
     *
     * @param  string  $tag  The tag name to look for.
     */
    public function has(string $tag): bool
    {
        return array_key_exists($tag, $this->config->tags());
    }

    /**
     * Get the registered tag names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->config->tags());
    }

    /**
     * Assert that the given tag name is registered in the config.
     *
     * @param  string  $tag  The tag name to verify.
     *
     * @throws TagNotRegisteredException
     */
    public function ensureRegistered(string $tag): void
    {
        if (! $this->has($tag)) {
            throw TagNotRegisteredException::for($tag, $this->names());
        }
    }

    /**
     * Get the tag instance registered under the given name.
     *
     * @param  string  $tag  The tag name to resolve.
     *
     * @throws TagNotRegisteredException
     * @throws InvalidTagException
     */
    public function instance(string $tag): RacheTagInterface
    {
        if (isset($this->resolved[$tag])) {
            return $this->resolved[$tag];
        }

        $this->ensureRegistered($tag);

        $class = $this->config->tags()[$tag];

        if (! class_exists($class)) {
            throw InvalidTagException::missingClass($tag, $class);
        }

        if (! is_subclass_of($class, RacheTagInterface::class)) {
            throw InvalidTagException::notATag($tag);
        }

        /** @var RacheTagInterface $instance */
        $instance = new $class($this->getRequest());

        return $this->resolved[$tag] = $instance;
    }

    /**
     * Get the tag data for the given tag name.
     *
     * @param  string  $tag  The tag name to read data for.
     * @return array<array-key, mixed>
     *
     * @throws TagNotRegisteredException
     * @throws InvalidTagException
     */
    public function data(string $tag): array
    {
        return $this->instance($tag)->getTagDetails();
    }

    /**
     * Forget every resolved tag instance.
     *
     * @return $this
     */
    public function flushResolved(): static
    {
        $this->resolved = [];

        return $this;
    }
}
