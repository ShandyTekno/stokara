<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\SaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'payment' => ['nullable', Rule::enum(PaymentMethod::class)],
        ]);

        $search = $filters['search'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $payment = $filters['payment'] ?? null;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $base = Sale::query()
            ->when($search, fn ($q, $s) => $q->where('invoice_number', 'like', "%{$s}%"))
            ->when($from, fn ($q, $d) => $q->whereDate('sold_at', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('sold_at', '<=', $d))
            ->when($payment, fn ($q, $m) => $q->where('payment_method', $m));

        $sales = (clone $base)
            ->with('user:id,name')
            ->withCount('details')
            ->withSum('details', 'quantity')
            ->latest('sold_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Sale $s) => [
                'id' => $s->id,
                'invoice_number' => $s->invoice_number,
                'sold_at' => $s->sold_at->format('d/m/Y H:i'),
                'items' => $s->details_count,
                'total_units' => (int) $s->details_sum_quantity,
                'total_amount' => (float) $s->total_amount,
                'payment' => $s->payment_method->label(),
                'payment_value' => $s->payment_method->value,
                'user' => $s->user?->name,
            ]);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'summary' => [
                'count' => (clone $base)->count(),
                'total_amount' => (float) (clone $base)->sum('total_amount'),
                'cash_total' => (float) (clone $base)->where('payment_method', PaymentMethod::Tunai->value)->sum('total_amount'),
                'qris_total' => (float) (clone $base)->where('payment_method', PaymentMethod::Qris->value)->sum('total_amount'),
            ],
            'filters' => [
                'search' => $request->input('search', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
                'payment' => $request->input('payment', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sales/Create', [
            'products' => Product::orderBy('name')
                ->get(['id', 'code', 'name', 'unit', 'stock', 'selling_price'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'unit' => $p->unit,
                    'stock' => $p->stock,
                    'selling_price' => (float) $p->selling_price,
                ]),
        ]);
    }

    public function store(SaleRequest $request, StockService $stock): RedirectResponse
    {
        $sale = $stock->recordSale($request->validated(), $request->user());

        $message = "Penjualan {$sale->invoice_number} berhasil dicatat.";
        if ($sale->payment_method === PaymentMethod::Tunai && (float) $sale->change_amount > 0) {
            $message .= ' Kembalian Rp '.number_format((float) $sale->change_amount, 0, ',', '.').'.';
        }

        return redirect()->route('sales.show', $sale)->with('success', $message);
    }

    public function show(Request $request, Sale $sale): Response
    {
        $sale->load(['user:id,name', 'details.product']);

        return Inertia::render('Sales/Show', [
            'store' => ['name' => $request->user()->store?->name],
            'sale' => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'sold_at' => $sale->sold_at->format('d/m/Y H:i'),
                'cashier' => $sale->user?->name,
                'payment' => $sale->payment_method->label(),
                'payment_value' => $sale->payment_method->value,
                'total_amount' => (float) $sale->total_amount,
                'amount_paid' => (float) $sale->amount_paid,
                'change_amount' => (float) $sale->change_amount,
                'notes' => $sale->notes,
                'details' => $sale->details->map(fn ($d) => [
                    'id' => $d->id,
                    'product_id' => $d->product_id,
                    'product' => $d->product?->name,
                    'code' => $d->product?->code,
                    'unit' => $d->product?->unit,
                    'product_deleted' => (bool) $d->product?->trashed(),
                    'quantity' => (int) $d->quantity,
                    'unit_price' => (float) $d->unit_price,
                    'subtotal' => (float) $d->subtotal,
                ])->values(),
            ],
        ]);
    }
}
