<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $data = $this->extractData($request);
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }

        $expense = Expense::create($data);

        return response()->json($expense->load(['category', 'user']), 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json($expense->load(['category', 'user']));
    }

    public function update(ExpenseRequest $request, Expense $expense): JsonResponse
    {
        $data = $this->extractData($request);

        if ($request->hasFile('receipt')) {
            // Replace: remove the previous file before storing the new one.
            $this->deleteReceipt($expense);
            $data['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        } elseif ($request->boolean('remove_receipt')) {
            $this->deleteReceipt($expense);
            $data['receipt_path'] = null;
        }

        $expense->update($data);

        return response()->json($expense->load(['category', 'user']));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->deleteReceipt($expense);
        $expense->delete();

        return response()->json(['message' => 'Despesa removida.']);
    }

    /**
     * Validated attributes that map directly to columns, excluding file/control fields.
     *
     * @return array<string, mixed>
     */
    private function extractData(ExpenseRequest $request): array
    {
        $data = $request->validated();
        unset($data['receipt'], $data['remove_receipt']);

        return $data;
    }

    private function deleteReceipt(Expense $expense): void
    {
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }
    }
}
