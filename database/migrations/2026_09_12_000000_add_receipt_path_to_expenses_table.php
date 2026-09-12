<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Relative path (on the "public" disk) to a receipt image captured
            // from the phone camera. Null when no receipt was attached.
            $table->string('receipt_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });
    }
};
