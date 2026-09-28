<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // Figures are wired in once the sales, stock and expense modules exist.
        return view('dashboard.index');
    }
}
