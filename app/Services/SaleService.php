<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomFieldValue;
use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly InvoiceNumberService $invoiceNumberService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $userId = null): Sale
    {
        return DB::transaction(function () use ($data, $userId): Sale {
            $soldAt = isset($data['sold_at']) ? Carbon::parse($data['sold_at']) : now();
            $customerId = $this->resolveCustomerId($data);
            $items = $this->normalizeItems($data['items']);
            $totals = $this->calculateTotals($items, $data);
            $payments = $this->normalizePayments($data['payments'], $totals['grand_total']);

            $sale = Sale::query()->create([
                'invoice_number' => $this->invoiceNumberService->next($soldAt),
                'customer_id' => $customerId,
                'user_id' => $userId,
                'status' => 'completed',
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'grand_total' => $totals['grand_total'],
                'notes' => $data['notes'] ?? null,
                'additional_details' => $data['additional_details'] ?? null,
                'sold_at' => $soldAt,
            ]);

            foreach ($items as $item) {
                $saleItem = $sale->items()->create(Arr::except($item, ['custom_values']));

                foreach ($item['custom_values'] ?? [] as $customValue) {
                    CustomFieldValue::query()->create([
                        'custom_field_id' => $customValue['custom_field_id'],
                        'sale_item_id' => $saleItem->id,
                        'value' => $customValue['value'] ?? null,
                    ]);
                }
            }

            foreach ($payments as $payment) {
                $sale->payments()->create($payment);
            }

            foreach ($data['custom_values'] ?? [] as $customValue) {
                CustomFieldValue::query()->create([
                    'custom_field_id' => $customValue['custom_field_id'],
                    'sale_id' => $sale->id,
                    'value' => $customValue['value'] ?? null,
                ]);
            }

            return $sale->load([
                'customer',
                'user',
                'items.product.category',
                'items.customFieldValues.customField',
                'payments',
                'customFieldValues.customField',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomerId(array $data): ?int
    {
        if (! empty($data['customer_id'])) {
            $customer = Customer::query()
                ->where('status', 'active')
                ->find($data['customer_id']);

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Selected customer is inactive or does not exist.',
                ]);
            }

            return $customer->id;
        }

        if (empty($data['customer'])) {
            return null;
        }

        $customer = Customer::query()->create($data['customer']);

        return $customer->id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items): array
    {
        return collect($items)->map(function (array $item): array {
            $product = null;

            if (! empty($item['product_id'])) {
                $product = Product::query()
                    ->where('status', 'active')
                    ->find($item['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Selected product is inactive or does not exist.',
                    ]);
                }
            }

            $quantity = (float) $item['quantity'];
            if (! $product && ! array_key_exists('unit_price', $item)) {
                throw ValidationException::withMessages([
                    'items' => 'Unit price is required for custom items.',
                ]);
            }

            $unitPrice = $product
                ? (float) $product->price
                : (float) $item['unit_price'];
            $discount = (float) ($item['discount_amount'] ?? 0);
            $lineSubtotal = $quantity * $unitPrice;

            if ($discount > $lineSubtotal) {
                throw ValidationException::withMessages([
                    'items' => 'Item discount cannot be greater than the item subtotal.',
                ]);
            }

            return [
                'product_id' => $product?->id,
                'item_name' => $product?->name ?? trim((string) $item['item_name']),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'line_total' => round($lineSubtotal - $discount, 2),
                'notes' => $item['notes'] ?? null,
                'additional_details' => $item['additional_details'] ?? null,
                'custom_values' => $item['custom_values'] ?? [],
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $data
     * @return array<string, float>
     */
    private function calculateTotals(array $items, array $data): array
    {
        $subtotal = round((float) collect($items)->sum('line_total'), 2);
        $discountAmount = round((float) ($data['discount_amount'] ?? 0), 2);

        if ($discountAmount > $subtotal) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Sale discount cannot be greater than the subtotal.',
            ]);
        }

        $taxRate = round((float) ($data['tax_rate'] ?? 0), 2);
        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = round($taxableAmount * ($taxRate / 100), 2);
        $grandTotal = round($taxableAmount + $taxAmount, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function normalizePayments(array $payments, float $grandTotal): array
    {
        $paymentTotal = round((float) collect($payments)->sum('amount'), 2);

        if ($paymentTotal !== round($grandTotal, 2)) {
            throw ValidationException::withMessages([
                'payments' => 'Payment total must match the sale grand total.',
            ]);
        }

        return collect($payments)->map(fn (array $payment): array => [
            'method' => $payment['method'],
            'amount' => round((float) $payment['amount'], 2),
            'provider' => $payment['provider'] ?? null,
            'transaction_reference' => $payment['transaction_reference'] ?? null,
            'notes' => $payment['notes'] ?? null,
        ])->all();
    }
}
