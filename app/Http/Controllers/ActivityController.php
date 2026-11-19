<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $base = StockMovement::query()
            ->when($filters['user'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        $activities = (clone $base)
            ->with(['product:id,name,unit', 'user:id,name'])
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (StockMovement $m) => [
                'id' => $m->id,
                'time' => $m->created_at->format('d/m/Y H:i'),
                'user' => $m->user?->name ?? 'Sistem',
                'type' => $m->type->label(),
                'product' => $m->product?->name,
                'unit' => $m->product?->unit,
                'change' => $m->quantity_change,
                'notes' => $m->notes,
            ]);

        $summary = (clone $base)
            ->with('user:id,name')
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'user' => $row->user?->name ?? 'Sistem',
                'total' => (int) $row->total,
            ]);

        return Inertia::render('Activities/Index', [
            'activities' => $activities,
            'summary' => $summary,
            'users' => User::where('store_id', $request->user()->store_id)
                ->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'user' => $request->input('user', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
            ],
        ]);
    }
}
