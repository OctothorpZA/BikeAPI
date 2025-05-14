<?php

namespace Database\Factories;

use App\Models\Rental;
use App\Models\PaxProfile;
use App\Models\Bike;
use App\Models\User; // For staff_user_id
use App\Models\Team; // For start_team_id and end_team_id
use App\Models\ShipDeparture; // For ship_departure_id
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str; // For booking_code
use Carbon\Carbon; // For date manipulations

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rental>
 */
class RentalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Rental::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rentalStatuses = ['pending_payment', 'confirmed', 'active', 'completed', 'cancelled', 'overdue'];
        $paymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
        $paymentMethods = ['card', 'cash', 'online_transfer', 'pwa_credit'];

        // Timings
        $createdAt = $this->faker->dateTimeBetween('-3 months', 'now');
        $startTime = null;
        $expectedEndTime = null;
        $endTime = null;
        $status = $this->faker->randomElement($rentalStatuses);

        if (in_array($status, ['active', 'completed', 'overdue'])) {
            $startTime = Carbon::instance($createdAt)->addMinutes($this->faker->numberBetween(5, 60));
            if ($status === 'active' || $status === 'overdue') {
                $expectedEndTime = Carbon::instance($startTime)->addHours($this->faker->numberBetween(1, 8));
                if ($status === 'overdue' && $this->faker->boolean(80)) { // 80% of overdue rentals have an end time
                    $endTime = Carbon::instance($expectedEndTime)->addMinutes($this->faker->numberBetween(15, 120)); // Ended late
                } elseif($this->faker->boolean(30)) { // 30% of active rentals might have an end time already set if pre-paid and returned early
                     $endTime = Carbon::instance($expectedEndTime)->subMinutes($this->faker->numberBetween(0, 30));
                }
            } elseif ($status === 'completed') {
                $expectedEndTime = Carbon::instance($startTime)->addHours($this->faker->numberBetween(1, 8));
                $endTime = Carbon::instance($expectedEndTime)->subMinutes($this->faker->numberBetween(0, 60)); // Could be early or on time
            }
        } elseif ($status === 'confirmed' || $status === 'pending_payment') {
            $startTime = Carbon::instance($createdAt)->addDays($this->faker->numberBetween(0, 7))->addHours($this->faker->numberBetween(1,5));
            $expectedEndTime = Carbon::instance($startTime)->addHours($this->faker->numberBetween(1, 8));
        }


        return [
            'pax_profile_id' => PaxProfile::factory(),
            'bike_id' => Bike::factory(), // Or Bike::factory()->available()->create()->id,

            // Assign to a staff user. Ensure you have staff users or UserFactory handles roles.
            'staff_user_id' => User::factory(), // Or User::inRandomOrder()->whereHasRole('Staff')->first()?->id,

            'start_team_id' => Team::factory(), // Depot where rental started
            'end_team_id' => ($status === 'completed' || ($status === 'overdue' && $endTime)) ? Team::inRandomOrder()->first()?->id ?? Team::factory() : null, // Depot where rental ended, if applicable

            // Optionally link to a ship departure
            'ship_departure_id' => $this->faker->boolean(20) ? ShipDeparture::factory() : null,

            'booking_code' => strtoupper(Str::random(8)), // e.g., A1B2C3D4
            'status' => $status,

            'start_time' => $startTime,
            'end_time' => $endTime,
            'expected_end_time' => $expectedEndTime,

            'rental_price' => $this->faker->boolean(80) ? $this->faker->randomFloat(2, 5, 150) : null,
            'payment_status' => ($status === 'cancelled' || $status === 'pending_payment') ? 'pending' : $this->faker->randomElement($paymentStatuses),
            'payment_method' => $this->faker->optional()->randomElement($paymentMethods),
            'transaction_id' => $this->faker->boolean(60) ? 'txn_' . Str::random(12) : null,

            'notes' => $this->faker->boolean(20) ? $this->faker->paragraph() : null,
        ];
    }

    /**
     * Indicate that the rental is active.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rental>
     */
    public function active(): Factory
    {
        return $this->state(function (array $attributes) {
            $startTime = Carbon::now()->subHours($this->faker->numberBetween(1, 3));
            return [
                'status' => 'active',
                'start_time' => $startTime,
                'expected_end_time' => Carbon::instance($startTime)->addHours($this->faker->numberBetween(2, 8)),
                'payment_status' => 'paid',
            ];
        });
    }

    /**
     * Indicate that the rental is completed.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rental>
     */
    public function completed(): Factory
    {
        return $this->state(function (array $attributes) {
            $startTime = Carbon::now()->subDays($this->faker->numberBetween(1, 10))->subHours($this->faker->numberBetween(1,5));
            $expectedEndTime = Carbon::instance($startTime)->addHours($this->faker->numberBetween(1, 8));
            return [
                'status' => 'completed',
                'start_time' => $startTime,
                'expected_end_time' => $expectedEndTime,
                'end_time' => Carbon::instance($expectedEndTime)->subMinutes($this->faker->numberBetween(0, 30)), // Returned on time or early
                'payment_status' => 'paid',
                'end_team_id' => $attributes['start_team_id'] ?? Team::factory(), // Often returned to the same depot
            ];
        });
    }

    /**
     * Associate the rental with a specific ship departure.
     *
     * @param \App\Models\ShipDeparture|null $shipDeparture
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rental>
     */
    public function forShipDeparture(ShipDeparture $shipDeparture = null): Factory
    {
        return $this->state(function (array $attributes) use ($shipDeparture) {
            $shipDeparture = $shipDeparture ?? ShipDeparture::factory()->create();
            // Adjust rental times to be plausible relative to ship departure
            $rentalStartTime = Carbon::instance($shipDeparture->departure_datetime)->subHours($this->faker->numberBetween(3, 6));
            $expectedReturnTime = Carbon::instance($shipDeparture->final_boarding_datetime ?? $shipDeparture->departure_datetime)->subMinutes($this->faker->numberBetween(30, 90));

            return [
                'ship_departure_id' => $shipDeparture->id,
                'start_time' => $rentalStartTime,
                'expected_end_time' => $expectedReturnTime,
            ];
        });
    }
}
