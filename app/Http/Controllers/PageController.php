<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        return view('page', ['page' => Page::published()->where('slug', $slug)->firstOrFail()]);
    }
}
