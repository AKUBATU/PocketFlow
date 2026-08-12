<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Category::query()
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'icon' => ['nullable', 'string', 'max:30'],
        ]);

        $category = Category::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'icon' => $validated['icon'] ?? null,
            'is_default' => false,
        ]);

        return response()->json([
            'message' => 'Kategori berhasil dibuat.',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeCategory($request, $category);

        if ($category->is_default) {
            return response()->json([
                'message' => 'Kategori bawaan tidak bisa diubah.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'icon' => ['nullable', 'string', 'max:30'],
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $category,
        ]);
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorizeCategory($request, $category);

        if ($category->is_default) {
            return response()->json([
                'message' => 'Kategori bawaan tidak bisa dihapus.',
            ], 403);
        }

        if ($category->transactions()->exists()) {
            return response()->json([
                'message' => 'Kategori sudah dipakai transaksi, tidak bisa dihapus.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    private function authorizeCategory(Request $request, Category $category): void
    {
        if ($category->user_id !== $request->user()->id) {
            abort(403, 'Kategori ini bukan milik user login.');
        }
    }
}
