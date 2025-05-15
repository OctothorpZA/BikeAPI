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
        Schema::table('users', function (Blueprint $table) {
            // Add the google_id column
            // It should be nullable because not all users will sign in with Google.
            // It should be unique because each Google ID is unique to a user.
            // You can place it after a specific column, e.g., 'email' or 'remember_token'.
            $table->string('google_id')->nullable()->unique()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the column if the migration is rolled back
            $table->dropColumn('google_id');
        });
    }
};
