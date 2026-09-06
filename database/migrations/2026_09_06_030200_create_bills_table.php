<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->index();
            $table->string('status', 20)->default('pending')->index(); // 'pending' | 'paid' | 'overdue'
            $table->date('paid_at')->nullable();
            // Who the bill belongs to (free-text label so it can include people outside the user list).
            $table->string('owner_label')->nullable();
            // Optional link to a system user responsible for the bill.
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('scope', 20)->default('apartment')->index(); // 'apartment' | 'other'
            // Recurrence for repeating bills.
            $table->string('recurrence', 20)->default('none'); // 'none' | 'monthly' | 'yearly' | 'weekly'
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
