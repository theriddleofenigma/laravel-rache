<?php

namespace Rache\Tags;

interface RacheTagInterface
{
    /**
     * Get the tag details of this rache tag.
     *
     * @return array
     */
    public function getTagDetails(): array;
}