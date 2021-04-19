<?php

namespace Rache\Tags;

use Illuminate\Http\Request;

class Auth implements RacheTagInterface
{
    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request)
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
        $user = $this->request->user();
        return ['id' => $user->id ?? null];
    }
}