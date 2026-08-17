<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function businessId(Request $request): int
    {
        return (int) $request->user()->business_id;
    }
}
