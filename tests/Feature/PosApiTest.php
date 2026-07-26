<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_settings_use_current_schema(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/settings', [
            'business_name' => 'Pratik Store',
            'logo' => 'logo.png',
            'address' => 'Kathmandu',
            'phone' => '9800000000',
            'email' => 'store@example.com',
            'tax_enabled' => true,
            'default_tax_rate' => 13,
            'online_payment_enabled' => true,
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.business_name', 'Pratik Store')
            ->assertJsonPath('data.logo', 'logo.png')
            ->assertJsonPath('data.phone', '9800000000')
            ->assertJsonPath('data.default_tax_rate', 13)
            ->assertJsonMissingPath('data.logo_path')
            ->assertJsonMissingPath('data.currency_code')
            ->assertJsonMissingPath('data.currency_symbol')
            ->assertJsonMissingPath('data.invoice_footer')
            ->assertJsonMissingPath('data.card_payment_enabled');
    }

    public function test_business_settings_require_phone(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/settings', [
            'business_name' => 'Pratik Store',
            'default_tax_rate' => 13,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_product_can_be_created_and_used_in_a_sale(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $categoryResponse = $this->postJson('/api/categories', [
            'name' => 'Electronics',
        ]);

        $categoryResponse->assertCreated();

        $productResponse = $this->postJson('/api/products', [
            'category_id' => $categoryResponse->json('data.id'),
            'name' => 'Laptop',
            'sku' => 'LAP-001',
            'price' => 100000,
        ]);

        $productResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Laptop');

        $saleResponse = $this->postJson('/api/sales', [
            'customer' => [
                'name' => 'Walk In Buyer',
                'phone' => '9800000000',
            ],
            'discount_amount' => 1000,
            'tax_rate' => 0,
            'items' => [
                [
                    'product_id' => $productResponse->json('data.id'),
                    'quantity' => 1,
                    'discount_amount' => 0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 99000,
                ],
            ],
        ]);

        $saleResponse
            ->assertCreated()
            ->assertJsonPath('data.grand_total', '99000.00')
            ->assertJsonPath('data.items.0.item_name', 'Laptop')
            ->assertJsonPath('data.payments.0.method', 'cash');

        $this->assertStringStartsWith('INV-', $saleResponse->json('data.invoice_number'));
    }

    public function test_sale_uses_catalog_price_for_selected_products(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $productResponse = $this->postJson('/api/products', [
            'name' => 'Keyboard',
            'price' => 2500,
        ]);

        $productResponse->assertCreated();

        $saleResponse = $this->postJson('/api/sales', [
            'items' => [
                [
                    'product_id' => $productResponse->json('data.id'),
                    'quantity' => 1,
                    'unit_price' => 1,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 2500,
                ],
            ],
        ]);

        $saleResponse
            ->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '2500.00')
            ->assertJsonPath('data.grand_total', '2500.00');
    }

    public function test_custom_sale_items_require_unit_price(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/sales', [
            'items' => [
                [
                    'item_name' => 'Custom Service',
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 0,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.unit_price');
    }

    public function test_inactive_customers_cannot_be_used_for_new_sales(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $customerResponse = $this->postJson('/api/customers', [
            'name' => 'Archived Buyer',
            'status' => 'inactive',
        ]);

        $customerResponse->assertCreated();

        $this->postJson('/api/sales', [
            'customer_id' => $customerResponse->json('data.id'),
            'items' => [
                [
                    'item_name' => 'Custom Item',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 100,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');
    }
}
