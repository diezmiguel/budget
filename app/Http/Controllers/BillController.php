<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillRequest;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Bill::query()->with(['category', 'responsibleUser']);

        if ($scope = $request->query('scope')) {
            $query->where('scope', $scope);
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $status = $request->query('status');
        if ($status === 'overdue') {
            $query->where('status', '!=', 'paid')
                ->whereDate('due_date', '<', Carbon::today());
        } elseif ($status === 'pending') {
            $query->where('status', '!=', 'paid')
                ->whereDate('due_date', '>=', Carbon::today());
        } elseif ($status === 'paid') {
            $query->where('status', 'paid');
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $bills = $query->orderBy('due_date')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($bills);
    }

    public function store(BillRequest $request): JsonResponse
    {
        $bill = Bill::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($bill->load(['category', 'responsibleUser']), 201);
    }

    public function show(Bill $bill): JsonResponse
    {
        return response()->json($bill->load(['category', 'responsibleUser']));
    }

    public function update(BillRequest $request, Bill $bill): JsonResponse
    {
        $bill->update($request->validated());

        return response()->json($bill->load(['category', 'responsibleUser']));
    }

    public function destroy(Bill $bill): JsonResponse
    {
        $bill->delete();

        return response()->json(['message' => 'Conta removida.']);
    }

    /**
     * Mark a bill as paid (or revert to pending).
     */
    public function markPaid(Request $request, Bill $bill): JsonResponse
    {
        $paid = $request->boolean('paid', true);

        $bill->update([
            'status' => $paid ? 'paid' : 'pending',
            'paid_at' => $paid ? Carbon::today() : null,
        ]);

        return response()->json($bill->load(['category', 'responsibleUser']));
    }
}
