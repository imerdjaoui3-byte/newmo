<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('customers')->whereNull('rating')->update(['rating' => 5.00]);

        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->default(5.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->nullable()->change();
        });
    }
};
