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
        Schema::create('pax_profiles', function (Blueprint $table) {
            $table->id(); // Primary key

            // Link to the PWA user account (if the pax profile is associated with a registered PWA user)
            // This assumes your PWA users are stored in the standard 'users' table.
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable(); // Email for the profile, might be different from user account or for non-registered users
            $table->string('phone_number')->nullable();

            // Additional demographic or identification information, all optional
            $table->string('country_of_residence')->nullable();
            $table->string('passport_number')->nullable(); // Consider encryption or if this is truly necessary due to sensitivity
            $table->date('date_of_birth')->nullable();

            $table->text('notes')->nullable(); // Any specific notes about this passenger/client

            $table->timestamps(); // created_at and updated_at
            $table->softDeletes(); // Optional: if you want to soft delete pax profiles
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pax_profiles');
    }
};
