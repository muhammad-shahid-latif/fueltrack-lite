<?php

namespace App\Http\Controllers;

use App\Support\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    /** One year; the pick survives logging out. */
    private const COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * The first page of the app: pick a network, then log in.
     */
    public function home(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return Brand::current() ? redirect()->route('login') : redirect()->route('brands.index');
    }

    public function index(): View|RedirectResponse
    {
        if (Brand::locked()) {
            return redirect()->route('home');
        }

        return view('brands.index', ['brands' => Brand::all(), 'current' => Brand::current()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(Brand::locked() !== null, 404);

        $request->validate(['brand' => ['required', Rule::in(Brand::all()->keys())]]);

        Cookie::queue(Brand::COOKIE, $request->input('brand'), self::COOKIE_MINUTES);

        return redirect()->route($request->user() ? 'dashboard' : 'login');
    }
}
