<?php

namespace Rache\Tags;

use Illuminate\Http\Request as LaravelRequest;

class Pagination implements RacheTagInterface
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
        return [
            'page' => $this->request->input('page'),
        ];
    }
}