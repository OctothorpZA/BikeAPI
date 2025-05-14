<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar; // Required to reset cache
use App\Models\User; // For potentially assigning a super admin user

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
        // General User/Profile Permissions
        $permViewProfile = Permission::firstOrCreate(['name' => 'view profile', 'guard_name' => $guardName]);
        $permEditProfile = Permission::firstOrCreate(['name' => 'edit profile', 'guard_name' => $guardName]);

        // Bike Management Permissions
        $permViewBikes = Permission::firstOrCreate(['name' => 'view bikes', 'guard_name' => $guardName]);
        $permCreateBikes = Permission::firstOrCreate(['name' => 'create bikes', 'guard_name' => $guardName]);
        $permEditBikes = Permission::firstOrCreate(['name' => 'edit bikes', 'guard_name' => $guardName]);
        $permDeleteBikes = Permission::firstOrCreate(['name' => 'delete bikes', 'guard_name' => $guardName]); // Typically Supervisor+
        $permScrapBikes = Permission::firstOrCreate(['name' => 'scrap bikes', 'guard_name' => $guardName]); // Alias for delete or specific status change

        // Rental Management Permissions
        $permViewRentals = Permission::firstOrCreate(['name' => 'view rentals', 'guard_name' => $guardName]);
        $permCreateRentals = Permission::firstOrCreate(['name' => 'create rentals', 'guard_name' => $guardName]);
        $permEditRentals = Permission::firstOrCreate(['name' => 'edit rentals', 'guard_name' => $guardName]);
        $permCancelRentals = Permission::firstOrCreate(['name' => 'cancel rentals', 'guard_name' => $guardName]);

        // PaxProfile Management
        $permViewPaxProfiles = Permission::firstOrCreate(['name' => 'view pax_profiles', 'guard_name' => $guardName]);
        $permEditPaxProfiles = Permission::firstOrCreate(['name' => 'edit pax_profiles', 'guard_name' => $guardName]);

        // Depot (Team) Management Permissions
        $permViewDepots = Permission::firstOrCreate(['name' => 'view depots', 'guard_name' => $guardName]); // General view
        $permManageDepots = Permission::firstOrCreate(['name' => 'manage depots', 'guard_name' => $guardName]); // CRUD for Owners/SuperAdmins
        $permAssignDepotStaff = Permission::firstOrCreate(['name' => 'assign depot staff', 'guard_name' => $guardName]); // Owner/Supervisor

        // Point of Interest (POI) Management
        $permViewPOIs = Permission::firstOrCreate(['name' => 'view pois', 'guard_name' => $guardName]);
        $permCreatePOIs = Permission::firstOrCreate(['name' => 'create pois', 'guard_name' => $guardName]); // Supervisor+
        $permEditPOIs = Permission::firstOrCreate(['name' => 'edit pois', 'guard_name' => $guardName]);   // Supervisor+
        $permDeletePOIs = Permission::firstOrCreate(['name' => 'delete pois', 'guard_name' => $guardName]); // Supervisor+
        $permApprovePOIs = Permission::firstOrCreate(['name' => 'approve pois', 'guard_name' => $guardName]);// Supervisor

        // Ship Departure Management
        $permViewShipDepartures = Permission::firstOrCreate(['name' => 'view ship_departures', 'guard_name' => $guardName]);
        $permManageShipDepartures = Permission::firstOrCreate(['name' => 'manage ship_departures', 'guard_name' => $guardName]); // Supervisor+

        // User Management (Staff/Supervisor level by Owner/SuperAdmin)
        $permViewUsers = Permission::firstOrCreate(['name' => 'view users', 'guard_name' => $guardName]);
        $permCreateUsers = Permission::firstOrCreate(['name' => 'create users', 'guard_name' => $guardName]);
        $permEditUsers = Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => $guardName]);
        $permDeleteUsers = Permission::firstOrCreate(['name' => 'delete users', 'guard_name' => $guardName]);
        $permAssignRoles = Permission::firstOrCreate(['name' => 'assign roles', 'guard_name' => $guardName]);

        // Super Admin Specific Permissions
        $permImpersonateUsers = Permission::firstOrCreate(['name' => 'impersonate users', 'guard_name' => $guardName]);
        $permViewSystemLogs = Permission::firstOrCreate(['name' => 'view system_logs', 'guard_name' => $guardName]); // e.g., Telescope, Sentry if integrated
        $permManageSettings = Permission::firstOrCreate(['name' => 'manage settings', 'guard_name' => $guardName]); // Application-wide settings

        // Owner Specific Permissions
        $permViewOwnerDashboard = Permission::firstOrCreate(['name' => 'view owner_dashboard', 'guard_name' => $guardName]);
        $permViewAllDepotData = Permission::firstOrCreate(['name' => 'view all_depot_data', 'guard_name' => $guardName]); // For their owned depots

        // Supervisor Specific Permissions
        $permViewSupervisorDashboard = Permission::firstOrCreate(['name' => 'view supervisor_dashboard', 'guard_name' => $guardName]);


        // Define Roles and Assign Permissions

        // Staff Role
        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => $guardName]);
        $staffRole->givePermissionTo([
            $permViewProfile, $permEditProfile,
            $permViewBikes, // View bikes in their assigned depot
            $permCreateRentals, $permViewRentals, $permEditRentals, // Manage rentals
            $permViewPaxProfiles, // View pax profiles related to their rentals
            // Staff can suggest POIs, but creation/approval is Supervisor+
        ]);

        // Supervisor Role
        $supervisorRole = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => $guardName]);
        $supervisorRole->givePermissionTo([
            $permViewProfile, $permEditProfile,
            $permViewBikes, $permCreateBikes, $permEditBikes, $permDeleteBikes, $permScrapBikes, // Full bike management for their depot
            $permViewRentals, $permCreateRentals, $permEditRentals, $permCancelRentals, // Full rental management
            $permViewPaxProfiles, $permEditPaxProfiles, // Manage pax profiles in their depot
            $permViewDepots, // View their assigned depot
            $permAssignDepotStaff, // Assign staff to their depot
            $permViewPOIs, $permCreatePOIs, $permEditPOIs, $permDeletePOIs, $permApprovePOIs, // Full POI management for their depot
            $permViewShipDepartures, $permManageShipDepartures, // Manage ship schedules
            $permViewSupervisorDashboard,
            $permViewUsers, // View staff in their depot
        ]);

        // Owner Role
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => $guardName]);
        $ownerRole->givePermissionTo([
            $permViewProfile, $permEditProfile,
            $permManageDepots, // Create/manage their depots
            $permAssignDepotStaff, // Assign supervisors/staff to their depots
            $permViewOwnerDashboard,
            $permViewAllDepotData, // View data across all their depots
            $permViewUsers, $permCreateUsers, $permEditUsers, // Manage supervisors/staff users
            // Inherits permissions suitable for managing multiple depots, e.g., viewing aggregated bike/rental data
            // Can be granted more specific permissions as needed by Super Admin
        ]);
        // Owners can typically do what Supervisors can do within their depots, plus manage supervisors.
        // You might choose to give Owner all Supervisor permissions or manage this granularly.
        // For simplicity, let's assume an Owner has Supervisor-level access to their owned depots.
        $ownerRole->syncPermissions($supervisorRole->permissions); // Start with supervisor permissions
        $ownerRole->givePermissionTo([ // Then add owner-specific ones
            $permManageDepots, $permViewOwnerDashboard, $permViewAllDepotData,
            $permCreateUsers, $permEditUsers, $permDeleteUsers, $permAssignRoles // Manage their own staff/supervisors
        ]);


        // Super Admin Role
        // Super Admins get all permissions. This can be done by assigning a wildcard or all defined permissions.
        // For explicitness and control, assign all defined permissions.
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guardName]);
        // $superAdminRole->givePermissionTo(Permission::all()); // Shortcut to give all permissions

        // Or assign specific high-level permissions and let policies handle the rest
        $superAdminRole->givePermissionTo([
            $permImpersonateUsers,
            $permViewSystemLogs,
            $permManageSettings,
            $permAssignRoles, // Can assign any role to any user
            // And typically all permissions that other roles have
            // $permViewProfile, $permEditProfile,
            // $permViewBikes, $permCreateBikes, $permEditBikes, $permDeleteBikes, $permScrapBikes,
            // $permViewRentals, $permCreateRentals, $permEditRentals, $permCancelRentals,
            // $permViewPaxProfiles, $permEditPaxProfiles,
            // $permViewDepots, $permManageDepots, $permAssignDepotStaff,
            // $permViewPOIs, $permCreatePOIs, $permEditPOIs, $permDeletePOIs, $permApprovePOIs,
            // $permViewShipDepartures, $permManageShipDepartures,
            // $permViewUsers, $permCreateUsers, $permEditUsers, $permDeleteUsers,
            // $permViewOwnerDashboard, $permViewAllDepotData,
            // $permViewSupervisorDashboard,
        ]);
        // A common strategy for Super Admin is to check for the role directly in policies
        // or use a Gate::before() callback, effectively granting all access.
        // For now, let's give all permissions explicitly for clarity in the seeder.
        $allPermissions = Permission::all();
        $superAdminRole->syncPermissions($allPermissions);


        // Optional: Create a default Super Admin user (useful for initial setup)
        // Ensure this email is unique or handle potential conflicts
        // $superAdminUser = User::firstOrCreate(
        //     ['email' => 'superadmin@dockandride.com'],
        //     [
        //         'name' => 'Super Admin',
        //         'password' => Hash::make('password'), // Change in production!
        //         'email_verified_at' => now(),
        //     ]
        // );
        // if ($superAdminUser->wasRecentlyCreated || !$superAdminUser->hasRole('Super Admin')) {
        //     $superAdminUser->assignRole($superAdminRole);
        // }
        // Note: UserFactory now assigns a default 'Staff' role if no other role is assigned.
        // If creating users here, ensure their roles are explicitly set to override the factory default if needed.
        // Or, create the user with the factory:
        // User::factory()->superAdmin()->create([
        //     'name' => 'Super Admin User',
        //     'email' => 'superadmin@dockandride.com',
        // ]);

        $this->command->info('Roles and Permissions seeded successfully.');
    }
}
