<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Category;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    /** Batas baris di layar; ekspor selalu memuat semua baris. */
    private const SCREEN_ROWS = 100;

    public function index(): Response
    {
        $reports = collect(ReportService::TYPES)
            ->map(fn (array $meta, string $slug) => [
                'slug' => $slug,
                'label' => $meta['label'],
                'description' => $meta['description'],
            ])
            ->values();

        return Inertia::render('Reports/Index', ['reports' => $reports]);
    }

    public function show(Request $request, ReportService $reports, string $type): Response
    {
        [$report, $filters, $meta] = $this->build($request, $reports, $type);

        $total = count($report['rows']);
        $report['rows'] = array_slice($report['rows'], 0, self::SCREEN_ROWS);

        return Inertia::render('Reports/Show', [
            'report' => $report,
            'rowsTotal' => $total,
            'screenRows' => self::SCREEN_ROWS,
            'filters' => [
                'from' => $filters['from']->toDateString(),
                'to' => $filters['to']->toDateString(),
                'category' => $filters['category'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'meta' => $meta + ['slug' => $type],
        ]);
    }

    public function pdf(Request $request, ReportService $reports, string $type): SymfonyResponse
    {
        [$report, $filters, $meta] = $this->build($request, $reports, $type);

        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'storeName' => $request->user()->store?->name,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape')
            ->download($this->filename($type, $filters, $meta['period'], 'pdf'));
    }

    public function excel(Request $request, ReportService $reports, string $type): SymfonyResponse
    {
        [$report, $filters, $meta] = $this->build($request, $reports, $type);

        $export = new ReportExport(
            $report,
            $request->user()->store?->name,
            now()->format('d/m/Y H:i'),
        );

        return Excel::download($export, $this->filename($type, $filters, $meta['period'], 'xlsx'));
    }

    /** @return array{0: array, 1: array, 2: array} */
    private function build(Request $request, ReportService $reports, string $type): array
    {
        $meta = ReportService::TYPES[$type];

        $input = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'category' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('store_id', $request->user()->store_id),
            ],
            'status' => ['nullable', Rule::in(['aman', 'menipis', 'habis'])],
        ]);

        $filters = $reports->resolveFilters($input, $meta['period']);
        $report = $reports->generate($type, $request->user()->store_id, $filters);

        return [$report, $filters, $meta];
    }

    private function filename(string $type, array $filters, bool $usesPeriod, string $extension): string
    {
        $suffix = $usesPeriod
            ? $filters['from']->format('Ymd').'-'.$filters['to']->format('Ymd')
            : now()->format('Ymd');

        return "laporan-{$type}-{$suffix}.{$extension}";
    }
}
