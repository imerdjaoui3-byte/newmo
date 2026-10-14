<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each user now grants access to their own inbox when signing in with Google.
     */
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('connected_by');
            $table->foreignId('user_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->foreignId('connected_by')->nullable()->after('expires_at')->constrained('users')->nullOnDelete();
        });
    }
};
