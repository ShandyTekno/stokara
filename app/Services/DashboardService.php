<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Carbon\Carbon;

class DashboardService
{
    public function data(): array
    {
        return [
            'stats' => $this->stats(),
            'salesChart' => $this->salesChart(),
            'movementChart' => $this->movementChart(),
            'lowStock' => $this->lowStock(),
            'activities' => $this->activities(),
        ];
    }

    private function stats(): array
    {
        $today = today()->toDateString();

        return [
            'total_products' => Product::count(),
            'total_stock' => (int) Product::sum('stock'),
            'low_stock_count' => Product::lowStock()->count(),
            'out_of_stock_count' => Product::outOfStock()->count(),
            'today_transactions' => Sale::whereDate('sold_at', $today)->count(),
            'today_sales' => (float) Sale::whereDate('sold_at', $today)->sum('total_amount'),
        ];
    }

    /** @return array<int, Carbon> 7 hari terakhir, dari yang terlama */
    private function lastDays(int $count = 7): array
    {
        return collect(range($count - 1, 0))
            ->map(fn (int $i) => today()->subDays($i))
            ->all();
    }

    private function salesChart(): array
    {
        $days = $this->lastDays();

        $totals = Sale::where('sold_at', '>=', $days[0]->copy()->startOfDay())
            ->selectRaw('DATE(sold_at) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'labels' => array_map(fn (Carbon $d) => $d->format('d/m'), $days),
            'values' => array_map(fn (Carbon $d) => (float) ($totals[$d->toDateString()] ?? 0), $days),
        ];
    }

    private function movementChart(): array
    {
        $days = $this->lastDays();

        $rows = StockMovement::where('created_at', '>=', $days[0]->copy()->startOfDay())
            ->selectRaw("DATE(created_at) as day,
                SUM(CASE WHEN quantity_change > 0 AND type <> 'initial_stock' THEN quantity_change ELSE 0 END) as masuk,
                SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END) as keluar")
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return [
            'labels' => array_map(fn (Carbon $d) => $d->format('d/m'), $days),
            'masuk' => array_map(fn (Carbon $d) => (int) ($rows[$d->toDateString()]->masuk ?? 0), $days),
            'keluar' => array_map(fn (Carbon $d) => (int) ($rows[$d->toDateString()]->keluar ?? 0), $days),
        ];
    }

    private function lowStock(): array
    {
        return Product::query()
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'stock' => $p->stock,
                'min_stock' => $p->min_stock,
                'unit' => $p->unit,
                'status' => $p->stock_status->value,
            ])
            ->all();
    }

    private function activities(): array
    {
        return StockMovement::with(['product:id,name,unit', 'user:id,name'])
            ->latest()
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'type' => $m->type->label(),
                'product' => $m->product?->name,
                'unit' => $m->product?->unit,
                'change' => $m->quantity_change,
                'user' => $m->user?->name,
                'time' => $m->created_at->diffForHumans(),
            ])
            ->all();
    }
}
