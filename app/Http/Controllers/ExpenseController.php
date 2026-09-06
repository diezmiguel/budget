<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::query()->with(['category', 'user']);

        if ($scope = $request->query('scope')) {
            $query->where('scope', $scope);
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('spent_on', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('spent_on', '<=', $to);
        }

        if ($search = $request->query('search')) {
            $query->where('description', 'like', '%'.$search.'%');
        }

        $expenses = $query->orderByDesc('spent_on')->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($expenses);
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        $expense = Expense::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($expense->load(['category', 'user']), 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json($expense->load(['category', 'user']));
    }

    public function update(ExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense->update($request->validated());

        return response()->json($expense->load(['category', 'user']));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(['message' => 'Despesa removida.']);
    }
}
