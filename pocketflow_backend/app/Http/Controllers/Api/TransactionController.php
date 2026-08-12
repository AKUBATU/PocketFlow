<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->with('category:id,name,type,icon')
            ->latest('transaction_date')
            ->latest('id');

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->query('date_to'));
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('merchant', 'like', "%{$q}%")
                    ->orWhere('note', 'like', "%{$q}%")
                    ->orWhere('ocr_text', 'like', "%{$q}%");
            });
        }

        $transactions = $query->paginate((int) $request->query('per_page', 20));

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $validated['user_id'] = $request->user()->id;
        $validated['source'] = $validated['source'] ?? 'manual';

        $this->ensureCategoryAllowed($request, $validated['category_id'] ?? null, $validated['type']);

        if ($request->hasFile('proof_image')) {
            $path = $request->file('proof_image')->store('transaction-proofs', 'public');
            $validated['image_path'] = $path;
            $validated['source'] = $validated['source'] === 'manual' ? 'photo' : $validated['source'];
        }

        $transaction = Transaction::create($validated)->load('category:id,name,type,icon');

        return response()->json([
            'message' => 'Transaksi berhasil disimpan.',
            'data' => $transaction,
        ], 201);
    }

    public function show(Request $request, Transaction $transaction)
    {
        $this->authorizeTransaction($request, $transaction);

        return response()->json($transaction->load('category:id,name,type,icon'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->authorizeTransaction($request, $transaction);

        $validated = $this->validatePayload($request, true);
        $this->ensureCategoryAllowed($request, $validated['category_id'] ?? null, $validated['type']);

        if ($request->hasFile('proof_image')) {
            if ($transaction->image_path) {
                Storage::disk('public')->delete($transaction->image_path);
            }

            $validated['image_path'] = $request->file('proof_image')->store('transaction-proofs', 'public');
        }

        $transaction->update($validated);

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $transaction->fresh()->load('category:id,name,type,icon'),
        ]);
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $this->authorizeTransaction($request, $transaction);

        if ($transaction->image_path) {
            Storage::disk('public')->delete($transaction->image_path);
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Transaksi berhasil dihapus.',
        ]);
    }

    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'merchant' => ['nullable', 'string', 'max:120'],
            'transaction_date' => ['required', 'date'],
            'transaction_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
            'ocr_text' => ['nullable', 'string'],
            'source' => ['nullable', Rule::in(['manual', 'photo', 'email', 'bank_proof', 'other'])],
            'proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];

        $validated = $request->validate($rules);

        if (! empty($validated['transaction_time']) && strlen($validated['transaction_time']) === 5) {
            $validated['transaction_time'] .= ':00';
        }

        return $validated;
    }

    private function ensureCategoryAllowed(Request $request, ?int $categoryId, string $type): void
    {
        if (! $categoryId) {
            return;
        }

        $allowed = Category::query()
            ->where('id', $categoryId)
            ->where('type', $type)
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $request->user()->id);
            })
            ->exists();

        if (! $allowed) {
            abort(422, 'Kategori tidak valid untuk user atau tipe transaksi ini.');
        }
    }

    private function authorizeTransaction(Request $request, Transaction $transaction): void
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403, 'Transaksi ini bukan milik user login.');
        }
    }
}
