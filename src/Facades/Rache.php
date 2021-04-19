<?php

namespace Rache\Facades;

use Illuminate\Support\Facades\Facade;

class Rache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'rache';
    }
}