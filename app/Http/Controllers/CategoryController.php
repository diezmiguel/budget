<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()->withCount(['expenses', 'bills']);

        if ($type = $request->query('type')) {
            $query->whereIn('type', [$type, 'both']);
        }

        return response()->json(
            $query->orderBy('name')->get()
        );
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($category, 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category->loadCount(['expenses', 'bills']));
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Categoria removida.']);
    }
}
