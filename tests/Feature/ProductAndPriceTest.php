<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PrintPrice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAndPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\CategorySeeder::class,
            \Database\Seeders\ProductSeeder::class,
            \Database\Seeders\PrintPriceSeeder::class,
            \Database\Seeders\AddonServiceSeeder::class,
        ]);

        $this->owner = User::where('username', 'admin')->first();
    }

    /** TC-10: CRUD produk barang */
    public function test_crud_product_barang(): void
    {
        $category = Category::first();

        // Create
        $response = $this->actingAs($this->owner)->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Produk Test Barang',
            'type' => 'barang',
            'price' => 5000,
            'cost_price' => 3000,
            'stock' => 50,
            'min_stock' => 10,
            'unit' => 'pcs',
        ]);
        $response->assertStatus(201);
        $productId = $response->json('product.id');

        // Check stock initial movement
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productId,
            'type' => 'in',
            'qty' => 50,
        ]);

        // Update
        $response = $this->actingAs($this->owner)->putJson("/api/products/{$productId}", [
            'category_id' => $category->id,
            'name' => 'Produk Updated',
            'type' => 'barang',
            'price' => 6000,
            'cost_price' => 3500,
            'unit' => 'pcs',
        ]);
        $response->assertOk();

        // Deactivate
        $response = $this->actingAs($this->owner)->deleteJson("/api/products/{$productId}");
        $response->assertOk();
        $this->assertFalse(Product::find($productId)->is_active);
    }

    /** TC-12: Adjustment stok tercatat di stock_movements */
    public function test_stock_adjustment_recorded(): void
    {
        $product = Product::where('type', 'barang')->first();
        $initialStock = $product->stock;

        $response = $this->actingAs($this->owner)->postJson("/api/products/{$product->id}/stock-adjust", [
            'qty' => 10,
            'notes' => 'Restock dari supplier',
        ]);

        $response->assertOk();
        $this->assertEquals($initialStock + 10, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'qty' => 10,
        ]);
    }

    /** TC-14: Kalkulasi harga cetak 12 kombinasi */
    public function test_all_12_print_price_combinations_exist(): void
    {
        $this->assertEquals(12, PrintPrice::count());

        foreach (['A4', 'F4', 'A3'] as $size) {
            foreach (['bw', 'color'] as $color) {
                foreach (['single', 'duplex'] as $side) {
                    $this->assertDatabaseHas('print_prices', [
                        'paper_size' => $size,
                        'color_type' => $color,
                        'side_type' => $side,
                    ]);
                }
            }
        }
    }

    /** TC-15 & TC-16: Tier grosir pricing */
    public function test_tier_pricing(): void
    {
        // A4 BW Single has tiers at 50 and 100
        $pp = PrintPrice::where('paper_size', 'A4')
            ->where('color_type', 'bw')
            ->where('side_type', 'single')
            ->first();

        // Below tier = normal price
        $normalPrice = $pp->getPriceForQty(10);
        $this->assertEquals($pp->price_per_sheet, $normalPrice);

        // At tier 50 = discounted
        $tier50Price = $pp->getPriceForQty(50);
        $this->assertLessThan((float) $pp->price_per_sheet, (float) $tier50Price);

        // At tier 100 = even more discounted
        $tier100Price = $pp->getPriceForQty(100);
        $this->assertLessThanOrEqual((float) $tier50Price, (float) $tier100Price);
    }

    /** TC-26 & TC-27: Dashboard returns correct data */
    public function test_dashboard_returns_expected_structure(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/reports/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'today_revenue', 'today_transactions', 'today_cost', 'today_profit',
                'month_revenue', 'top_products', 'low_stock', 'recent_transactions', 'chart_data',
            ]);
    }
}
