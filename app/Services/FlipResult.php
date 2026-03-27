<?php

namespace App\Services;

use App\Models\Prize;

class FlipResult
{
    public function __construct(
        public readonly Prize|null $prize = null,
        public readonly string|null $message = null,
    )
    {
        //
    }
}