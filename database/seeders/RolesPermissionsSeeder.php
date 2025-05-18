<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar; // Required to reset cache

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

        // --- Core Permissions ---
        $permViewProfile = Permission::firstOrCreate(['name' => 'view profile', 'guard_name' => $guardName]);
        $permEditProfile = Permission::firstOrCreate(['name' => 'edit profile', 'guard_name' => $guardName]);

        // Bike Permissions
        $permViewBikes = Permission::firstOrCreate(['name' => 'view bikes', 'guard_name' => $guardName]);
        $permCreateBikes = Permission::firstOrCreate(['name' => 'create bikes', 'guard_name' => $guardName]);
        $permEditBikes = Permission::firstOrCreate(['name' => 'edit bikes', 'guard_name' => $guardName]);
        $permDeleteBikes = Permission::firstOrCreate(['name' => 'delete bikes', 'guard_name' => $guardName]);
        $permScrapBikes = Permission::firstOrCreate(['name' => 'scrap bikes', 'guard_name' => $guardName]); // Typically for Supervisor+

        // Rental Permissions
        $permViewRentals = Permission::firstOrCreate(['name' => 'view rentals', 'guard_name' => $guardName]);
        $permCreateRentals = Permission::firstOrCreate(['name' => 'create rentals', 'guard_name' => $guardName]);
        $permEditRentals = Permission::firstOrCreate(['name' => 'edit rentals', 'guard_name' => $guardName]);
        $permCancelRentals = Permission::firstOrCreate(['name' => 'cancel rentals', 'guard_name' => $guardName]); // Typically for Supervisor+

        // PaxProfile Permissions
        $permViewPaxProfiles = Permission::firstOrCreate(['name' => 'view pax_profiles', 'guard_name' => $guardName]);
        $permEditPaxProfiles = Permission::firstOrCreate(['name' => 'edit pax_profiles', 'guard_name' => $guardName]); // Supervisor+ or if staff can edit certain fields

        // Depot (Team) Permissions (Spatie permissions for controlling access to custom UIs)
        $permViewDepots = Permission::firstOrCreate(['name' => 'view depots', 'guard_name' => $guardName]); // General viewing of depot info
        $permManageDepots = Permission::firstOrCreate(['name' => 'manage depots', 'guard_name' => $guardName]); // For Owners to manage their own depots (settings, etc.)

        // Permission for accessing the UI to assign/manage depot staff roles (Jetstream roles like admin/editor)
        // This is different from managing global Spatie roles.
        $permAssignDepotStaff = Permission::firstOrCreate(['name' => 'assign depot staff', 'guard_name' => $guardName]);


        // POI Permissions
        $permViewPOIs = Permission::firstOrCreate(['name' => 'view pois', 'guard_name' => $guardName]);
        $permCreatePOIs = Permission::firstOrCreate(['name' => 'create pois', 'guard_name' => $guardName]);
        $permEditPOIs = Permission::firstOrCreate(['name' => 'edit pois', 'guard_name' => $guardName]);
        $permDeletePOIs = Permission::firstOrCreate(['name' => 'delete pois', 'guard_name' => $guardName]);
        $permApprovePOIs = Permission::firstOrCreate(['name' => 'approve pois', 'guard_name' => $guardName]);

        // ShipDeparture Permissions
        $permViewShipDepartures = Permission::firstOrCreate(['name' => 'view ship_departures', 'guard_name' => $guardName]);
        $permManageShipDepartures = Permission::firstOrCreate(['name' => 'manage ship_departures', 'guard_name' => $guardName]); // Create, Edit, Delete Ship Departures

        // User Management Permissions (Global Spatie Roles & User accounts)
        $permViewUsers = Permission::firstOrCreate(['name' => 'view users', 'guard_name' => $guardName]); // View list of users
        $permCreateUsers = Permission::firstOrCreate(['name' => 'create users', 'guard_name' => $guardName]); // Create new user accounts
        $permEditUsers = Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => $guardName]); // Edit user details
        $permDeleteUsers = Permission::firstOrCreate(['name' => 'delete users', 'guard_name' => $guardName]); // Delete user accounts
        $permAssignSpatieRoles = Permission::firstOrCreate(['name' => 'assign spatie roles', 'guard_name' => $guardName]); // Assign global Spatie roles (Staff, Supervisor, Owner)

        // System & Admin Permissions
        $permImpersonateUsers = Permission::firstOrCreate(['name' => 'impersonate users', 'guard_name' => $guardName]);
        $permViewSystemLogs = Permission::firstOrCreate(['name' => 'view system_logs', 'guard_name' => $guardName]);
        $permManageSettings = Permission::firstOrCreate(['name' => 'manage settings', 'guard_name' => $guardName]); // Manage general app settings

        // Dashboard View Permissions
        $permViewOwnerDashboard = Permission::firstOrCreate(['name' => 'view owner_dashboard', 'guard_name' => $guardName]);
        $permViewSupervisorDashboard = Permission::firstOrCreate(['name' => 'view supervisor_dashboard', 'guard_name' => $guardName]);
        $permViewAllDepotData = Permission::firstOrCreate(['name' => 'view all_depot_data', 'guard_name' => $guardName]); // For Owners to see data across their depots


        // --- Define Roles ---
        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => $guardName]);
        $supervisorRole = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => $guardName]);
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => $guardName]);
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guardName]);
        $pwaUserRole = Role::firstOrCreate(['name' => 'PWA User', 'guard_name' => $guardName]);

        // --- Assign Permissions to Roles ---

        // Staff Role
        $staffRole->syncPermissions([
            $permViewProfile, $permEditProfile,
            $permViewBikes,
            $permCreateRentals, $permViewRentals, $permEditRentals, // Staff can create, view, edit rentals
            $permViewPaxProfiles, // Staff can view pax profiles
            $permViewShipDepartures,
            $permViewPOIs, // Staff can view POIs
        ]);

        // Supervisor Role
        $supervisorRole->syncPermissions([
            $permViewProfile, $permEditProfile,
            $permViewBikes, $permCreateBikes, $permEditBikes, $permDeleteBikes, $permScrapBikes,
            $permViewRentals, $permCreateRentals, $permEditRentals, $permCancelRentals,
            $permViewPaxProfiles, $permEditPaxProfiles,
            $permViewDepots, // Supervisors can view depot info
            // $permAssignDepotStaff, // Supervisors typically don't assign other staff/supervisors; Owners do.
            $permViewPOIs, $permCreatePOIs, $permEditPOIs, $permDeletePOIs, $permApprovePOIs,
            $permViewShipDepartures, $permManageShipDepartures,
            $permViewSupervisorDashboard,
            $permViewUsers, // Supervisors can view users within their depot context (handled by policy)
        ]);

        // Owner Role
        // Start with supervisor permissions, then add owner-specific ones
        $ownerPermissions = $supervisorRole->permissions->pluck('name')->toArray();
        $ownerPermissions = array_merge($ownerPermissions, [
            $permManageDepots,          // Owners manage their depots
            $permAssignDepotStaff,      // Owners assign staff/supervisors to their depots
            $permViewOwnerDashboard,
            $permViewAllDepotData,
            $permCreateUsers,           // Owners might create staff accounts for their depots
            $permEditUsers,             // Owners might edit staff accounts for their depots
            $permDeleteUsers,           // Owners might delete staff accounts for their depots
            $permAssignSpatieRoles,     // Owners might assign Spatie roles (Staff, Supervisor) to users in their depots
        ]);
        $ownerRole->syncPermissions(array_unique($ownerPermissions));


        // Super Admin Role gets all permissions
        // This ensures that if new permissions are added, Super Admin gets them.
        // Alternatively, assign all permissions explicitly if preferred.
        // $allPermissions = Permission::all();
        // $superAdminRole->syncPermissions($allPermissions);
        // For now, let's grant all permissions explicitly to be clear
        $superAdminRole->syncPermissions(Permission::all());


        // PWA User Role Permissions (minimal for now)
        $pwaUserRole->syncPermissions([
            $permViewProfile, // PWA users can view their own profile (API will enforce ownership)
            // Add other PWA specific permissions like 'create own_rental', 'view own_rentals'
        ]);

        $this->command->info('Roles and Permissions seeded successfully.');
    }
}
