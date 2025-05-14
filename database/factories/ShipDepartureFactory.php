<?php

namespace Database\Factories;

use App\Models\ShipDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShipDeparture>
 */
class ShipDepartureFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ShipDeparture::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cruiseLines = ['Royal Caribbean', 'Carnival Cruise Line', 'Norwegian Cruise Line', 'MSC Cruises', 'Princess Cruises', 'Celebrity Cruises'];
        $shipNamePrefixes = ['Ocean', 'Sea', 'Star', 'Grand', 'Majestic', 'Island', 'Regal', 'Royal'];
        $shipNameSuffixes = ['Dreamer', 'Explorer', 'Voyager', 'Princess', 'Star', 'Breeze', 'Spirit', 'Jewel'];
        $portNames = ['Port Canaveral', 'Port Miami', 'Port Everglades', 'Port of Los Angeles', 'Port of Seattle', 'Port of Galveston', 'Port of New York', 'Port of Southampton', 'Port of Barcelona', 'Port Civitavecchia (Rome)'];

        $departureDatetime = $this->faker->dateTimeBetween('-1 month', '+2 months');
        $expectedArrivalAtPort = Carbon::instance($departureDatetime)->addHours($this->faker->numberBetween(8, 72)); // Example duration
        $finalBoardingTime = Carbon::instance($departureDatetime)->subHours($this->faker->numberBetween(1, 2));


        return [
            'ship_name' => $this->faker->randomElement($shipNamePrefixes) . ' ' . $this->faker->randomElement($shipNameSuffixes),
            'cruise_line_name' => $this->faker->randomElement($cruiseLines),
            'departure_port_name' => $this->faker->randomElement($portNames),
            'arrival_port_name' => $this->faker->optional(0.7)->randomElement($portNames), // 70% chance of having a distinct arrival port
            'departure_datetime' => $departureDatetime,
            'expected_arrival_datetime_at_port' => $this->faker->boolean(80) ? $expectedArrivalAtPort : null,
            'final_boarding_datetime' => $finalBoardingTime,
            'voyage_number' => 'VOY' . $this->faker->unique()->randomNumber(5),
            'notes' => $this->faker->boolean(20) ? $this->faker->sentence() : null,
            'is_active' => $this->faker->boolean(90), // 90% are active
        ];
    }

    /**
     * Indicate that the ship departure is for today.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShipDeparture>
     */
    public function departingToday(): Factory
    {
        return $this->state(function (array $attributes) {
            $departureTime = Carbon::today()->addHours($this->faker->numberBetween(8, 18)); // Departs today between 8 AM and 6 PM
            return [
                'departure_datetime' => $departureTime,
                'expected_arrival_datetime_at_port' => Carbon::instance($departureTime)->addDays($this->faker->numberBetween(1,7)),
                'final_boarding_datetime' => Carbon::instance($departureTime)->subHour(),
            ];
        });
    }

    /**
     * Indicate that the ship departure is in the past.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShipDeparture>
     */
    public function departed(): Factory
    {
        return $this->state(function (array $attributes) {
            $departureTime = Carbon::now()->subDays($this->faker->numberBetween(1, 30));
            return [
                'departure_datetime' => $departureTime,
                 'expected_arrival_datetime_at_port' => Carbon::instance($departureTime)->addDays($this->faker->numberBetween(1,7)),
                'final_boarding_datetime' => Carbon::instance($departureTime)->subHour(),
            ];
        });
    }
}
