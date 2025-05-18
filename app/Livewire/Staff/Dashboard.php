<?php

namespace App\Livewire\Staff;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout; // Import this
use Livewire\Attributes\Title;  // Optional: For setting the page title

#[Layout('layouts.staff-app')] // Specify the new staff layout
#[Title('Staff Dashboard')]     // Optional: Sets the <title> tag in the layout
class Dashboard extends Component
{
    public string $greeting = '';
    public array $widgets = [];
    public ?string $userRole = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (!$user) {
            // Should be caught by auth middleware, but as a safeguard
            redirect()->route('login');
            return;
        }

        $this->greeting = "Welcome, " . $user->name . "!";
        $this->userRole = $user->roles->isNotEmpty() ? $user->roles->first()->name : 'N/A'; // Display first role

        if ($user->hasRole('Super Admin')) {
            $this->widgets = [
                ['title' => 'System Statistics', 'content' => 'Overview of total users, active rentals, and bikes by status.', 'icon' => 'heroicon-o-chart-pie'],
                ['title' => 'User Management', 'content' => 'Manage all user accounts and roles.', 'link' => '#', 'icon' => 'heroicon-o-users'], // TODO: Add route
                ['title' => 'Depot (Team) Management', 'content' => 'Oversee all depots and their configurations.', 'link' => '#', 'icon' => 'heroicon-o-building-storefront'], // TODO: Add route
                ['title' => 'Developer Tools', 'content' => 'Access Telescope (local only) and Horizon (if applicable).', 'link' => config('telescope.path'), 'icon' => 'heroicon-o-code-bracket-square', 'external' => true],
            ];
        } elseif ($user->hasRole('Owner')) {
            $this->widgets = [
                ['title' => 'My Depots Overview', 'content' => 'Aggregated rental data, bike utilization, and revenue insights across your depots.', 'icon' => 'heroicon-o-briefcase'],
                ['title' => 'Manage My Depots', 'content' => 'View and manage settings for your depots.', 'link' => route('teams.show', $user->currentTeam->id), 'icon' => 'heroicon-o-cog-6-tooth'], // Assumes current team is relevant or provide selector
                ['title' => 'Supervisor Management', 'content' => 'Assign and manage supervisors for your depots.', 'link' => '#', 'icon' => 'heroicon-o-user-group'], // TODO: Add route
            ];
        } elseif ($user->hasRole('Supervisor')) {
            $teamName = $user->currentTeam?->name ?? 'Your Depot';
            $this->widgets = [
                ['title' => $teamName . ' Stats', 'content' => 'Key figures: available, rented, and maintenance bikes. Active rentals.', 'icon' => 'heroicon-o-chart-bar-square'],
                ['title' => 'POI Approvals', 'content' => 'Review and approve/reject Points of Interest awaiting moderation.', 'link' => '#', 'icon' => 'heroicon-o-map-pin'], // TODO: Add route
                ['title' => $teamName . ' Staff Roster', 'content' => 'View staff members assigned to this depot.', 'link' => route('teams.show', $user->currentTeam->id), 'icon' => 'heroicon-o-identification'],
            ];
        } elseif ($user->hasRole('Staff')) {
            $teamName = $user->currentTeam?->name ?? 'Your Depot';
            $this->widgets = [
                ['title' => $teamName . ' Operations', 'content' => 'View upcoming/due rentals and bikes flagged for attention.', 'icon' => 'heroicon-o-clipboard-document-list'],
                ['title' => 'Rental Check-In/Out', 'content' => 'Quick access to bike check-in and check-out processes.', 'link' => '#', 'icon' => 'heroicon-o-qr-code'], // TODO: Add route
                ['title' => 'Bike Maintenance Report', 'content' => 'Flag bikes requiring maintenance.', 'link' => '#', 'icon' => 'heroicon-o-wrench-screwdriver'], // TODO: Add route
            ];
        } else {
             $this->widgets = [
                ['title' => 'Welcome', 'content' => 'Your dashboard is ready. Specific tools for your role will appear here if configured.', 'icon' => 'heroicon-o-sparkles'],
            ];
        }
    }

    // This method provides data to the layout's named slots, like 'header'
    public function render()
    {
        return view('livewire.staff.dashboard', [
            'header_title' => __('Staff Dashboard') // This can be used in the Blade if you prefer
        ]);
    }
}
