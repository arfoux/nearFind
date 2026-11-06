<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class NeedsController extends Controller
{
    /**
     * Browse every need around Sukajadi: the same list the map plots, rendered
     * as text with a free-text search and the category chips.
     */
    public function index(): View
    {
        return view('needs.index', [
            'categories' => config('needs.categories'),
            'needs' => config('needs.needs'),
        ]);
    }

    /**
     * Recap of what has been reported so far: totals, a per-category breakdown,
     * and the needs ranked by support.
     */
    public function report(): View
    {
        $needs = config('needs.needs');
        $categories = config('needs.categories');

        $perCategory = [];
        $supporters = 0;
        $opportunities = 0;
        $peak = 1;

        foreach ($needs as $need) {
            $category = (string) $need['category'];
            $perCategory[$category] = ($perCategory[$category] ?? 0) + 1;

            if ($perCategory[$category] > $peak) {
                $peak = $perCategory[$category];
            }

            $supporters += (int) $need['supporters'];

            if (! empty($need['opportunity'])) {
                $opportunities = $opportunities + 1;
            }
        }

        // Most supported first. Plain insertion sort: `usort` and the spaceship
        // operator are not part of the compiled surface.
        $ranked = [];

        foreach ($needs as $need) {
            $placed = false;
            $next = [];

            foreach ($ranked as $existing) {
                if (! $placed && (int) $need['supporters'] > (int) $existing['supporters']) {
                    $next[] = $need;
                    $placed = true;
                }

                $next[] = $existing;
            }

            if (! $placed) {
                $next[] = $need;
            }

            $ranked = $next;
        }

        return view('needs.report', [
            'categories' => $categories,
            'needs' => $ranked,
            'perCategory' => $perCategory,
            'peak' => $peak,
            'supporters' => $supporters,
            'opportunities' => $opportunities,
        ]);
    }
}
