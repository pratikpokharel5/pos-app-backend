<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
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

    public function test_business_settings_require_valid_tax_rate(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/settings', [
            'business_name' => 'Pratik Store',
            'phone' => '9800000000',
            'default_tax_rate' => 'abc',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_tax_rate');
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

        $categoryResponse = $this->postJson('/api/categories', [
            'name' => 'Accessories',
        ]);

        $categoryResponse->assertCreated();

        $productResponse = $this->postJson('/api/products', [
            'category_id' => $categoryResponse->json('data.id'),
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

    public function test_sale_can_use_split_payments(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $saleResponse = $this->postJson('/api/sales', [
            'items' => [
                [
                    'item_name' => 'Custom Item',
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 400,
                ],
                [
                    'method' => 'online',
                    'amount' => 600,
                    'provider' => 'Fonepay',
                    'transaction_reference' => 'FP-001',
                ],
            ],
        ]);

        $saleResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payments.0.method', 'cash')
            ->assertJsonPath('data.payments.1.method', 'online')
            ->assertJsonPath('data.payments.1.provider', 'Fonepay');
    }

    public function test_sale_can_be_held_without_payments(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $saleResponse = $this->postJson('/api/sales', [
            'status' => 'held',
            'items' => [
                [
                    'item_name' => 'Held Item',
                    'quantity' => 2,
                    'unit_price' => 150,
                ],
            ],
            'payments' => [],
        ]);

        $saleResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'held')
            ->assertJsonCount(0, 'data.payments');
    }

    public function test_held_sale_does_not_create_new_customer(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/sales', [
            'status' => 'held',
            'customer' => [
                'name' => 'Held Buyer',
                'phone' => '9800000022',
            ],
            'items' => [
                [
                    'item_name' => 'Held Item',
                    'quantity' => 1,
                    'unit_price' => 200,
                ],
            ],
            'payments' => [],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'held')
            ->assertJsonPath('data.customer_id', null);

        $this->assertDatabaseMissing('customers', [
            'business_id' => $admin->business_id,
            'phone' => '9800000022',
        ]);
    }

    public function test_sales_index_excludes_held_sales(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $completedSale = $this->postJson('/api/sales', [
            'items' => [
                [
                    'item_name' => 'Completed Item',
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
        ]);

        $heldSale = Sale::query()->create([
            'business_id' => $admin->business_id,
            'invoice_number' => 'INV-HELD-0001',
            'user_id' => $admin->id,
            'status' => 'held',
            'subtotal' => 200,
            'grand_total' => 200,
        ]);

        $completedSale->assertCreated();

        $this->getJson('/api/sales')
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $completedSale->json('data.id'))
            ->assertJsonMissingPath('data.1');

        $this->getJson('/api/sales/held')
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $heldSale->id)
            ->assertJsonMissingPath('data.1');
    }

    public function test_dashboard_recent_sales_excludes_held_sales(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $completedSale = $this->postJson('/api/sales', [
            'items' => [
                [
                    'item_name' => 'Dashboard Item',
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
        ]);

        Sale::query()->create([
            'business_id' => $admin->business_id,
            'invoice_number' => 'INV-HELD-DASHBOARD',
            'user_id' => $admin->id,
            'status' => 'held',
            'subtotal' => 200,
            'grand_total' => 200,
            'sold_at' => now()->addMinute(),
        ]);

        $completedSale->assertCreated();

        $this->getJson('/api/dashboard/summary')
            ->assertSuccessful()
            ->assertJsonPath('recent_sales.0.id', $completedSale->json('data.id'))
            ->assertJsonMissingPath('recent_sales.1');
    }

    public function test_sales_report_summary_excludes_held_sales(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        Sale::query()->create([
            'business_id' => $admin->business_id,
            'invoice_number' => 'INV-REPORT-001',
            'user_id' => $admin->id,
            'status' => 'completed',
            'subtotal' => 1000,
            'discount_amount' => 100,
            'tax_amount' => 117,
            'grand_total' => 1017,
            'sold_at' => '2026-08-15 10:00:00',
        ]);

        Sale::query()->create([
            'business_id' => $admin->business_id,
            'invoice_number' => 'INV-REPORT-HELD',
            'user_id' => $admin->id,
            'status' => 'held',
            'subtotal' => 500,
            'discount_amount' => 0,
            'tax_amount' => 65,
            'grand_total' => 565,
            'sold_at' => '2026-08-15 11:00:00',
        ]);

        Sale::query()->create([
            'business_id' => $admin->business_id,
            'invoice_number' => 'INV-REPORT-OLD',
            'user_id' => $admin->id,
            'status' => 'completed',
            'subtotal' => 700,
            'discount_amount' => 0,
            'tax_amount' => 91,
            'grand_total' => 791,
            'sold_at' => '2026-08-14 10:00:00',
        ]);

        $this->getJson('/api/reports/sales-summary?from=2026-08-15&to=2026-08-15')
            ->assertSuccessful()
            ->assertJsonPath('invoice_count', 1)
            ->assertJsonPath('subtotal', 1000)
            ->assertJsonPath('discount_total', 100)
            ->assertJsonPath('tax_total', 117)
            ->assertJsonPath('revenue', 1017);
    }

    public function test_payment_report_summary_totals_completed_sale_payments(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $completedSale = Sale::query()->create([
            'business_id' => $admin->business_id,
            'invoice_number' => 'INV-PAYMENT-REPORT',
            'user_id' => $admin->id,
            'status' => 'completed',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'sold_at' => '2026-08-15 10:00:00',
        ]);
        $heldSale = Sale::query()->create([
            'business_id' => $admin->business_id,
            'invoice_number' => 'INV-PAYMENT-HELD',
            'user_id' => $admin->id,
            'status' => 'held',
            'subtotal' => 500,
            'grand_total' => 500,
            'sold_at' => '2026-08-15 11:00:00',
        ]);

        Payment::query()->create([
            'business_id' => $admin->business_id,
            'sale_id' => $completedSale->id,
            'method' => 'cash',
            'amount' => 400,
        ]);
        Payment::query()->create([
            'business_id' => $admin->business_id,
            'sale_id' => $completedSale->id,
            'method' => 'online',
            'amount' => 600,
        ]);
        Payment::query()->create([
            'business_id' => $admin->business_id,
            'sale_id' => $heldSale->id,
            'method' => 'cash',
            'amount' => 500,
        ]);

        $response = $this->getJson('/api/reports/payment-summary?from=2026-08-15&to=2026-08-15')
            ->assertSuccessful();

        $payments = collect($response->json())->keyBy('method');

        $this->assertSame(2, $payments->count());
        $this->assertSame(1, $payments['cash']['payment_count']);
        $this->assertSame(400, $payments['cash']['total']);
        $this->assertSame(1, $payments['online']['payment_count']);
        $this->assertSame(600, $payments['online']['total']);
    }

    public function test_top_products_report_totals_completed_sale_items(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $completedSale = Sale::query()->create([
            'business_id' => $admin->business_id,
            'invoice_number' => 'INV-TOP-PRODUCTS',
            'user_id' => $admin->id,
            'status' => 'completed',
            'subtotal' => 750,
            'grand_total' => 750,
            'sold_at' => '2026-08-15 10:00:00',
        ]);
        $heldSale = Sale::query()->create([
            'business_id' => $admin->business_id,
            'invoice_number' => 'INV-TOP-PRODUCTS-HELD',
            'user_id' => $admin->id,
            'status' => 'held',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'sold_at' => '2026-08-15 11:00:00',
        ]);

        SaleItem::query()->create([
            'business_id' => $admin->business_id,
            'sale_id' => $completedSale->id,
            'item_name' => 'Milk Tea',
            'quantity' => 2,
            'unit_price' => 100,
            'line_total' => 200,
        ]);
        SaleItem::query()->create([
            'business_id' => $admin->business_id,
            'sale_id' => $completedSale->id,
            'item_name' => 'Coffee',
            'quantity' => 1,
            'unit_price' => 550,
            'line_total' => 550,
        ]);
        SaleItem::query()->create([
            'business_id' => $admin->business_id,
            'sale_id' => $heldSale->id,
            'item_name' => 'Held Item',
            'quantity' => 10,
            'unit_price' => 100,
            'line_total' => 1000,
        ]);

        $this->getJson('/api/reports/top-products?from=2026-08-15&to=2026-08-15')
            ->assertSuccessful()
            ->assertJsonPath('0.item_name', 'Milk Tea')
            ->assertJsonPath('0.quantity_sold', 2)
            ->assertJsonPath('0.revenue', 200)
            ->assertJsonPath('1.item_name', 'Coffee')
            ->assertJsonPath('1.quantity_sold', 1)
            ->assertJsonPath('1.revenue', 550)
            ->assertJsonMissingPath('2');
    }

    public function test_held_sale_can_be_unheld(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $saleResponse = $this->postJson('/api/sales', [
            'status' => 'held',
            'items' => [
                [
                    'item_name' => 'Held Item',
                    'quantity' => 1,
                    'unit_price' => 200,
                ],
            ],
        ]);

        $saleResponse->assertCreated();

        $this->deleteJson("/api/sales/{$saleResponse->json('data.id')}/hold")
            ->assertSuccessful();

        $this->getJson("/api/sales/{$saleResponse->json('data.id')}")
            ->assertNotFound();
    }

    public function test_held_sale_cannot_be_loaded_as_invoice(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $saleResponse = $this->postJson('/api/sales', [
            'status' => 'held',
            'items' => [
                [
                    'item_name' => 'Held Item',
                    'quantity' => 1,
                    'unit_price' => 200,
                ],
            ],
            'payments' => [],
        ]);

        $saleResponse->assertCreated();

        $this->getJson("/api/sales/{$saleResponse->json('data.id')}/invoice")
            ->assertNotFound();
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
            'phone' => '9800000001',
            'status' => 'inactive',
        ]);

        $customerResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $this->patchJson("/api/customers/{$customerResponse->json('data.id')}/status", [
            'status' => 'inactive',
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'inactive');

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

    public function test_created_users_are_active_by_default(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/users', [
            'name' => 'Counter Staff',
            'phone' => '9811111111',
            'password' => 'password',
            'status' => 'inactive',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_user_status_can_be_changed_separately(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $staff = User::factory()->create([
            'business_id' => $admin->business_id,
            'role' => 'staff',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->patchJson("/api/users/{$staff->id}/status", [
            'status' => 'inactive',
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_user_cannot_disable_own_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->patchJson("/api/users/{$admin->id}/status", [
            'status' => 'inactive',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    }
}
