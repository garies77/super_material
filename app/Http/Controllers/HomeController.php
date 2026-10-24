<?php

namespace App\Http\Controllers;

use App\Services\HomepageService;

class HomeController extends Controller
{
    // Tanpa parameter: pratinjau kampanye & layout memanggil controller ini langsung
    public function __invoke()
    {
        $homepage = app(HomepageService::class);

        $sections = $homepage->sections()->map(fn ($section) => [
            'section' => $section,
            'data' => $homepage->resolve($section),
        ]);

        return view('home', [
            'sections' => $sections,
            'heroBanner' => $sections->contains(fn ($s) => $s['section']->type === 'hero') ? HomepageService::heroBanner() : null,
        ]);
    }
}
