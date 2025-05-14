# Dock & Ride: Roles, Permissions, and Team Model

This document outlines the user roles, associated permissions, and the team (Depot) management model for the Dock & Ride application, based on the "Developmental Blueprint: Dock & Ride Rebuild (v1.0)".

## 1. Role Definitions & Hierarchy

The application utilizes Spatie Laravel Permission for role-based access control. The following roles are defined:

### 1.1. Super Admin
* **Description:** Highest level of access across the entire system. Not tied to a specific Team/Depot; oversees all entities and business operations.
* **Key Responsibilities & Permissions:**
    * Manage all system data and configurations.
    * Manage all users (Owners, Supervisors, Staff), including creation, updates, and deletion.
    * Assign and manage Spatie roles for all users.
    * Impersonate any other user role (for debugging, support, and system checks).
    * Full CRUD (Create, Read, Update, Delete) access to all models (Bikes, Rentals, PaxProfiles, PointsOfInterest, ShipDepartures, Teams/Depots, etc.).
    * Access to system-wide dashboards and reporting.
    * Manage application settings (e.g., API keys, integrations).

### 1.2. Owner
* **Description:** Represents a business entity or primary account holder who manages one or more "Depots" (represented as Jetstream Teams).
* **Key Responsibilities & Permissions:**
    * View aggregated business activity dashboards (rentals, bike utilization, etc.) for all Depots under their purview.
    * Create, Read, Update, and Delete Depots (Jetstream Teams) that they own or are explicitly granted management rights over by a Super Admin.
    * Manage Supervisor users for their Depots:
        * Invite Supervisors to their Depots (Jetstream Teams).
        * Assign/Update Jetstream team roles for Supervisors within their Depots.
        * Remove Supervisors from their Depots.
    * View all data (Bikes, Rentals, Staff activity, POIs) within the scope of their owned/managed Depots.
    * Cannot typically manage other Owners or Super Admins outside their Depots unless explicitly granted by a Super Admin.
    * May have permissions to manage certain global settings relevant to their business operations if defined.

### 1.3. Supervisor
* **Description:** Manages the day-to-day operations of a specific Depot (Jetstream Team) they are assigned to.
* **Key Responsibilities & Permissions (scoped to their assigned Depot/Team):**
    * Manage assets (Bikes):
        * Create, Read, Update Bikes within their Depot.
        * Mark Bikes for maintenance, scrap/delete Bikes.
        * Track bike status and location within their Depot.
    * Manage bookings/rentals:
        * Oversee check-in/check-out processes.
        * Handle rental modifications, cancellations, and issue resolution within their Depot.
    * Manage Staff users within their Depot:
        * Invite Staff to their Depot (Jetstream Team).
        * Assign/Update Jetstream team roles for Staff within their Depot.
        * Remove Staff from their Depot.
        * (Spatie role for Staff might be automatically assigned or managed by Owner/Super Admin).
    * Manage Points of Interest (POIs) associated with their Depot:
        * Create, Read, Update, Delete POIs linked to their Depot.
        * Approve POIs suggested by Staff.
    * View Depot-specific dashboards and reports.
    * Handle sensitive data (e.g., PaxProfiles) with respect to GDPR for their Depot's operations.

### 1.4. Staff
* **Description:** Lowest internal operational role, assigned to one or more specific Depots (Jetstream Teams).
* **Key Responsibilities & Permissions (scoped to their currently active Depot/Team context):**
    * Manage bookings/rentals:
        * Perform check-in and check-out of bikes.
        * Create new rentals.
        * Update rental details (e.g., notes, expected return time adjustments if permitted).
    * Manage bike status:
        * Log bike maintenance status (e.g., report issues, mark as needing maintenance).
        * Update bike availability.
    * Cannot delete assets (Bikes).
    * Can view data relevant to their tasks within their assigned Depot (e.g., available bikes, active rentals).
    * May suggest new Points of Interest for Supervisor approval.

