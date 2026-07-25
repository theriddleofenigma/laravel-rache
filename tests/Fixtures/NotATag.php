<?php

declare(strict_types=1);

namespace Rache\Tests\Fixtures;

use Illuminate\Http\Request;

/**
 * A class registered as a rache tag that does not implement the tag contract.
 */
class NotATag
{
    /**
     * Accept the request the registry passes to every tag.
     *
     * @param  Request  $request  The request being handled.
     */
    public function __construct(Request $request)
    {
        //
    }
}
