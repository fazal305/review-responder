<?php

namespace App\Http\Controllers\Api;

use App\Enums\DraftStatus;
use App\Http\Controllers\Controller;
use App\Models\ApiUsageLog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'queued_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
        ]);
    }

    /** Aggregates only: no user ids, emails or review text leave this endpoint. */
    public function usage(): JsonResponse
    {
        $since = now()->subDays(7);

        $services = ApiUsageLog::where('created_at', '>=', $since)
            ->selectRaw('service, action, COUNT(*) AS calls, SUM(CASE WHEN ok THEN 0 ELSE 1 END) AS failures, AVG(duration_ms) AS avg_duration_ms')
            ->groupBy('service', 'action')
            ->orderBy('service')->orderBy('action')
            ->get()
            ->map(fn ($row) => [
                'service' => $row->service,
                'action' => $row->action,
                'calls' => (int) $row->calls,
                'failures' => (int) $row->failures,
                'avg_duration_ms' => (int) round((float) $row->avg_duration_ms),
            ]);

        $recentFailures = ApiUsageLog::where('ok', false)->latest('created_at')->latest('id')->limit(20)
            ->get(['service', 'action', 'created_at'])
            ->map(fn ($log) => [
                'service' => $log->service,
                'action' => $log->action,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        $drafts = DB::table('drafts')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'window_days' => 7,
            'services' => $services,
            'recent_failures' => $recentFailures,
            'queued_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'totals' => [
                'users' => User::where('is_demo', false)->count(),
                'demo_users' => User::where('is_demo', true)->count(),
                'locations' => Location::count(),
                'monitored_locations' => Location::where('monitoring', true)->count(),
                'drafts' => collect(DraftStatus::cases())->mapWithKeys(fn (DraftStatus $s) => [$s->value => (int) ($drafts[$s->value] ?? 0)]),
            ],
        ]);
    }
}
