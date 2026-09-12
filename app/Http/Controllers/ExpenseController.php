<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Services\ReceiptScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    /**
     * Disk used for receipt images. "local" is private (outside the web root),
     * so files are streamed through the app rather than served via a symlink —
     * this avoids the 403 issues that symlinked /storage causes on shared hosting.
     */
    private const RECEIPT_DISK = 'local';

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

    /**
     * Read a receipt photo and return suggested field values (no persistence).
     */
    public function scanReceipt(Request $request, ReceiptScanService $scanner): JsonResponse
    {
        $validated = $request->validate([
            'receipt' => ['required', 'image', 'mimes:jpeg,jpg,png,heic,heif,webp', 'max:8192'],
        ]);

        $categories = Category::query()
            ->where('type', 'expense')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->all();

        try {
            $suggestions = $scanner->scan($validated['receipt'], $categories);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['suggestions' => $suggestions]);
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        $data = $this->extractData($request);
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts', self::RECEIPT_DISK);
        }

        $expense = Expense::create($data);

        return response()->json($expense->load(['category', 'user']), 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json($expense->load(['category', 'user']));
    }

    /**
     * Stream a receipt image through the app (private disk, auth-protected).
     */
    public function receipt(Expense $expense): StreamedResponse
    {
        abort_if(! $expense->receipt_path, 404);

        $disk = Storage::disk(self::RECEIPT_DISK);
        abort_unless($disk->exists($expense->receipt_path), 404);

        return $disk->response(
            $expense->receipt_path,
            null,
            ['Cache-Control' => 'private, max-age=3600'],
        );
    }

    public function update(ExpenseRequest $request, Expense $expense): JsonResponse
    {
        $data = $this->extractData($request);

        if ($request->hasFile('receipt')) {
            // Replace: remove the previous file before storing the new one.
            $this->deleteReceipt($expense);
            $data['receipt_path'] = $request->file('receipt')->store('receipts', self::RECEIPT_DISK);
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
            Storage::disk(self::RECEIPT_DISK)->delete($expense->receipt_path);
        }
    }
}
