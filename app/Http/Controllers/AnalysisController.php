<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Category;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AnalysisExplainer;
use App\Services\StockAnalysisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class AnalysisController extends Controller
{
    /** Batas baris per daftar di layar; ekspor selalu memuat semua baris. */
    private const SCREEN_ROWS = 50;

    public function index(Request $request, StockAnalysisService $analysis, AiAssistantService $ai): Response
    {
        $params = $this->params($request);
        $result = $analysis->analyze($request->user()->store_id, $params['period'], $params['cover'], $params['category']);

        $limited = fn (array $rows) => ['rows' => array_slice($rows, 0, self::SCREEN_ROWS), 'total' => count($rows)];
        $live = $ai->isLive();

        return Inertia::render('Analysis/Index', [
            'mode' => $live ? 'live' : 'demo',
            'model' => $live ? config('services.gemini.model') : null,
            'filters' => [
                'period' => $params['period'],
                'cover' => $params['cover'],
                'category' => $params['category'] ?? '',
            ],
            'options' => [
                'periods' => StockAnalysisService::PERIODS,
                'covers' => StockAnalysisService::COVERS,
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'info' => $result['data'],
            'notes' => $result['notes'],
            'summary' => $result['summary'],
            'formulas' => $result['formulas'],
            'definitions' => $result['definitions'],
            'reorder' => $limited($result['reorder']),
            'risk' => $limited($result['risk']),
            'slow' => $limited($result['slow']),
            'screenRows' => self::SCREEN_ROWS,
            'explanation' => session('analysis_explanation'),
        ]);
    }

    public function explain(
        Request $request,
        StockAnalysisService $analysis,
        AnalysisExplainer $explainer,
        AiAssistantService $ai,
    ): RedirectResponse {
        $params = $this->params($request);
        $user = $request->user();

        $limiterKey = 'ai-analysis:'.$user->id;
        $perMinute = (int) config('services.gemini.rate_per_minute', 10);
        if ($perMinute > 0 && RateLimiter::tooManyAttempts($limiterKey, $perMinute)) {
            $wait = RateLimiter::availableIn($limiterKey);

            return back()->withErrors(['explain' => "Terlalu banyak permintaan. Coba lagi dalam {$wait} detik."]);
        }

        // Batas harian hanya untuk mode sungguhan (yang menimbulkan biaya).
        $dailyKey = 'ai-analysis-daily:'.$user->store_id;
        $daily = (int) config('services.gemini.analysis_daily_limit', 20);
        if ($ai->isLive() && $daily > 0 && RateLimiter::tooManyAttempts($dailyKey, $daily)) {
            return back()->withErrors(['explain' => 'Batas harian penjelasan AI untuk toko ini sudah tercapai. Coba lagi besok.']);
        }

        RateLimiter::hit($limiterKey, 60);

        // Analisis selalu dihitung ulang di server; angka dari browser tidak dipercaya.
        $result = $analysis->analyze($user->store_id, $params['period'], $params['cover'], $params['category']);

        try {
            $reply = $explainer->explain($result, (string) $user->store?->name);
        } catch (AiProviderException $e) {
            return back()->withErrors(['explain' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['explain' => 'Terjadi kesalahan saat menyusun penjelasan. Coba lagi.']);
        }

        if ($ai->isLive()) {
            RateLimiter::hit($dailyKey, 86400);
        }

        return back()->with('analysis_explanation', [
            'text' => $reply->text,
            'is_simulation' => $reply->isSimulation,
            'model' => $reply->meta['model'] ?? null,
            'generated_at' => now()->format('d/m/Y H:i'),
        ]);
    }

    public function pdf(Request $request, StockAnalysisService $analysis): SymfonyResponse
    {
        $report = $this->procurementReport($request, $analysis);

        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'storeName' => $request->user()->store?->name,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape')->download($this->filename('pdf'));
    }

    public function excel(Request $request, StockAnalysisService $analysis): SymfonyResponse
    {
        $report = $this->procurementReport($request, $analysis);

        return Excel::download(
            new ReportExport($report, $request->user()->store?->name, now()->format('d/m/Y H:i')),
            $this->filename('xlsx'),
        );
    }

    /** @return array<string, mixed> */
    private function procurementReport(Request $request, StockAnalysisService $analysis): array
    {
        $params = $this->params($request);
        $result = $analysis->analyze($request->user()->store_id, $params['period'], $params['cover'], $params['category']);

        return $analysis->procurementReport($result);
    }

    /** @return array{period: int, cover: int, category: ?int} */
    private function params(Request $request): array
    {
        $input = $request->validate([
            'period' => ['nullable', Rule::in(StockAnalysisService::PERIODS)],
            'cover' => ['nullable', Rule::in(StockAnalysisService::COVERS)],
            'category' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('store_id', $request->user()->store_id),
            ],
        ], [
            'in' => ':attribute tidak valid.',
            'integer' => ':attribute tidak valid.',
            'exists' => ':attribute tidak valid.',
        ], [
            'period' => 'Periode analisis',
            'cover' => 'Cakupan stok',
            'category' => 'Kategori',
        ]);

        return [
            'period' => (int) ($input['period'] ?? StockAnalysisService::DEFAULT_PERIOD),
            'cover' => (int) ($input['cover'] ?? StockAnalysisService::DEFAULT_COVER),
            'category' => isset($input['category']) ? (int) $input['category'] : null,
        ];
    }

    private function filename(string $extension): string
    {
        return 'analisis-pengadaan-'.now()->format('Ymd').'.'.$extension;
    }
}
