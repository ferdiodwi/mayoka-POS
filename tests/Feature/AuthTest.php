<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\UserSeeder::class);
    }

    /** TC-01: Login berhasil dengan kredensial valid */
    public function test_login_success(): void
    {
        $response = $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user'])
            ->assertJson(['user' => ['username' => 'admin', 'role' => 'owner']]);
    }

    /** TC-02: Login gagal dengan password salah */
    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401);
    }

    /** TC-03: Login gagal jika user is_active = false */
    public function test_login_fails_if_inactive(): void
    {
        $user = User::where('username', 'kasir1')->first();
        $user->update(['is_active' => false]);

        $response = $this->postJson('/api/login', [
            'username' => 'kasir1',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    /** TC-04: Role middleware memblokir kasir dari endpoint owner */
    public function test_kasir_blocked_from_owner_endpoints(): void
    {
        $kasir = User::where('username', 'kasir1')->first();

        $response = $this->actingAs($kasir)->getJson('/api/users');
        $response->assertStatus(403);

        $response = $this->actingAs($kasir)->getJson('/api/reports/dashboard');
        $response->assertStatus(403);
    }

    /** TC-05: CRUD user */
    public function test_owner_can_crud_users(): void
    {
        $owner = User::where('username', 'admin')->first();

        // Create
        $response = $this->actingAs($owner)->postJson('/api/users', [
            'name' => 'Kasir Baru',
            'username' => 'kasir_baru',
            'password' => 'password123',
            'role' => 'kasir',
        ]);
        $response->assertStatus(201);
        $userId = $response->json('user.id');

        // Update
        $response = $this->actingAs($owner)->putJson("/api/users/{$userId}", [
            'name' => 'Kasir Updated',
            'username' => 'kasir_baru',
            'role' => 'kasir',
        ]);
        $response->assertOk();

        // Deactivate
        $response = $this->actingAs($owner)->deleteJson("/api/users/{$userId}");
        $response->assertOk();
        $this->assertFalse(User::find($userId)->is_active);
    }
}
