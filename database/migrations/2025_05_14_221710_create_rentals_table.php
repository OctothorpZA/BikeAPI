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
        Schema::create('rentals', function (Blueprint $table) {
            $table->id(); // Primary key

            // Foreign key to the pax_profiles table (the customer/renter)
            $table->foreignId('pax_profile_id')->constrained('pax_profiles')->onDelete('cascade');

            // Foreign key to the bikes table
            $table->foreignId('bike_id')->constrained('bikes')->onDelete('cascade'); // Or restrict/set null depending on rules

            // Foreign key to the users table (staff member who initiated/processed the rental)
            // Nullable if a rental can be system-initiated or if staff tracking isn't mandatory at creation.
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->onDelete('set null');

            // Depot (Team) where the rental started
            $table->foreignId('start_team_id')->constrained('teams')->onDelete('cascade');

            // Depot (Team) where the rental ended (nullable, as it's set upon return)
            $table->foreignId('end_team_id')->nullable()->constrained('teams')->onDelete('set null');

            // Optional: Link to a ship departure schedule, especially for cruise passengers
            $table->foreignId('ship_departure_id')->nullable()->constrained('ship_departures')->onDelete('set null');

            $table->string('booking_code')->unique(); // Unique code for PWA login/retrieval

            // Status of the rental (e.g., 'pending_payment', 'confirmed', 'active', 'completed', 'cancelled', 'overdue')
            $table->string('status')->default('pending_payment');

            $table->timestamp('start_time')->nullable(); // Actual start time of the rental
            $table->timestamp('end_time')->nullable();   // Actual end time of the rental
            $table->timestamp('expected_end_time')->nullable(); // Expected return time, crucial for cruise pax

            $table->decimal('rental_price', 8, 2)->nullable(); // Optional: if price is determined per rental
            $table->string('payment_status')->default('pending'); // e.g., 'pending', 'paid', 'refunded'
            $table->string('payment_method')->nullable(); // e.g., 'card', 'cash', 'online'
            $table->string('transaction_id')->nullable(); // For payment gateway reference

            $table->text('notes')->nullable(); // Staff notes or customer requests

            $table->timestamps(); // created_at and updated_at
            $table->softDeletes(); // Optional: for soft deleting rental records
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
