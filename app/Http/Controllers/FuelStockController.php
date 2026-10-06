<?php

namespace App\Http\Controllers;

use App\Services\FuelStockService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FuelStockController extends Controller
{
    public function __invoke(Request $request, FuelStockService $stock): View
    {
        $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today']]);

        $date = $request->date('date') ?? today();

        return view('stock.index', [
            'date' => $date,
            'rows' => $stock->summary($date),
        ]);
    }
}
