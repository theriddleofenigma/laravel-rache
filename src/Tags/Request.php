<?php

namespace Rache\Tags;

use Illuminate\Http\Request as LaravelRequest;

class Request implements RacheTagInterface
{
    /**
     * @var LaravelRequest
     */
    protected $request;

    /**
     * Request constructor.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(LaravelRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Get the tag details of this rache tag.
     *
     * @return array
     */
    public function getTagDetails(): array
    {
        return $this->request->all();
    }
}