<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar; // Required to reset cache
// use App\Models\User; // Not strictly needed here unless creating a default user in this seeder

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define Permission Guard Name
        $guardName = 'web'; // Default guard, ensure it matches your auth.php config

        // Define Core Permissions
        $permViewProfile = Permission::firstOrCreate(['name' => 'view profile', 'guard_name' => $guardName]);
        $permEditProfile = Permission::firstOrCreate(['name' => 'edit profile', 'guard_name' => $guardName]);
        $permViewBikes = Permission::firstOrCreate(['name' => 'view bikes', 'guard_name' => $guardName]);
        $permCreateBikes = Permission::firstOrCreate(['name' => 'create bikes', 'guard_name' => $guardName]);
        $permEditBikes = Permission::firstOrCreate(['name' => 'edit bikes', 'guard_name' => $guardName]);
        $permDeleteBikes = Permission::firstOrCreate(['name' => 'delete bikes', 'guard_name' => $guardName]);
        $permScrapBikes = Permission::firstOrCreate(['name' => 'scrap bikes', 'guard_name' => $guardName]);
        $permViewRentals = Permission::firstOrCreate(['name' => 'view rentals', 'guard_name' => $guardName]);
        $permCreateRentals = Permission::firstOrCreate(['name' => 'create rentals', 'guard_name' => $guardName]);
        $permEditRentals = Permission::firstOrCreate(['name' => 'edit rentals', 'guard_name' => $guardName]);
        $permCancelRentals = Permission::firstOrCreate(['name' => 'cancel rentals', 'guard_name' => $guardName]);
        $permViewPaxProfiles = Permission::firstOrCreate(['name' => 'view pax_profiles', 'guard_name' => $guardName]);
        $permEditPaxProfiles = Permission::firstOrCreate(['name' => 'edit pax_profiles', 'guard_name' => $guardName]);
        $permViewDepots = Permission::firstOrCreate(['name' => 'view depots', 'guard_name' => $guardName]);
        $permManageDepots = Permission::firstOrCreate(['name' => 'manage depots', 'guard_name' => $guardName]);
        $permAssignDepotStaff = Permission::firstOrCreate(['name' => 'assign depot staff', 'guard_name' => $guardName]);
        $permViewPOIs = Permission::firstOrCreate(['name' => 'view pois', 'guard_name' => $guardName]);
        $permCreatePOIs = Permission::firstOrCreate(['name' => 'create pois', 'guard_name' => $guardName]);
        $permEditPOIs = Permission::firstOrCreate(['name' => 'edit pois', 'guard_name' => $guardName]);
        $permDeletePOIs = Permission::firstOrCreate(['name' => 'delete pois', 'guard_name' => $guardName]);
        $permApprovePOIs = Permission::firstOrCreate(['name' => 'approve pois', 'guard_name' => $guardName]);
        $permViewShipDepartures = Permission::firstOrCreate(['name' => 'view ship_departures', 'guard_name' => $guardName]);
        $permManageShipDepartures = Permission::firstOrCreate(['name' => 'manage ship_departures', 'guard_name' => $guardName]);
        $permViewUsers = Permission::firstOrCreate(['name' => 'view users', 'guard_name' => $guardName]);
        $permCreateUsers = Permission::firstOrCreate(['name' => 'create users', 'guard_name' => $guardName]);
        $permEditUsers = Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => $guardName]);
        $permDeleteUsers = Permission::firstOrCreate(['name' => 'delete users', 'guard_name' => $guardName]);
        $permAssignRoles = Permission::firstOrCreate(['name' => 'assign roles', 'guard_name' => $guardName]);
        $permImpersonateUsers = Permission::firstOrCreate(['name' => 'impersonate users', 'guard_name' => $guardName]);
        $permViewSystemLogs = Permission::firstOrCreate(['name' => 'view system_logs', 'guard_name' => $guardName]);
        $permManageSettings = Permission::firstOrCreate(['name' => 'manage settings', 'guard_name' => $guardName]);
        $permViewOwnerDashboard = Permission::firstOrCreate(['name' => 'view owner_dashboard', 'guard_name' => $guardName]);
        $permViewAllDepotData = Permission::firstOrCreate(['name' => 'view all_depot_data', 'guard_name' => $guardName]);
        $permViewSupervisorDashboard = Permission::firstOrCreate(['name' => 'view supervisor_dashboard', 'guard_name' => $guardName]);

        // Define Roles
        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => $guardName]);
        $supervisorRole = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => $guardName]);
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => $guardName]);
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guardName]);
        $pwaUserRole = Role::firstOrCreate(['name' => 'PWA User', 'guard_name' => $guardName]); // <-- NEW ROLE

        // Assign Permissions to Staff Role
        $staffRole->syncPermissions([
            $permViewProfile, $permEditProfile,
            $permViewBikes,
            $permCreateRentals, $permViewRentals, $permEditRentals,
            $permViewPaxProfiles,
            $permViewShipDepartures, // Staff can view ship departures
        ]);

        // Assign Permissions to Supervisor Role
        $supervisorRole->syncPermissions([
            $permViewProfile, $permEditProfile,
            $permViewBikes, $permCreateBikes, $permEditBikes, $permDeleteBikes, $permScrapBikes,
            $permViewRentals, $permCreateRentals, $permEditRentals, $permCancelRentals,
            $permViewPaxProfiles, $permEditPaxProfiles,
            $permViewDepots,
            $permAssignDepotStaff,
            $permViewPOIs, $permCreatePOIs, $permEditPOIs, $permDeletePOIs, $permApprovePOIs,
            $permViewShipDepartures, $permManageShipDepartures,
            $permViewSupervisorDashboard, $permViewUsers,
        ]);

        // Assign Permissions to Owner Role
        $ownerRole->syncPermissions($supervisorRole->permissions); // Start with supervisor permissions
        $ownerRole->givePermissionTo([ // Then add owner-specific ones
            $permManageDepots,
            $permViewOwnerDashboard,
            $permViewAllDepotData,
            $permCreateUsers, $permEditUsers, $permDeleteUsers, $permAssignRoles
        ]);

        // Super Admin Role gets all permissions
        $allPermissions = Permission::all();
        $superAdminRole->syncPermissions($allPermissions);

        // PWA User Role Permissions (minimal)
        // Example: $pwaUserRole->givePermissionTo([$permViewProfile]); // If they can view their own profile via API

        $this->command->info('Roles and Permissions (including PWA User) seeded successfully.');
    }
}
