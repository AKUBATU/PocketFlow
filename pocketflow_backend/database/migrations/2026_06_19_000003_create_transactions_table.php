<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->decimal('amount', 15, 2);
            $table->string('merchant', 120)->nullable();
            $table->date('transaction_date');
            $table->time('transaction_time')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('image_path')->nullable();
            $table->longText('ocr_text')->nullable();
            $table->enum('source', ['manual', 'photo', 'email', 'bank_proof', 'other'])->default('manual');
            $table->timestamps();

            $table->index(['user_id', 'type', 'transaction_date']);
            $table->index(['category_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
