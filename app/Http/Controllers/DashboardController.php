<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $soon = $today->copy()->addDays(7);

        // Resolve the expense reporting range from the requested period.
        // Bills stay "as of now"; only expense aggregates use this range.
        [$rangeStart, $rangeEnd, $period] = $this->resolveRange($request, $today);
        $monthStart = $rangeStart->toDateString();
        $monthEnd = $rangeEnd->toDateString();

        // --- Bills ---
        $upcomingBills = Bill::with(['category', 'responsibleUser'])
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->limit(50)
            ->get()
            ->map(fn (Bill $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'amount' => $b->amount,
                'due_date' => $b->due_date?->toDateString(),
                'owner_label' => $b->owner_label,
                'responsible_user' => $b->responsibleUser?->name,
                'category' => $b->category?->name,
                'category_color' => $b->category?->color,
                'scope' => $b->scope,
                'effective_status' => $b->effectiveStatus(),
            ]);

        $overdueTotal = Bill::where('status', '!=', 'paid')
            ->whereDate('due_date', '<', $today)
            ->sum('amount');

        $dueSoonTotal = Bill::where('status', '!=', 'paid')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $soon)
            ->sum('amount');

        $pendingTotal = Bill::where('status', '!=', 'paid')->sum('amount');

        $paidThisMonth = Bill::where('status', 'paid')
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // --- Expenses ---
        $expensesThisMonth = Expense::whereBetween('spent_on', [$monthStart, $monthEnd])
            ->sum('amount');

        $apartmentThisMonth = Expense::where('scope', 'apartment')
            ->whereBetween('spent_on', [$monthStart, $monthEnd])
            ->sum('amount');

        $otherThisMonth = Expense::where('scope', 'other')
            ->whereBetween('spent_on', [$monthStart, $monthEnd])
            ->sum('amount');

        // All-time totals so the dashboard reflects expenses even when none
        // fall within the current month.
        $expensesTotal = Expense::sum('amount');
        $expensesCount = Expense::count();

        // Totals by category (this month) for the chart.
        $byCategory = Expense::query()
            ->whereBetween('spent_on', [$monthStart, $monthEnd])
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category:id,name,color')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category?->name ?? 'Sem categoria',
                'color' => $row->category?->color ?? '#94a3b8',
                'total' => (float) $row->total,
            ])
            ->sortByDesc('total')
            ->values();

        // Last 6 months of expenses for a trend line.
        $trend = Expense::query()
            ->whereDate('spent_on', '>=', $today->copy()->subMonths(5)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(spent_on, '%Y-%m') as ym"),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->ym,
                'total' => (float) $row->total,
            ]);

        return response()->json([
            'bills' => [
                'upcoming' => $upcomingBills,
                'overdue_total' => (float) $overdueTotal,
                'due_soon_total' => (float) $dueSoonTotal,
                'pending_total' => (float) $pendingTotal,
                'paid_this_month' => (float) $paidThisMonth,
                'overdue_count' => Bill::where('status', '!=', 'paid')->whereDate('due_date', '<', $today)->count(),
                'pending_count' => Bill::where('status', '!=', 'paid')->count(),
            ],
            'expenses' => [
                'range_total' => (float) $expensesThisMonth,
                'range_apartment' => (float) $apartmentThisMonth,
                'range_other' => (float) $otherThisMonth,
                'total' => (float) $expensesTotal,
                'count' => $expensesCount,
                'by_category' => $byCategory,
                'trend' => $trend,
            ],
            'period' => [
                'key' => $period,
                'from' => $monthStart,
                'to' => $monthEnd,
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Resolve the expense reporting range from the request.
     *
     * Accepts either a named "period" (this_month, last_month, last_3_months,
     * last_6_months, this_year, all) or explicit from/to dates which take
     * precedence. Falls back to the current month.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveRange(Request $request, Carbon $today): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if ($from && $to) {
            try {
                $start = Carbon::parse($from)->startOfDay();
                $end = Carbon::parse($to)->endOfDay();
                if ($start->lte($end)) {
                    return [$start, $end, 'custom'];
                }
            } catch (\Throwable) {
                // Fall through to the named period / default.
            }
        }

        $period = (string) $request->query('period', 'this_month');

        return match ($period) {
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
                $period,
            ],
            'last_3_months' => [
                $today->copy()->subMonthsNoOverflow(2)->startOfMonth(),
                $today->copy()->endOfMonth(),
                $period,
            ],
            'last_6_months' => [
                $today->copy()->subMonthsNoOverflow(5)->startOfMonth(),
                $today->copy()->endOfMonth(),
                $period,
            ],
            'this_year' => [
                $today->copy()->startOfYear(),
                $today->copy()->endOfYear(),
                $period,
            ],
            'all' => [
                Carbon::createFromDate(2000, 1, 1)->startOfDay(),
                $today->copy()->endOfYear(),
                $period,
            ],
            default => [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth(),
                'this_month',
            ],
        };
    }
}
