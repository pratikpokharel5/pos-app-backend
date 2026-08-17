<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_fetch_profile_and_logout(): void
    {
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $token = $loginResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@example.com');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();
    }

    public function test_owner_can_register_business_and_login_with_phone(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'business_name' => 'New Counter Store',
            'owner_name' => 'New Owner',
            'phone' => '9820000000',
            'password' => 'password',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('user.name', 'New Owner')
            ->assertJsonPath('user.phone', '9820000000')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.business.name', 'New Counter Store');

        $this->assertDatabaseHas('businesses', [
            'name' => 'New Counter Store',
            'phone' => '9820000000',
        ]);

        $this->assertDatabaseHas('business_settings', [
            'tax_enabled' => false,
            'default_tax_rate' => 0,
            'online_payment_enabled' => true,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => '9820000000',
            'password' => 'password',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.phone', '9820000000')
            ->assertJsonPath('user.business.name', 'New Counter Store');
    }
}
