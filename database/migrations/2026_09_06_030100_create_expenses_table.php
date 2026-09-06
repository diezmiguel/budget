<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('spent_on')->index();
            // Scope helps distinguish apartment expenses from everything else.
            $table->string('scope', 20)->default('apartment')->index(); // 'apartment' | 'other'
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // owner / who registered
            $table->string('payment_method', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['scope', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
