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
        Schema::create('ship_departures', function (Blueprint $table) {
            $table->id(); // Primary key

            $table->string('ship_name');
            $table->string('cruise_line_name')->nullable();
            $table->string('departure_port_name');
            $table->string('arrival_port_name')->nullable(); // If it's a one-way trip relevant to the rental period

            $table->timestamp('departure_datetime'); // Scheduled departure date and time
            $table->timestamp('expected_arrival_datetime_at_port')->nullable(); // Expected arrival back at the departure port (if round trip) or next port

            // This could be the "all aboard" time, which is crucial for renters.
            $table->timestamp('final_boarding_datetime')->nullable();

            $table->string('voyage_number')->nullable(); // Cruise voyage or itinerary number

            $table->text('notes')->nullable(); // Any relevant notes about this specific departure or ship

            $table->boolean('is_active')->default(true); // To easily enable/disable schedules

            $table->timestamps(); // created_at and updated_at
            $table->softDeletes(); // Optional, if you want to soft delete schedules
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ship_departures');
    }
};
