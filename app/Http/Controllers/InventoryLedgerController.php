<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\InventoryLedger;

class InventoryLedgerController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::orderBy('name', 'asc')->get();
        
        $productId = $request->get('product_id', $products->first()->id ?? null);
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate   = $request->get('end_date', date('Y-m-t'));

        $selectedProduct = $products->firstWhere('id', $productId);

        $ledgers = collect();
        if ($productId) {
            $ledgers = InventoryLedger::where('product_id', $productId)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->orderBy('transaction_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        return view('product.ledger', compact('products', 'productId', 'selectedProduct', 'startDate', 'endDate', 'ledgers'));
    }
}