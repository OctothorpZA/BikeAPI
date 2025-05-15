<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Team;
use App\Models\Bike;
use App\Models\PaxProfile;
use App\Models\PointOfInterest;
use App\Models\Rental;
use App\Models\ShipDeparture;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;
use Spatie\Permission\Models\Role;
use Carbon\Carbon; // For date manipulations in Rentals

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Demo Data Seeding...');

        if (app()->environment(['production'])) {
            $this->command->error('DemoDataSeeder should not be run in production! Aborting.');
            return;
        }

        // --- 1. Create Users & Assign Spatie Roles ---
        $users = $this->seedUsersAndRoles();

        // --- 2. Create Depots (Teams) & Assign Owners ---
        $depots = $this->seedDepots($users['owners']);

        // --- 3. Assign Supervisors and Staff to Depots ---
        if ($depots->isNotEmpty()) {
            $this->assignStaffToDepots($depots, $users['supervisors'], $users['staff']);
        } else {
            $this->command->warn('No depots created, skipping staff assignment to depots.');
        }

        // --- 4. Seed Bikes ---
        $bikes = collect(); // Initialize as an empty collection
        if ($depots->isNotEmpty()) {
            $bikes = $this->seedBikes($depots); // Capture seeded bikes
        } else {
            $this->command->warn('No depots created, skipping bike seeding.');
        }

        // --- 5. Seed Ship Departures ---
        $shipDepartures = $this->seedShipDepartures();

        // --- 6. Seed Pax Profiles (some linked to PWA Users) ---
        $paxProfiles = $this->seedPaxProfiles($users['pwaUsers']); // Capture seeded pax profiles

        // --- 7. Seed Additional Points Of Interest (Staff Picks, etc.) ---
        $this->seedAdditionalPOIs($depots, $users['staff'], $users['supervisors']);

        // --- 8. Seed Rentals ---
        if ($depots->isNotEmpty() && $users['staff']->isNotEmpty() && $paxProfiles->isNotEmpty() && $bikes->isNotEmpty()) {
            $this->seedRentals($depots, $shipDepartures, $users['staff'], $paxProfiles, $bikes);
        } else {
            $this->command->warn('Cannot seed rentals due to missing depots, staff, pax profiles, or bikes.');
        }

        $this->command->info('Demo Data Seeding Completed Successfully!');
    }

    private function seedUsersAndRoles(): array
    {
        $this->command->line('Seeding Users and Roles...');
        $password = Hash::make('password');

        $superAdmin = User::factory()->create([
            'name' => 'Super Admin User', 'email' => 'superadmin@dockandride.com', 'password' => $password,
        ]);
        $superAdmin->syncRoles(['Super Admin']);
        $this->command->info("Created User: {$superAdmin->email} (Super Admin)");

        $owner1 = User::factory()->create(['name' => 'Owner One', 'email' => 'owner1@dockandride.com', 'password' => $password]);
        $owner1->syncRoles(['Owner']);
        $this->command->info("Created User: {$owner1->email} (Owner)");
        $owner2 = User::factory()->create(['name' => 'Owner Two', 'email' => 'owner2@dockandride.com', 'password' => $password]);
        $owner2->syncRoles(['Owner']);
        $this->command->info("Created User: {$owner2->email} (Owner)");
        $owners = collect([$owner1, $owner2]);

        $supervisors = collect();
        $supervisorData = [
            ['name' => 'Supervisor Alpha', 'email' => 'supervisor.alpha@dockandride.com'],
            ['name' => 'Supervisor Bravo', 'email' => 'supervisor.bravo@dockandride.com'],
            ['name' => 'Supervisor Charlie', 'email' => 'supervisor.charlie@dockandride.com'],
        ];
        foreach ($supervisorData as $data) {
            $supervisor = User::factory()->create(array_merge($data, ['password' => $password]));
            $supervisor->syncRoles(['Supervisor']);
            $supervisors->push($supervisor);
            $this->command->info("Created User: {$supervisor->email} (Supervisor)");
        }

        $staffMembers = User::factory(10)->create(['password' => $password]);
        foreach ($staffMembers as $staff) {
            if (!$staff->hasRole('Staff')) { $staff->syncRoles(['Staff']); }
        }
        $this->command->info("Created 10 Staff users.");


        $pwaUsers = collect();
        $pwaUser1 = User::factory()->create(['name' => 'PWA User One', 'email' => 'pwauser1@example.com', 'password' => $password]);
        $pwaUser1->syncRoles([]); $pwaUsers->push($pwaUser1);
        $this->command->info("Created User: {$pwaUser1->email} (PWA User)");
        $pwaUser2 = User::factory()->create(['name' => 'PWA User Two', 'email' => 'pwauser2@example.com', 'password' => $password]);
        $pwaUser2->syncRoles([]); $pwaUsers->push($pwaUser2);
        $this->command->info("Created User: {$pwaUser2->email} (PWA User)");

        $this->command->line('Users and Roles seeded.');
        return ['superAdmin' => $superAdmin, 'owners' => $owners, 'supervisors' => $supervisors, 'staff' => $staffMembers, 'pwaUsers' => $pwaUsers];
    }

    private function seedDepots($owners)
    {
        $this->command->line('Seeding Depots (Teams)...');
        $depots = collect();
        if ($owners->isEmpty()) {
            $this->command->warn('No owners available to assign to depots. Depots will not be created.');
            return $depots;
        }
        $depotData = [
            ['name' => 'Waterfront Depot', 'owner_index' => 0],
            ['name' => 'Downtown Hub', 'owner_index' => 0],
            ['name' => 'Airport Kiosk', 'owner_index' => 1],
        ];
        foreach ($depotData as $i => $data) {
            $owner = $owners->get($data['owner_index'] % $owners->count());
            if (!$owner) {
                $this->command->error("Could not find an owner for depot: {$data['name']}. Skipping.");
                continue;
            }
            $depot = Team::factory()->create([
                'user_id' => $owner->id, 'name' => $data['name'], 'personal_team' => false,
            ]);
            $depots->push($depot);
            $this->command->info("Created Depot: '{$depot->name}' owned by {$owner->email}.");
        }
        $this->command->line('Depots seeded.');
        return $depots;
    }

    private function assignStaffToDepots($depots, $supervisors, $staffMembers)
    {
        $this->command->line('Assigning Supervisors and Staff to Depots...');
        if ($depots->isEmpty() || ($supervisors->isEmpty() && $staffMembers->isEmpty())) {
            $this->command->warn('No depots, supervisors, or staff to process for assignment. Skipping.');
            return;
        }
        $supervisorIndex = 0; $staffIndex = 0;
        foreach ($depots as $depot) {
            $this->command->info("Processing Depot for staff assignment: {$depot->name}");
            if ($supervisors->isNotEmpty()) {
                $supervisorToAssign = $supervisors->get($supervisorIndex % $supervisors->count());
                if ($supervisorToAssign && !$depot->users->contains($supervisorToAssign->id)) {
                    $depot->users()->attach($supervisorToAssign, ['role' => 'admin']);
                    $supervisorToAssign->switchTeam($depot);
                    $this->command->line(" -> Assigned Supervisor: {$supervisorToAssign->email} to {$depot->name} with 'admin' Jetstream role.");
                } $supervisorIndex++;
            }
            if ($staffMembers->isNotEmpty()) {
                $staffToAssignCount = $this->faker()->numberBetween(2, 4);
                for ($i = 0; $i < $staffToAssignCount; $i++) {
                    $staffToAssign = $staffMembers->get($staffIndex % $staffMembers->count());
                    if ($staffToAssign && !$depot->users->contains($staffToAssign->id)) {
                        $depot->users()->attach($staffToAssign, ['role' => 'editor']);
                        $staffToAssign->switchTeam($depot);
                        $this->command->line("    -> Assigned Staff: {$staffToAssign->email} to {$depot->name} with 'editor' Jetstream role.");
                    } $staffIndex++;
                }
            }
        }
        $this->command->line('Supervisors and Staff assigned to Depots.');
    }

    private function seedBikes($depots)
    {
        $this->command->line('Seeding Bikes...');
        $allBikes = collect();
        if ($depots->isEmpty()) {
            $this->command->warn('No depots available to seed bikes into. Skipping bike seeding.');
            return $allBikes;
        }
        $bikeTypes = ['standard', 'electric', 'mountain', 'kids'];
        $bikeStatuses = ['available', 'maintenance', 'unavailable'];
        foreach ($depots as $depot) {
            $numberOfBikes = $this->faker()->numberBetween(8, 15);
            $this->command->info("Creating {$numberOfBikes} bikes for Depot: {$depot->name} (ID: {$depot->id})");
            for ($i = 0; $i < $numberOfBikes; $i++) {
                $bike = Bike::factory()->create([
                    'team_id' => $depot->id,
                    'type' => $this->faker()->randomElement($bikeTypes),
                    'status' => $this->faker()->randomElement($bikeStatuses),
                ]);
                $allBikes->push($bike);
            }
            $this->command->info(" -> {$numberOfBikes} bikes created for {$depot->name}.");
        }
        $this->command->line('Bikes seeded.');
        return $allBikes;
    }

    private function seedShipDepartures()
    {
        $this->command->line('Seeding Ship Departures...');
        $generalDepartures = ShipDeparture::factory(10)->create();
        $todayDepartures = ShipDeparture::factory(3)->departingToday()->create();
        $pastDepartures = ShipDeparture::factory(2)->departed()->create();

        $this->command->info("Created {$generalDepartures->count()} general ship departures.");
        $this->command->info("Created {$todayDepartures->count()} ship departures for today.");
        $this->command->info("Created {$pastDepartures->count()} past ship departures.");
        $this->command->line('Ship Departures seeded.');
        return ShipDeparture::all();
    }

    private function seedPaxProfiles($pwaUsers)
    {
        $this->command->line('Seeding Pax Profiles...');
        $paxProfiles = collect();

        if ($pwaUsers->isNotEmpty()) {
            foreach ($pwaUsers as $pwaUser) {
                $profile = PaxProfile::factory()->create([
                    'user_id' => $pwaUser->id,
                    'email' => $pwaUser->email,
                    'first_name' => Str::before($pwaUser->name, ' '),
                    'last_name' => Str::after($pwaUser->name, ' ') ?: $this->faker()->lastName, // Fallback for single word names
                ]);
                $paxProfiles->push($profile);
                $this->command->info("Created PaxProfile for PWA User: {$pwaUser->email}");
            }
        }

        $standaloneProfiles = PaxProfile::factory(15)->create();
        $paxProfiles = $paxProfiles->merge($standaloneProfiles);
        $this->command->info("Created {$standaloneProfiles->count()} standalone PaxProfiles.");

        $this->command->line('Pax Profiles seeded.');
        return $paxProfiles;
    }

    private function seedAdditionalPOIs($depots, $staffMembers, $supervisors)
    {
        $this->command->line('Seeding Additional Points Of Interest (Staff Picks)...');
        if ($staffMembers->isEmpty() && $supervisors->isEmpty()) {
            $this->command->warn('No staff or supervisors available to create/approve Staff Pick POIs. Skipping.');
            return;
        }

        $creators = $staffMembers->merge($supervisors)->unique('id');
        $approvers = $supervisors->merge(User::role('Owner')->get())->merge(User::role('Super Admin')->get())->unique('id');

        if ($creators->isEmpty()) {
            $this->command->warn('No creators (staff/supervisors) found for Staff Pick POIs. Skipping.');
            return;
        }


        for ($i = 0; $i < 15; $i++) {
            $creator = $creators->random();
            $isApproved = $this->faker()->boolean(60);
            $teamForPOI = $depots->isNotEmpty() && $this->faker()->boolean(40) ? $depots->random() : null;

            PointOfInterest::factory()->create([
                'name' => 'Staff Pick: ' . $this->faker()->company() . ' ' . $this->faker()->randomElement(['View', 'Spot', 'Cafe', 'Gem']),
                'category' => PointOfInterest::CATEGORY_STAFF_PICK,
                'created_by_user_id' => $creator->id,
                'is_approved' => $isApproved,
                'approved_by_user_id' => $isApproved && $approvers->isNotEmpty() ? $approvers->random()->id : null,
                'team_id' => $teamForPOI?->id,
                'description' => $this->faker()->bs(), // Corrected: $this->faker()
                'address_line_1' => $this->faker()->streetAddress(), // Corrected: $this->faker()
                'city' => $this->faker()->city(), // Corrected: $this->faker()
                // Add other necessary fields like latitude, longitude or ensure factory provides them
                'latitude' => $this->faker()->latitude(),
                'longitude' => $this->faker()->longitude(),
            ]);
        }
        $this->command->info('Additional Points Of Interest (Staff Picks) seeded.');
    }

    private function seedRentals($depots, $shipDepartures, $staffMembers, $paxProfiles, $allBikes)
    {
        $this->command->line('Seeding Rentals...');
        if ($depots->isEmpty() || $staffMembers->isEmpty() || $paxProfiles->isEmpty() || $allBikes->isEmpty()) {
            $this->command->warn('Insufficient data to seed rentals (missing depots, staff, pax profiles, or bikes). Skipping.');
            return;
        }

        $availableBikes = $allBikes->where('status', 'available')->shuffle();
        if ($availableBikes->isEmpty()) {
            $this->command->warn('No available bikes to create rentals. Skipping rental seeding.');
            return;
        }
        $bikeIndex = 0;

        for ($i = 0; $i < 50; $i++) {
            if ($bikeIndex >= $availableBikes->count()) {
                $this->command->warn('Ran out of available bikes for new rentals.');
                break;
            }
            $bikeToRent = $availableBikes->get($bikeIndex);
            if (!$bikeToRent) { // Should ideally not happen with the check above
                $this->command->warn("Bike at index {$bikeIndex} is null, skipping this rental iteration.");
                continue;
            }


            $startDepot = $depots->random();
            $bikeToRent->update(['team_id' => $startDepot->id, 'status' => 'available']);


            $paxProfile = $paxProfiles->random();
            $staffUser = $staffMembers->random();
            $isCruisePax = $this->faker()->boolean(30);
            $shipDeparture = $isCruisePax && $shipDepartures->isNotEmpty() ? $shipDepartures->random() : null;

            $statusChance = $this->faker()->numberBetween(1, 100);
            $rentalStatus = 'confirmed';
            $paymentStatus = 'pending';
            $startTime = null; $expectedEndTime = null; $endTime = null; $endDepotId = null;

            if ($statusChance <= 60) {
                $rentalStatus = 'active'; $paymentStatus = 'paid';
                $startTime = Carbon::instance($this->faker()->dateTimeBetween('-3 days', 'now'));
                $expectedEndTime = Carbon::instance($startTime)->addHours($this->faker()->numberBetween(2, 8));
                $bikeToRent->update(['status' => 'rented']);
                $bikeIndex++;
            } elseif ($statusChance <= 90) {
                $rentalStatus = 'completed'; $paymentStatus = 'paid';
                $startTime = Carbon::instance($this->faker()->dateTimeBetween('-2 months', '-4 days'));
                $expectedEndTime = Carbon::instance($startTime)->addHours($this->faker()->numberBetween(1, 8));
                $endTime = Carbon::instance($expectedEndTime)->subMinutes($this->faker()->numberBetween(0, 60));
                $endDepotId = $this->faker()->boolean(70) ? $startDepot->id : ($depots->where('id', '!=', $startDepot->id)->isNotEmpty() ? $depots->where('id', '!=', $startDepot->id)->random()->id : $startDepot->id);
                // Bike is not rented anymore, but its status would have been 'available' after completion.
                // For simplicity, we don't revert status here, assuming it's handled by application logic.
                // If not using this bike for another active rental, it's fine.
            } else {
                $rentalStatus = $this->faker()->randomElement(['pending_payment', 'confirmed']);
                $startTime = Carbon::instance($this->faker()->dateTimeBetween('+1 hour', '+7 days'));
                $expectedEndTime = Carbon::instance($startTime)->addHours($this->faker()->numberBetween(1, 8));
            }

            Rental::factory()->create([
                'pax_profile_id' => $paxProfile->id,
                'bike_id' => $bikeToRent->id,
                'staff_user_id' => $staffUser->id,
                'start_team_id' => $startDepot->id,
                'end_team_id' => $endDepotId,
                'ship_departure_id' => $shipDeparture?->id,
                'status' => $rentalStatus,
                'payment_status' => $paymentStatus,
                'start_time' => $startTime,
                'expected_end_time' => $expectedEndTime,
                'end_time' => $endTime,
            ]);
        }
        $this->command->info('Rentals seeded.');
    }

    // Helper for Faker instance
    private function faker()
    {
        return \Faker\Factory::create();
    }
}
