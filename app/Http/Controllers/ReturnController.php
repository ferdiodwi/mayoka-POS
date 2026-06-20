<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\ReturnTransaction;
use App\Models\ReturnItem;
use App\Models\Shift;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function store(Request $request, Transaction $transaction)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:transaction_items,id',
            'items.*.return_qty' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        $user = auth()->user();
        $activeShift = Shift::where('user_id', $user->id)->where('status', 'open')->first();

        if (!$activeShift) {
            return response()->json(['message' => 'Anda harus membuka shift terlebih dahulu untuk memproses retur.'], 403);
        }

        try {
            DB::beginTransaction();

            $totalRefund = 0;
            $returnItemsData = [];

            foreach ($request->items as $itemData) {
                $txItem = TransactionItem::where('id', $itemData['id'])
                            ->where('transaction_id', $transaction->id)
                            ->firstOrFail();

                if ($txItem->item_type !== 'product') {
                    throw new \Exception("Hanya barang (produk) yang dapat diretur.");
                }

                $maxReturn = $txItem->qty - $txItem->returned_qty;
                if ($itemData['return_qty'] > $maxReturn) {
                    throw new \Exception("Jumlah retur untuk {$txItem->description} melebihi batas yang diizinkan (maksimal {$maxReturn}).");
                }

                $itemRefund = $txItem->unit_price * $itemData['return_qty'];
                $totalRefund += $itemRefund;

                // Update returned_qty in transaction item
                $txItem->increment('returned_qty', $itemData['return_qty']);

                $returnItemsData[] = [
                    'transaction_item_id' => $txItem->id,
                    'product_id' => $txItem->product_id,
                    'qty' => $itemData['return_qty'],
                    'subtotal' => $itemRefund,
                ];

                // Return stock
                if ($txItem->product_id) {
                    $txItem->product->increment('stock', $itemData['return_qty']);
                    
                    StockMovement::create([
                        'product_id' => $txItem->product_id,
                        'type' => 'in',
                        'qty' => $itemData['return_qty'],
                        'reference' => 'RETUR-' . $transaction->invoice_number,
                        'notes' => 'Retur Penjualan',
                        'user_id' => $user->id,
                    ]);
                }
            }

            // Create Return record
            $returnTx = ReturnTransaction::create([
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'shift_id' => $activeShift->id,
                'refund_amount' => $totalRefund,
                'reason' => $request->reason,
            ]);

            foreach ($returnItemsData as $ritem) {
                $ritem['return_id'] = $returnTx->id;
                ReturnItem::create($ritem);
            }

            // Deduct cash from active shift
            $activeShift->decrement('cash_expected', $totalRefund);

            DB::commit();

            return response()->json([
                'message' => 'Retur berhasil diproses.',
                'refund_amount' => $totalRefund
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
