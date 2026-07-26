<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function salesSummary(?string $from = null, ?string $to = null): array
    {
        $query = Sale::query()->where('status', 'completed');
        $this->applyDateRange($query, $from, $to);

        return [
            'invoice_count' => (clone $query)->count(),
            'subtotal' => (float) (clone $query)->sum('subtotal'),
            'discount_total' => (float) (clone $query)->sum('discount_amount'),
            'tax_total' => (float) (clone $query)->sum('tax_amount'),
            'revenue' => (float) (clone $query)->sum('grand_total'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function paymentSummary(?string $from = null, ?string $to = null): array
    {
        $query = Payment::query()
            ->select('method', DB::raw('COUNT(*) as payment_count'), DB::raw('SUM(amount) as total'))
            ->whereHas('sale', fn ($saleQuery) => $saleQuery->where('status', 'completed'))
            ->groupBy('method');

        if ($from || $to) {
            $query->whereHas('sale', fn ($saleQuery) => $this->applyDateRange($saleQuery, $from, $to));
        }

        return $query->get()->map(fn (Payment $payment): array => [
            'method' => $payment->method,
            'payment_count' => (int) $payment->payment_count,
            'total' => (float) $payment->total,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function topProducts(?string $from = null, ?string $to = null, int $limit = 10): array
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->select(
                'sale_items.item_name',
                DB::raw('SUM(sale_items.quantity) as quantity_sold'),
                DB::raw('SUM(sale_items.line_total) as revenue'),
            )
            ->groupBy('sale_items.item_name')
            ->orderByDesc('quantity_sold')
            ->limit($limit);

        if ($from) {
            $query->where('sales.sold_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where('sales.sold_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return $query->get()->map(fn (object $row): array => [
            'item_name' => $row->item_name,
            'quantity_sold' => (float) $row->quantity_sold,
            'revenue' => (float) $row->revenue,
        ])->all();
    }

    private function applyDateRange($query, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->where('sold_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where('sold_at', '<=', Carbon::parse($to)->endOfDay());
        }
    }
}
