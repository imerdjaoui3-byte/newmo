<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->date('bill_date');
            $table->unsignedInteger('loaded_quantity');
            $table->unsignedInteger('sold_quantity');
            $table->unsignedInteger('returned_quantity');
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();

            $table->unique(['driver_id', 'bill_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
