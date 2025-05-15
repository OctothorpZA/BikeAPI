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
        Schema::create('points_of_interest', function (Blueprint $table) {
            $table->id(); // Primary key

            // Optional: Link to a team if the POI is a Depot.
            // This allows Depots (Teams) to also be listed as POIs.
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');

            $table->string('name');
            $table->string('category')->default('General')->after('team_id'); // e.g., 'Depot', 'Cafe', 'Landmark', 'Viewpoint', 'Repair Station'

            $table->text('description')->nullable();

            $table->decimal('latitude', 10, 7); // Latitude
            $table->decimal('longitude', 10, 7); // Longitude

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code', 2)->nullable(); // ISO 3166-1 alpha-2 country code

            $table->string('phone_number')->nullable();
            $table->string('website_url')->nullable();

            $table->string('primary_image_url')->nullable(); // URL to a primary image for the POI
            // Consider a separate 'poi_images' table if multiple images per POI are needed.

            $table->boolean('is_approved')->default(false); // For staff-suggested POIs needing approval
            $table->boolean('is_active')->default(true);   // To show/hide POI on maps

            // Optional: Who created/approved this POI
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps(); // created_at and updated_at
            $table->softDeletes(); // Optional
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_of_interest');
    }
};
