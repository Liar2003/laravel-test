<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Device;
use App\Models\Content;
use App\Models\ContentView;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Main endpoint to get all dashboard overview statistics.
     */
    public function overview(Request $request)
    {
        // Validate the time_range input
        $validated = $request->validate([
            'time_range' => 'sometimes|in:day,week,month,year',
        ]);

        $timeRange = $validated['time_range'] ?? 'month';

        return response()->json([
            'users' => $this->getUserStats($timeRange),
            'devices' => $this->getDeviceStats($timeRange),
            'content' => $this->getContentStats($timeRange),
            'subscriptions' => $this->getSubscriptionStats($timeRange),
            'views' => $this->getViewStats($timeRange), // Added view stats to the main overview
            'meta' => [
                'time_range' => $timeRange,
                'last_updated' => now()->toDateTimeString(),
            ]
        ]);
    }

    /**
     * Get statistics for Users.
     */
    protected function getUserStats(string $timeRange)
    {
        return [
            'total' => User::count(),
            'change_percentage' => $this->getPercentageChange(User::class, $timeRange),
            'chart' => $this->getTimeSeries(User::class, $timeRange)
        ];
    }

    /**
     * Get statistics for Devices.
     * Includes the new Daily Active Devices count.
     */
    protected function getDeviceStats(string $timeRange)
    {
        // Assumes a 'last_active_time' column on the Device model.
        $dailyActive = Device::whereBetween('last_active_at', [now()->startOfDay(), now()->endOfDay()])->count();

        return [
            'total' => Device::count(),
            'vip_devices' => Device::where('is_vip', true)->count(),
            'daily_active' => $dailyActive, // New feature
            'change_percentage' => $this->getPercentageChange(Device::class, $timeRange),
            'chart' => $this->getTimeSeries(Device::class, $timeRange)
        ];
    }

    /**
     * Get statistics for Content.
     */
    protected function getContentStats(string $timeRange)
    {
        $popularContent = Content::withCount('views')
            ->orderByDesc('views_count')
            ->take(5)
            ->get(['id', 'title', 'views_count']);

        return [
            'total' => Content::count(),
            'vip_content' => Content::where('isvip', true)->count(),
            'popular_content' => $popularContent,
            'chart' => $this->getTimeSeries(Content::class, $timeRange),
        ];
    }

    /**
     * Get statistics for Subscriptions.
     */
    protected function getSubscriptionStats(string $timeRange)
    {
        return [
            'total' => Subscription::count(),
            'active' => Subscription::where('is_active', true)->count(),
            'chart' => $this->getTimeSeries(Subscription::class, $timeRange)
        ];
    }

    /**
     * Get statistics for Content Views.
     */
    protected function getViewStats(string $timeRange)
    {
        return [
            'total' => ContentView::count(),
            'vip_views' => ContentView::whereHas('content', fn($q) => $q->where('isvip', true))->count(),
            'chart' => $this->getDetailedViewTimeSeries($timeRange)
        ];
    }

    //--------------------------------------------------------------------------
    // HELPER METHODS
    //--------------------------------------------------------------------------

    /**
     * Calculates the percentage change in record count between the current and previous period.
     */
    protected function getPercentageChange(string $model, string $range): float
    {
        [$currentStart, $currentEnd] = $this->getDateRange($range, false);
        [$previousStart, $previousEnd] = $this->getDateRange($range, true);

        $currentCount = $model::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $previousCount = $model::whereBetween('created_at', [$previousStart, $previousEnd])->count();

        if ($previousCount === 0) {
            return $currentCount > 0 ? 100.0 : 0.0;
        }

        return round((($currentCount - $previousCount) / $previousCount) * 100, 2);
    }

    /**
     * Generic method to get time series data for a given model.
     */
    protected function getTimeSeries(string $model, string $range, string $dateColumn = 'created_at')
    {
        $dateExpression = $this->getDbDateExpression($dateColumn, $range);

        return $model::select(
            DB::raw("{$dateExpression} as date"),
            DB::raw('count(*) as count')
        )
            ->whereBetween($dateColumn, $this->getDateRange($range))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Gets detailed time series for views, including a breakdown of VIP vs non-VIP.
     * This remains a separate method due to its unique JOIN and conditional aggregation.
     */
    protected function getDetailedViewTimeSeries(string $range)
    {
        $dateExpression = $this->getDbDateExpression('content_views.created_at', $range);

        return ContentView::select(
            DB::raw("{$dateExpression} as date"),
            DB::raw('count(*) as total_views'),
            DB::raw('sum(CASE WHEN contents.isvip = true THEN 1 ELSE 0 END) as vip_views')
        )
            ->join('contents', 'content_views.content_id', '=', 'contents.id')
            ->whereBetween('content_views.created_at', $this->getDateRange($range))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * [FIXED] Calculates the start and end dates for a given time range.
     * This version uses explicit Carbon methods to avoid the UnitException.
     *
     * @param string $range The time range (day, week, month, year).
     * @param bool $previous If true, get the date range for the preceding period.
     * @return array [Carbon $start, Carbon $end]
     */
    protected function getDateRange(string $range, bool $previous = false): array
    {
        if ($previous) {
            // Calculate the start and end for the *previous* period.
            $end = match ($range) {
                'day'   => now()->subDay(),
                'week'  => now()->subWeek(),
                'year'  => now()->subYear(),
                default => now()->subMonth(),
            };
            $start = match ($range) {
                'day'   => now()->subDays(2),
                'week'  => now()->subWeeks(2),
                'year'  => now()->subYears(2),
                default => now()->subMonths(2),
            };
            return [$start, $end];
        }

        // Calculate the start and end for the *current* period.
        $end = now();
        $start = match ($range) {
            'day'   => now()->subDay(),
            'week'  => now()->subWeek(),
            'year'  => now()->subYear(),
            default => now()->subMonth(),
        };

        return [$start, $end];
    }

    /**
     * Creates a database-agnostic date formatting expression.
     */
    protected function getDbDateExpression(string $column, string $range): string
    {
        $format = $this->getDbDateFormat($range);
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "TO_CHAR({$column}::timestamp, '{$format}')",
            'mysql' => "DATE_FORMAT({$column}, '{$format}')",
            default => "strftime('{$format}', {$column})", // sqlite
        };
    }

    /**
     * Returns the correct date format string based on the database driver.
     */
    protected function getDbDateFormat(string $range): string
    {
        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        return match ($range) {
            'day'   => $isPostgres ? 'HH24:00' : '%H:00',
            'week'  => $isPostgres ? 'YYYY-MM-DD' : '%Y-%m-%d',
            'year'  => $isPostgres ? 'YYYY-MM' : '%Y-%m',
            default => $isPostgres ? 'YYYY-MM-DD' : '%Y-%m-%d', // month
        };
    }
}
