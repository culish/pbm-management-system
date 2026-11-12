<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, bool $active = true): User
    {
        $this->seed(RolePermissionSeeder::class);

        return User::create([
            'name' => 'Pengguna Uji', 'email' => "{$role}@test.local", 'password' => 'rahasia-uji',
            'role_id' => Role::where('key', $role)->value('id'), 'is_active' => $active,
        ]);
    }

    public function test_login_returns_token_and_permissions(): void
    {
        $this->user('dosen');

        $this->postJson('/api/auth/login', ['email' => 'dosen@test.local', 'password' => 'rahasia-uji'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['permissions']])
            ->assertJsonPath('user.role.key', 'dosen');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->user('dosen');

        $this->postJson('/api/auth/login', ['email' => 'dosen@test.local', 'password' => 'salah'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $this->user('dosen', active: false);

        $this->postJson('/api/auth/login', ['email' => 'dosen@test.local', 'password' => 'rahasia-uji'])->assertForbidden();
    }

    public function test_permission_middleware_blocks_other_roles(): void
    {
        $laboran = $this->user('laboran');
        $this->getJson('/api/dashboard')->assertUnauthorized();

        $this->actingAs($laboran)->getJson('/api/soal/opsi')->assertForbidden();
        $this->actingAs($laboran)->getJson('/api/dashboard')->assertOk();
    }

    public function test_repeated_failures_lock_only_that_email(): void
    {
        $this->user('dosen');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'dosen@test.local', 'password' => 'salah'])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['email' => 'dosen@test.local', 'password' => 'rahasia-uji'])->assertStatus(429);
        $this->postJson('/api/auth/login', ['email' => 'lain@test.local', 'password' => 'salah'])->assertStatus(422);
    }
}
