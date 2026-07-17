<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;

class RecalculateHPP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hpp:recalculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate HPP (Weighted Average) for all products based on purchase history';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting HPP Recalculation...');

        $products = Product::where('type', 'barang')->get();

        foreach ($products as $product) {
            // Get all non-voided purchases for this product ordered by date
            $purchaseItems = PurchaseItem::where('product_id', $product->id)
                ->whereHas('purchase', function ($query) {
                    $query->where('payment_status', '!=', 'voided');
                })
                ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->orderBy('purchases.purchase_date', 'asc')
                ->orderBy('purchases.id', 'asc')
                ->select('purchase_items.*', 'purchases.purchase_date')
                ->get();

            if ($purchaseItems->isEmpty()) {
                continue;
            }

            $currentStock = 0;
            $currentHpp = 0;

            foreach ($purchaseItems as $item) {
                $stockAddition = $item->qty * ($item->base_multiplier ?? 1);
                $costPerBaseUnit = $item->unit_price / ($item->base_multiplier ?? 1);
                $totalStockAfter = $currentStock + $stockAddition;

                if ($totalStockAfter > 0) {
                    $currentHpp = (($currentStock * $currentHpp) + ($stockAddition * $costPerBaseUnit)) / $totalStockAfter;
                } else {
                    $currentHpp = $costPerBaseUnit;
                }

                $currentStock = $totalStockAfter;
            }

            $oldHpp = (float) $product->cost_price;
            $newHpp = round($currentHpp, 2);

            if ($oldHpp !== $newHpp) {
                $product->update(['cost_price' => $newHpp]);
                $this->info("Updated {$product->name}: Rp" . number_format($oldHpp, 2) . " -> Rp" . number_format($newHpp, 2));
            }
        }

        $this->info('HPP Recalculation completed!');
    }
}
