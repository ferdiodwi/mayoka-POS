<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PrintPrice;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->kasir = User::where('username', 'kasir1')->first();
        $this->shift = Shift::create([
            'user_id' => $this->kasir->id,
            'started_at' => now(),
            'cash_start' => 100000,
            'status' => 'open',
        ]);
    }

    /** TC-18: Checkout transaksi lengkap */
    public function test_full_checkout_with_print_product_addon(): void
    {
        $product = Product::where('type', 'barang')->first();
        $printPrice = PrintPrice::first();
        $addon = \App\Models\AddonService::first();

        $response = $this->actingAs($this->kasir)->postJson('/api/transactions/checkout', [
            'items' => [
                [
                    'itemType' => 'print',
                    'printPriceId' => $printPrice->id,
                    'description' => 'A4 BW Single',
                    'qty' => 10,
                    'unitPrice' => $printPrice->price_per_sheet,
                    'costPerSheet' => $printPrice->cost_per_sheet,
                    'discount' => 0,
                    'addons' => [
                        ['addonServiceId' => $addon->id, 'name' => $addon->name, 'price' => $addon->price, 'qty' => 1],
                    ],
                ],
                [
                    'itemType' => 'product',
                    'productId' => $product->id,
                    'description' => $product->name,
                    'qty' => 2,
                    'unitPrice' => $product->price,
                    'costPrice' => $product->cost_price,
                    'discount' => 0,
                    'addons' => [],
                ],
            ],
            'payment_method' => 'cash',
            'cash_paid' => 100000,
            'discount' => 0,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'transaction' => ['id', 'invoice_number', 'total', 'items']]);

        // Verify 3 items: print + addon + product
        $this->assertEquals(3, count($response->json('transaction.items')));
    }

    /** TC-19: Stok ATK berkurang setelah checkout */
    public function test_product_stock_decreases_after_checkout(): void
    {
        $product = Product::where('type', 'barang')->first();
        $initialStock = $product->stock;

        $this->actingAs($this->kasir)->postJson('/api/transactions/checkout', [
            'items' => [
                [
                    'itemType' => 'product',
                    'productId' => $product->id,
                    'description' => $product->name,
                    'qty' => 3,
                    'unitPrice' => $product->price,
                    'costPrice' => $product->cost_price,
                    'discount' => 0,
                    'addons' => [],
                ],
            ],
            'payment_method' => 'cash',
            'cash_paid' => 100000,
        ]);

        $this->assertEquals($initialStock - 3, $product->fresh()->stock);
    }

    /** TC-21: Stock movements tercatat dengan reference invoice */
    public function test_stock_movement_recorded_with_invoice(): void
    {
        $product = Product::where('type', 'barang')->first();

        $response = $this->actingAs($this->kasir)->postJson('/api/transactions/checkout', [
            'items' => [
                [
                    'itemType' => 'product',
                    'productId' => $product->id,
                    'description' => $product->name,
                    'qty' => 1,
                    'unitPrice' => $product->price,
                    'costPrice' => $product->cost_price,
                    'discount' => 0,
                    'addons' => [],
                ],
            ],
            'payment_method' => 'cash',
            'cash_paid' => 100000,
        ]);

        $invoice = $response->json('transaction.invoice_number');
        $movement = StockMovement::where('product_id', $product->id)
            ->where('reference', $invoice)->first();

        $this->assertNotNull($movement);
        $this->assertEquals('out', $movement->type);
    }

    /** TC-22: Invoice number format */
    public function test_invoice_number_format(): void
    {
        $response = $this->actingAs($this->kasir)->postJson('/api/transactions/checkout', [
            'items' => [
                ['itemType' => 'print', 'description' => 'Test', 'qty' => 1, 'unitPrice' => 200, 'costPerSheet' => 80, 'discount' => 0, 'addons' => []],
            ],
            'payment_method' => 'cash',
            'cash_paid' => 200,
        ]);

        $invoice = $response->json('transaction.invoice_number');
        $this->assertMatchesRegularExpression('/^INV-\d{8}-\d{4}$/', $invoice);
    }

    /** TC-24: Validasi cash_paid >= total */
    public function test_cash_paid_must_be_sufficient(): void
    {
        $response = $this->actingAs($this->kasir)->postJson('/api/transactions/checkout', [
            'items' => [
                ['itemType' => 'print', 'description' => 'Test', 'qty' => 10, 'unitPrice' => 200, 'costPerSheet' => 80, 'discount' => 0, 'addons' => []],
            ],
            'payment_method' => 'cash',
            'cash_paid' => 100, // Total is 2000, paying only 100
        ]);

        $response->assertStatus(422);
    }

    /** TC-25: Kembalian dihitung benar */
    public function test_cash_change_calculated_correctly(): void
    {
        $response = $this->actingAs($this->kasir)->postJson('/api/transactions/checkout', [
            'items' => [
                ['itemType' => 'print', 'description' => 'A4 BW', 'qty' => 5, 'unitPrice' => 200, 'costPerSheet' => 80, 'discount' => 0, 'addons' => []],
            ],
            'payment_method' => 'cash',
            'cash_paid' => 5000,
        ]);

        $response->assertStatus(201);
        // Total = 5 * 200 = 1000, Change = 5000 - 1000 = 4000
        $this->assertEquals('4000.00', $response->json('transaction.cash_change'));
    }
}
