<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $month = $request->query('month', now()->format('Y-m'));

        try {
            $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
        }

        $end = $start->copy()->endOfMonth();

        /*
         * Penting:
         * Gunakan transactions.user_id, transactions.type, dan transactions.transaction_date.
         * Kalau hanya pakai user_id atau type, MySQL bisa bingung karena tabel categories
         * juga punya kolom user_id dan type.
         */
        $base = Transaction::query()
            ->where('transactions.user_id', $user->id);

        $totalIncomeAll = (clone $base)
            ->where('transactions.type', 'income')
            ->sum('transactions.amount');

        $totalExpenseAll = (clone $base)
            ->where('transactions.type', 'expense')
            ->sum('transactions.amount');

        $balance = $totalIncomeAll - $totalExpenseAll;

        $monthly = (clone $base)
            ->whereBetween('transactions.transaction_date', [
                $start->toDateString(),
                $end->toDateString(),
            ]);

        $income = (clone $monthly)
            ->where('transactions.type', 'income')
            ->sum('transactions.amount');

        $expense = (clone $monthly)
            ->where('transactions.type', 'expense')
            ->sum('transactions.amount');

        $remaining = $income - $expense;

        $ratio = $income > 0 ? ($expense / $income) * 100 : null;
        $condition = $this->condition((float) $income, (float) $expense);

        $topCategory = (clone $monthly)
            ->where('transactions.type', 'expense')
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->first();

        $latest = (clone $base)
            ->with('category:id,name,type,icon')
            ->orderByDesc('transactions.transaction_date')
            ->orderByDesc('transactions.id')
            ->limit(5)
            ->get();

        return response()->json([
            'month' => $start->format('Y-m'),
            'balance' => (float) $balance,
            'monthly_income' => (float) $income,
            'monthly_expense' => (float) $expense,
            'monthly_remaining' => (float) $remaining,
            'expense_ratio' => $ratio !== null ? round($ratio, 2) : null,
            'condition' => $condition,
            'top_expense_category' => $topCategory ? [
                'name' => $topCategory->category_name ?? 'Tanpa Kategori',
                'total' => (float) $topCategory->total,
            ] : null,
            'latest_transactions' => $latest,
        ]);
    }

    private function condition(float $income, float $expense): array
    {
        if ($income <= 0 && $expense > 0) {
            return [
                'status' => 'deficit',
                'title' => 'Belum ada pemasukan bulan ini.',
                'message' => 'Pengeluaran sudah tercatat, tetapi pemasukan bulan ini belum ada.',
            ];
        }

        if ($income <= 0) {
            return [
                'status' => 'neutral',
                'title' => 'Mulai catat transaksi.',
                'message' => 'Tambahkan pemasukan dan pengeluaran agar ringkasan bisa dihitung.',
            ];
        }

        $ratio = $expense / $income;

        if ($ratio < 0.7) {
            return [
                'status' => 'healthy',
                'title' => 'Keuangan bulan ini cukup sehat.',
                'message' => 'Pengeluaran masih di bawah 70% dari pemasukan.',
            ];
        }

        if ($ratio <= 1) {
            return [
                'status' => 'warning',
                'title' => 'Pengeluaran mulai mendekati pemasukan.',
                'message' => 'Coba cek kategori terbesar agar sisa dana tetap aman.',
            ];
        }

        return [
            'status' => 'deficit',
            'title' => 'Pengeluaran melebihi pemasukan.',
            'message' => 'Bulan ini defisit. Prioritaskan pengeluaran wajib dulu.',
        ];
    }
}