### 1.5. Bike Renter (Client/PWA User)
* **Description:** External user interacting with the PWA. Not a Spatie role, but represents an authenticated entity via Sanctum.
* **Key Capabilities:**
    * Authenticate via Booking Code/QR Code for a specific rental session.
    * (Future v2+) Optionally register for a persistent PWA account (email/password).
    * View their active rental details.
    * View rental history (if registered PWA user).
    * Interact with map features (view Depots, POIs, get directions).
    * (Future) Engage in chat support.

## 2. Team (Depot) Management Model

The application uses Laravel Jetstream's "Teams" feature to represent physical "Depots" (rental locations).

* **Depots as Teams:** Each physical store, container, or rental hub is a `Team` record in the database. This allows for location-specific management of bikes, staff, and rentals.
* **Owner Manages Multiple Teams:**
    * A User with the "Owner" Spatie role can be the `owner` of multiple Jetstream `Team` records (Depots).
    * Alternatively, an Owner can be a member of multiple Jetstream Teams with a high-level Jetstream team role (e.g., 'admin' or a custom 'depot-manager' Jetstream role). This structure facilitates their "bird's eye view" for aggregated reporting and management across their Depots.
* **Supervisors and Staff Assignment:**
    * **Supervisors:** Users with the "Supervisor" Spatie role are assigned to one or more specific Depots by being added as members to the corresponding Jetstream Team. They will have a Jetstream team role (e.g., 'supervisor', 'editor') that grants them operational permissions within that team, complemented by their Spatie role for broader capabilities.
    * **Staff:** Users with the "Staff" Spatie role are also assigned to one or more Depots by being added as members to Jetstream Teams. Their Jetstream team role (e.g., 'staff-member', 'contributor') will define their specific operational access within that team context.
* **Staff Rotation & Current Team Context:**
    * Staff (User records) can be members of multiple Jetstream Teams (Depots).
    * The Staff Portal UI will leverage Jetstream's feature to allow staff to switch their "current team" context. This enables them to manage operations for the specific Depot they are working at during a shift.
* **Inter-Depot Rentals & Asset Tracking:**
    * `Bikes` will have a `home_team_id` (foreign key to `teams.id`) indicating their primary/default Depot.
    * `Rentals` will store `start_team_id` (the Depot where the rental began) and an `end_team_id` (nullable, the Depot where the bike was returned if different). This allows tracking bike movement and current logical location.
* **Owner's Bird's Eye View:**
    * This will be achieved via custom-built Livewire/Volt dashboards and reports within the Staff Portal.
    * These components will query and aggregate data (rentals, bike statuses, POI activity, etc.) across all Teams associated with the logged-in Owner, respecting their Spatie role and Jetstream team memberships.

## 3. Mapping to Spatie Permissions & Jetstream Team Roles

* **Spatie Roles & Permissions:** Define granular permissions (e.g., `create bike`, `delete bike`, `manage depot settings`, `impersonate user`, `view all depots report`) and assign them to the Spatie Roles (Super Admin, Owner, Supervisor, Staff).
* **Jetstream Team Roles:** Jetstream provides default team roles like 'admin' and 'editor'. We can customize these or add new ones (e.g., 'supervisor', 'staff_member') via `Jetstream::role()`. These Jetstream team roles control a user's abilities *within a specific team*.
    * Example: An "Owner" might have the 'admin' Jetstream role on teams they own. A "Supervisor" might have an 'editor' or custom 'supervisor' Jetstream role on their assigned team.
* **Combined Authorization:** Laravel Policies will be used extensively. Policies will check:
    1.  The user's global Spatie role and permissions (e.g., a Super Admin can do almost anything).
    2.  For team-scoped actions, the user's membership and role *within the specific Jetstream team* (e.g., a Supervisor can only manage bikes within the teams they are assigned to with a 'supervisor' Jetstream role).

This combination provides flexible and robust access control. The `RolesPermissionsSeeder.php` will be responsible for creating these Spatie roles and assigning core permissions during application setup.
