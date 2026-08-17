<?php

namespace Database\Seeders;

use App\Models\Business;
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
        $this->seedBusiness([
            'business' => [
                'name' => 'Test Store',
                'slug' => 'test-store',
                'logo' => null,
                'email' => 'store@test.com',
                'phone' => '9840030271',
                'address' => 'Kathmandu, Nepal',
                'plan' => 'starter',
            ],
            'settings' => [
                'tax_enabled' => true,
                'default_tax_rate' => 13,
                'online_payment_enabled' => true,
            ],
            'admin' => [
                'name' => 'Admin User',
                'email' => 'admin@test.com',
                'phone' => null,
            ],
            'staff' => [
                'name' => 'Staff User',
                'email' => 'staff@test.com',
                'phone' => null,
            ],
            'categories' => [
                ['name' => 'Electronics', 'description' => 'Laptops, keyboards, mice, and accessories.'],
                ['name' => 'Clothing', 'description' => 'Simple apparel items for retail demo data.'],
                ['name' => 'Food & Drinks', 'description' => 'Cafe and restaurant style items.'],
                ['name' => 'Music', 'description' => 'Guitars and music accessories.'],
            ],
            'products' => [
                ['category' => 'Electronics', 'name' => 'Dell Inspiron 15', 'sku' => 'ELEC-LAP-001', 'price' => 85000, 'description' => 'Everyday laptop for students and small offices.'],
                ['category' => 'Electronics', 'name' => 'Logitech Wireless Mouse', 'sku' => 'ELEC-MOU-001', 'price' => 1200, 'description' => 'Compact wireless mouse.'],
                ['category' => 'Clothing', 'name' => 'Cotton T-Shirt', 'sku' => 'CLTH-TSH-001', 'price' => 950, 'description' => 'Plain cotton t-shirt.'],
                ['category' => 'Food & Drinks', 'name' => 'Chicken Burger', 'sku' => 'FOOD-BUR-001', 'price' => 350, 'description' => 'Cafe-style chicken burger.'],
                ['category' => 'Food & Drinks', 'name' => 'Milk Coffee', 'sku' => 'FOOD-COF-001', 'price' => 180, 'description' => 'Fresh hot milk coffee.'],
                ['category' => 'Music', 'name' => 'Acoustic Guitar', 'sku' => 'MUS-GTR-001', 'price' => 18500, 'description' => 'Starter acoustic guitar.'],
            ],
            'customers' => [
                ['name' => 'Aarav Sharma', 'phone' => '9823142358', 'email' => 'aarav@example.test', 'address' => 'Lalitpur'],
                ['name' => 'Sita Gurung', 'phone' => '9842365214', 'email' => 'sita@example.test', 'address' => 'Bhaktapur'],
                ['name' => 'Nabin Thapa', 'phone' => '9832562368', 'email' => null, 'address' => 'Kathmandu'],
            ],
            'sales' => [
                [
                    'customer_phone' => '9801111111',
                    'discount_amount' => 1000,
                    'tax_rate' => 13,
                    'notes' => 'Includes basic setup support.',
                    'sold_at' => CarbonImmutable::now()->subDays(2)->setTime(10, 30),
                    'items' => [['sku' => 'ELEC-LAP-001', 'quantity' => 1, 'discount_amount' => 0]],
                    'payments' => [['method' => 'cash', 'amount' => 94920]],
                ],
                [
                    'customer_phone' => '9802222222',
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                    'notes' => 'Cafe counter order.',
                    'sold_at' => CarbonImmutable::now()->subDay()->setTime(14, 15),
                    'items' => [
                        ['sku' => 'FOOD-BUR-001', 'quantity' => 2, 'discount_amount' => 0],
                        ['sku' => 'FOOD-COF-001', 'quantity' => 2, 'discount_amount' => 0],
                    ],
                    'payments' => [['method' => 'online', 'amount' => 1060, 'provider' => 'Fonepay', 'transaction_reference' => 'FP-DEMO-001']],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedBusiness(array $data): void
    {
        $business = Business::query()->updateOrCreate(
            ['slug' => $data['business']['slug']],
            [
                ...$data['business'],
                'status' => 'active',
            ],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => $data['admin']['email']],
            [
                'business_id' => $business->id,
                'name' => $data['admin']['name'],
                'phone' => $data['admin']['phone'],
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => $data['staff']['email']],
            [
                'business_id' => $business->id,
                'name' => $data['staff']['name'],
                'phone' => $data['staff']['phone'],
                'password' => Hash::make('password'),
                'role' => 'staff',
                'status' => 'active',
            ],
        );

        BusinessSetting::query()->updateOrCreate(
            ['business_id' => $business->id],
            $data['settings'],
        );

        $categories = collect($data['categories'])->mapWithKeys(fn(array $category): array => [
            $category['name'] => Category::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'name' => $category['name'],
                ],
                [
                    'description' => $category['description'],
                    'status' => 'active',
                ],
            ),
        ]);

        $products = collect($data['products'])->mapWithKeys(fn(array $product): array => [
            $product['sku'] => Product::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'sku' => $product['sku'],
                ],
                [
                    'category_id' => $categories[$product['category']]->id,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'description' => $product['description'],
                    'status' => 'active',
                ],
            ),
        ]);

        $customers = collect($data['customers'])->mapWithKeys(fn(array $customer): array => [
            $customer['phone'] => Customer::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'phone' => $customer['phone'],
                ],
                [
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'address' => $customer['address'],
                    'notes' => null,
                    'status' => 'active',
                ],
            ),
        ]);

        if (Sale::query()->where('business_id', $business->id)->exists()) {
            return;
        }

        $saleService = app(SaleService::class);

        foreach ($data['sales'] as $sale) {
            $saleService->create([
                'customer_id' => $sale['customer_phone'] && $customers->has($sale['customer_phone'])
                    ? $customers->get($sale['customer_phone'])->id
                    : null,
                'discount_amount' => $sale['discount_amount'],
                'tax_rate' => $sale['tax_rate'],
                'notes' => $sale['notes'],
                'sold_at' => $sale['sold_at'],
                'items' => collect($sale['items'])->map(fn(array $item): array => [
                    'product_id' => $products[$item['sku']]->id,
                    'quantity' => $item['quantity'],
                    'discount_amount' => $item['discount_amount'],
                ])->all(),
                'payments' => $sale['payments'],
            ], $business->id, $admin->id);
        }
    }
}
