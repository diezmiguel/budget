<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $soon = $today->copy()->addDays(7);

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
        $expensesThisMonth = Expense::whereBetween('spent_on', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $apartmentThisMonth = Expense::where('scope', 'apartment')
            ->whereBetween('spent_on', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $otherThisMonth = Expense::where('scope', 'other')
            ->whereBetween('spent_on', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Totals by category (this month) for the chart.
        $byCategory = Expense::query()
            ->whereBetween('spent_on', [$startOfMonth, $endOfMonth])
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
                'this_month' => (float) $expensesThisMonth,
                'apartment_this_month' => (float) $apartmentThisMonth,
                'other_this_month' => (float) $otherThisMonth,
                'by_category' => $byCategory,
                'trend' => $trend,
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
