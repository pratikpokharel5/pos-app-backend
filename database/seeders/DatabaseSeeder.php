<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff User',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'status' => 'active',
            ],
        );

        BusinessSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'business_name' => 'Pratik POS Store',
                'logo' => null,
                'address' => 'Kathmandu, Nepal',
                'phone' => '9800000000',
                'email' => 'hello@pratikpos.test',
                'tax_enabled' => true,
                'default_tax_rate' => 13,
                'online_payment_enabled' => true,
            ],
        );

        $categories = collect([
            [
                'name' => 'Electronics',
                'description' => 'Laptops, keyboards, mice, and accessories.',
            ],
            [
                'name' => 'Clothing',
                'description' => 'Simple apparel items for retail demo data.',
            ],
            [
                'name' => 'Food & Drinks',
                'description' => 'Cafe and restaurant style items.',
            ],
            [
                'name' => 'Music',
                'description' => 'Guitars and music accessories.',
            ],
        ])->mapWithKeys(fn (array $category): array => [
            $category['name'] => Category::query()->updateOrCreate(
                ['name' => $category['name']],
                [
                    'description' => $category['description'],
                    'status' => 'active',
                ],
            ),
        ]);

        $products = collect([
            [
                'category' => 'Electronics',
                'name' => 'Dell Inspiron 15',
                'sku' => 'ELEC-LAP-001',
                'price' => 85000,
                'description' => 'Everyday laptop for students and small offices.',
            ],
            [
                'category' => 'Electronics',
                'name' => 'Logitech Wireless Mouse',
                'sku' => 'ELEC-MOU-001',
                'price' => 1200,
                'description' => 'Compact wireless mouse.',
            ],
            [
                'category' => 'Clothing',
                'name' => 'Cotton T-Shirt',
                'sku' => 'CLTH-TSH-001',
                'price' => 950,
                'description' => 'Plain cotton t-shirt.',
            ],
            [
                'category' => 'Food & Drinks',
                'name' => 'Chicken Burger',
                'sku' => 'FOOD-BUR-001',
                'price' => 350,
                'description' => 'Cafe-style chicken burger.',
            ],
            [
                'category' => 'Food & Drinks',
                'name' => 'Milk Coffee',
                'sku' => 'FOOD-COF-001',
                'price' => 180,
                'description' => 'Fresh hot milk coffee.',
            ],
            [
                'category' => 'Music',
                'name' => 'Acoustic Guitar',
                'sku' => 'MUS-GTR-001',
                'price' => 18500,
                'description' => 'Starter acoustic guitar.',
            ],
        ])->mapWithKeys(fn (array $product): array => [
            $product['sku'] => Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'category_id' => $categories[$product['category']]->id,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'description' => $product['description'],
                    'status' => 'active',
                ],
            ),
        ]);

        $customers = collect([
            [
                'name' => 'Aarav Sharma',
                'phone' => '9801111111',
                'email' => 'aarav@example.test',
                'address' => 'Lalitpur',
            ],
            [
                'name' => 'Sita Gurung',
                'phone' => '9802222222',
                'email' => 'sita@example.test',
                'address' => 'Bhaktapur',
            ],
            [
                'name' => 'Nabin Thapa',
                'phone' => '9803333333',
                'email' => null,
                'address' => 'Kathmandu',
            ],
        ])->mapWithKeys(fn (array $customer): array => [
            $customer['phone'] => Customer::query()->updateOrCreate(
                ['phone' => $customer['phone']],
                [
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'address' => $customer['address'],
                    'notes' => null,
                    'status' => 'active',
                ],
            ),
        ]);

        if (Sale::query()->exists()) {
            return;
        }

        $saleService = app(SaleService::class);

        $saleService->create([
            'customer_id' => $customers['9801111111']->id,
            'discount_amount' => 1000,
            'tax_rate' => 13,
            'notes' => 'Includes basic setup support.',
            'sold_at' => CarbonImmutable::now()->subDays(2)->setTime(10, 30),
            'items' => [
                [
                    'product_id' => $products['ELEC-LAP-001']->id,
                    'quantity' => 1,
                    'discount_amount' => 0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 94920,
                ],
            ],
        ], $admin->id);

        $saleService->create([
            'customer_id' => $customers['9802222222']->id,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'notes' => 'Cafe counter order.',
            'sold_at' => CarbonImmutable::now()->subDay()->setTime(14, 15),
            'items' => [
                [
                    'product_id' => $products['FOOD-BUR-001']->id,
                    'quantity' => 2,
                    'discount_amount' => 0,
                ],
                [
                    'product_id' => $products['FOOD-COF-001']->id,
                    'quantity' => 2,
                    'discount_amount' => 0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'online',
                    'amount' => 1060,
                    'provider' => 'Fonepay',
                    'transaction_reference' => 'FP-DEMO-001',
                ],
            ],
        ], $admin->id);

        $saleService->create([
            'customer_id' => null,
            'discount_amount' => 100,
            'tax_rate' => 0,
            'notes' => 'Walk-in clothing sale.',
            'sold_at' => CarbonImmutable::now()->setTime(11, 45),
            'items' => [
                [
                    'product_id' => $products['CLTH-TSH-001']->id,
                    'quantity' => 3,
                    'discount_amount' => 0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 2750,
                ],
            ],
        ], $admin->id);

        $saleService->create([
            'customer_id' => $customers['9803333333']->id,
            'discount_amount' => 500,
            'tax_rate' => 0,
            'notes' => 'Guitar sale with complimentary picks.',
            'sold_at' => CarbonImmutable::now()->setTime(16, 20),
            'items' => [
                [
                    'product_id' => $products['MUS-GTR-001']->id,
                    'quantity' => 1,
                    'discount_amount' => 0,
                ],
                [
                    'item_name' => 'Complimentary Guitar Picks',
                    'quantity' => 1,
                    'unit_price' => 0,
                    'discount_amount' => 0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'online',
                    'amount' => 18000,
                    'provider' => 'Khalti',
                    'transaction_reference' => 'KHALTI-DEMO-001',
                ],
            ],
        ], $admin->id);
    }
}
