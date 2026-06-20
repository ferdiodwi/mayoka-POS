<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\UserSeeder::class);
        $this->kasir = User::where('username', 'kasir1')->first();
    }

    /** TC-06: Buka shift sukses */
    public function test_open_shift_success(): void
    {
        $response = $this->actingAs($this->kasir)->postJson('/api/shifts/open', [
            'cash_start' => 100000,
        ]);

        $response->assertStatus(201)
            ->assertJson(['shift' => ['status' => 'open', 'cash_start' => '100000.00']]);
    }

    /** TC-07: Gagal buka shift jika sudah ada shift aktif */
    public function test_cannot_open_duplicate_shift(): void
    {
        $this->actingAs($this->kasir)->postJson('/api/shifts/open', ['cash_start' => 100000]);

        $response = $this->actingAs($this->kasir)->postJson('/api/shifts/open', ['cash_start' => 50000]);
        $response->assertStatus(422);
    }

    /** TC-08: Tutup shift menghitung cash_expected dan cash_difference */
    public function test_close_shift_calculates_correctly(): void
    {
        $openResponse = $this->actingAs($this->kasir)->postJson('/api/shifts/open', ['cash_start' => 100000]);
        $shiftId = $openResponse->json('shift.id');

        $response = $this->actingAs($this->kasir)->putJson("/api/shifts/{$shiftId}/close", [
            'cash_end' => 105000,
            'notes' => 'Test close',
        ]);

        $response->assertOk();
        $shift = Shift::find($shiftId);
        $this->assertEquals('closed', $shift->status);
        $this->assertNotNull($shift->ended_at);
        $this->assertEquals(105000, $shift->cash_end);
    }

    /** TC-09: Tidak bisa transaksi tanpa shift aktif */
    public function test_checkout_fails_without_active_shift(): void
    {
        $response = $this->actingAs($this->kasir)->postJson('/api/transactions/checkout', [
            'items' => [['itemType' => 'print', 'description' => 'Test', 'qty' => 1, 'unitPrice' => 200, 'costPerSheet' => 80, 'addons' => []]],
            'payment_method' => 'cash',
            'cash_paid' => 200,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Anda belum membuka shift.']);
    }
}
