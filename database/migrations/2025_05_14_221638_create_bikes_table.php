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
        Schema::create('bikes', function (Blueprint $table) {
            $table->id(); // Primary key: id (bigint, unsigned, auto-increment)

            // Foreign key for the team (Depot) this bike belongs to (its home depot).
            // Assumes 'teams' table uses bigIncrements for its 'id'.
            // Constrained ensures foreign key integrity.
            // Nullable if a bike might not be assigned to a depot initially,
            // but typically a bike has a home depot. Let's make it non-nullable for now.
            // If it can be unassigned, use ->nullable().
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');

            $table->string('bike_identifier')->unique(); // Unique identifier for the bike (e.g., serial number, asset tag)
            $table->string('nickname')->nullable(); // Optional friendly name for the bike

            // Type of bike (e.g., 'standard', 'electric', 'mountain', 'kids').
            // Using a string here for flexibility. Could also be an enum if types are strictly defined.
            $table->string('type')->default('standard');

            // Status of the bike.
            // Examples: 'available', 'rented', 'maintenance', 'unavailable', 'scrapped'.
            $table->string('status')->default('available');

            // For potential future GPS tracking or last known location.
            $table->decimal('current_latitude', 10, 7)->nullable(); // e.g., 12.3456789 (latitude)
            $table->decimal('current_longitude', 10, 7)->nullable(); // e.g., 123.4567890 (longitude)

            $table->text('notes')->nullable(); // Any additional notes about the bike

            $table->timestamps(); // Adds created_at and updated_at columns
            $table->softDeletes(); // Adds deleted_at column for soft deletes (optional, but good for assets)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bikes');
    }
};
