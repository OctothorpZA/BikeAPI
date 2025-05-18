<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Rental;
use App\Models\User; // For type hinting
use App\Models\Bike;  // For type hinting
use App\Models\Team;  // For type hinting
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

new #[Layout('layouts.staff-app')] #[Title('Rental Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $sortField = 'start_time';
    public string $sortDirection = 'desc';
    public string $statusFilter = ''; // For filtering by rental status

    // Available statuses for filtering
    public array $rentalStatuses = [
        'pending',
        'confirmed',
        'active',
        'completed',
        'cancelled',
        'no-show',
    ];

    /**
     * Sort the rental list by a given field.
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
        $this->resetPage();
    }

    /**
     * Reset pagination when search or filter changes.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Provide data for the component's view.
     */
    public function with(): array
    {
        Gate::authorize('viewAny', Rental::class);

        $user = Auth::user();
        $currentTeam = $user->currentTeam;

        $rentalsQuery = Rental::query()
            // Corrected eager loading:
            // Removed direct 'user' as it's not defined on Rental model.
            // 'paxProfile.user' loads the user associated with the PaxProfile.
            // 'staffUser' loads the staff member associated via staff_user_id.
            ->with(['paxProfile.user', 'staffUser', 'bike.team', 'startTeam', 'endTeam'])
            ->when($currentTeam && !$user->hasRole(['Super Admin', 'Owner']), function (Builder $query) use ($currentTeam) {
                // Supervisors and Staff only see rentals related to their current depot (either start or end)
                $query->where(function (Builder $q) use ($currentTeam) {
                    $q->where('start_team_id', $currentTeam->id)
                      ->orWhere('end_team_id', $currentTeam->id)
                      ->orWhereHas('bike', function(Builder $bikeQuery) use ($currentTeam) {
                          $bikeQuery->where('team_id', $currentTeam->id);
                      });
                });
            })
            ->when($user->hasRole('Owner'), function(Builder $query) use ($user) {
                // Owners see rentals related to any of their teams
                $ownerTeamIds = $user->allTeams()->pluck('id');
                 $query->where(function (Builder $q) use ($ownerTeamIds) {
                    $q->whereIn('start_team_id', $ownerTeamIds)
                      ->orWhereIn('end_team_id', $ownerTeamIds)
                      ->orWhereHas('bike', function(Builder $bikeQuery) use ($ownerTeamIds) {
                          $bikeQuery->whereIn('team_id', $ownerTeamIds);
                      });
                });
            })
            // Super Admins see all rentals
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('id', 'like', '%' . $this->search . '%')
                        ->orWhere('booking_reference', 'like', '%' . $this->search . '%')
                        // Search by PaxProfile's linked User (customer)
                        ->orWhereHas('paxProfile.user', function (Builder $userQuery) {
                            $userQuery->where('name', 'like', '%' . $this->search . '%')
                                      ->orWhere('email', 'like', '%' . $this->search . '%');
                        })
                        // Search by PaxProfile fields directly (for non-linked pax or booking ref on pax)
                        ->orWhereHas('paxProfile', function (Builder $paxQuery) {
                            $paxQuery->where('first_name', 'like', '%' . $this->search . '%')
                                     ->orWhere('last_name', 'like', '%' . $this->search . '%')
                                     ->orWhere('booking_reference', 'like', '%' . $this->search . '%');
                        })
                        // Optionally, search by staff user if needed
                        ->orWhereHas('staffUser', function (Builder $staffUserQuery) {
                            $staffUserQuery->where('name', 'like', '%' . $this->search . '%')
                                           ->orWhere('email', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('bike', function (Builder $bikeQuery) {
                            $bikeQuery->where('bike_identifier', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->statusFilter, function (Builder $query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection);

        return [
            'rentals' => $rentalsQuery->paginate($this->perPage),
            'header_title' => __('Rental Management'),
        ];
    }

    /**
     * Placeholder for viewing rental details.
     */
    public function viewRental(int $rentalId): void
    {
        // $rental = Rental::findOrFail($rentalId);
        // Gate::authorize('view', $rental);
        // Implement modal/page for viewing details later
        session()->flash('message', "View rental ID: {$rentalId} functionality will be implemented here.");
    }

     /**
     * Placeholder for creating a new rental.
     */
    public function createRental(): void
    {
        // Gate::authorize('create', Rental::class);
        session()->flash('message', "Create rental functionality will be implemented here.");
    }


    /**
     * Placeholder for editing a rental.
     */
    public function editRental(int $rentalId): void
    {
        // $rental = Rental::findOrFail($rentalId);
        // Gate::authorize('update', $rental);
        session()->flash('message', "Edit rental ID: {$rentalId} functionality will be implemented here.");
    }

    /**
     * Placeholder for cancelling a rental.
     */
    public function cancelRental(int $rentalId): void
    {
        // $rental = Rental::findOrFail($rentalId);
        // Gate::authorize('update', $rental); // Or a specific 'cancel' permission
        session()->flash('message', "Cancel rental ID: {$rentalId} functionality will be implemented here.");
    }

    /**
     * Get a Tailwind CSS color class based on rental status.
     */
    public function getStatusColorClass(string $status): string
    {
        return match ($status) {
            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100',
            'confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-100',
            'active' => 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100',
            'completed' => 'bg-purple-100 text-purple-800 dark:bg-purple-700 dark:text-purple-100',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100',
            'no-show' => 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200',
            default => 'bg-gray-200 text-gray-700 dark:bg-gray-500 dark:text-gray-100',
        };
    }
}; ?>

<div>
    {{-- Page Header --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $header_title ?? __('Rental Management') }}
        </h2>
    </x-slot>

    <div class="py-2 md:py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8"> {{-- Changed to max-w-full for wider table --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    {{-- Session Message --}}
                    @if (session()->has('message'))
                        <div class="mb-4 p-4 bg-green-100 dark:bg-green-700 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-100 rounded relative" role="alert">
                            <span class="block sm:inline">{{ session('message') }}</span>
                        </div>
                    @endif

                    {{-- Header Section with Search, Filters, and Create Button --}}
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                        <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
                             <div class="relative w-full sm:w-auto">
                                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search ID, ref, user, bike..."
                                       class="w-full sm:w-64 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                @if($search)
                                <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    @svg('heroicon-o-x-circle', 'w-5 h-5')
                                </button>
                                @endif
                            </div>
                            <div class="relative w-full sm:w-auto">
                                <select wire:model.live="statusFilter" class="w-full sm:w-auto px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="">All Statuses</option>
                                    @foreach($rentalStatuses as $status)
                                        <option value="{{ $status }}">{{ ucfirst(str_replace('-', ' ', $status)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @can('create', App\Models\Rental::class)
                            <button wire:click="createRental" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition dark:bg-indigo-500 dark:hover:bg-indigo-400 flex-shrink-0">
                                @svg('heroicon-o-plus-circle', 'w-5 h-5 mr-2 -ml-1')
                                {{ __('New Rental') }}
                            </button>
                        @endcan
                    </div>

                    {{-- Rentals Table --}}
                    <div class="overflow-x-auto bg-white dark:bg-gray-800 shadow-md sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" wire:click="sortBy('id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        ID @if($sortField === 'id') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('pax_profile_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        User / Pax @if($sortField === 'pax_profile_id') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('bike_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Bike @if($sortField === 'bike_id') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('status')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Status @if($sortField === 'status') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('start_time')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Start Time @if($sortField === 'start_time') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('end_time')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        End Time @if($sortField === 'end_time') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                     <th scope="col" wire:click="sortBy('start_team_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Start Depot @if($sortField === 'start_team_id') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('end_team_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        End Depot @if($sortField === 'end_team_id') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($rentals as $rental)
                                    <tr wire:key="rental-{{ $rental->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $rental->id }} <br>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $rental->booking_reference ?? 'N/A' }}</span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{-- Display PaxProfile name first, then linked User if exists --}}
                                            @if($rental->paxProfile)
                                                {{ $rental->paxProfile->full_name }} (Pax)
                                                @if($rental->paxProfile->user)
                                                    <br><span class="text-xs text-indigo-500 dark:text-indigo-400">(Linked: {{ $rental->paxProfile->user->name }})</span>
                                                @endif
                                            @elseif($rental->user_id && $rental->relationLoaded('user') && $rental->user) {{-- Fallback if direct user_id was intended and loaded --}}
                                                {{ $rental->user->name }} (User)
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ $rental->bike?->bike_identifier ?? 'N/A' }} <br>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $rental->bike?->team?->name ?? 'Unknown Depot' }}</span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $this->getStatusColorClass($rental->status) }}">
                                                {{ ucfirst(str_replace('-', ' ', $rental->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $rental->start_time ? Carbon::parse($rental->start_time)->format('d M Y, H:i') : 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $rental->end_time ? Carbon::parse($rental->end_time)->format('d M Y, H:i') : 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $rental->startTeam?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $rental->endTeam?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium space-x-1">
                                            @can('view', $rental)
                                            <button wire:click="viewRental({{ $rental->id }})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-200 p-1 rounded hover:bg-blue-100 dark:hover:bg-gray-700" title="View Details">
                                                @svg('heroicon-o-eye', 'w-5 h-5')
                                            </button>
                                            @endcan
                                            @can('update', $rental)
                                            <button wire:click="editRental({{ $rental->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200 p-1 rounded hover:bg-indigo-100 dark:hover:bg-gray-700" title="Edit">
                                                @svg('heroicon-o-pencil-square', 'w-5 h-5')
                                            </button>
                                            @if(in_array($rental->status, ['pending', 'confirmed', 'active']))
                                            <button wire:click="cancelRental({{ $rental->id }})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200 p-1 rounded hover:bg-red-100 dark:hover:bg-gray-700" title="Cancel Rental">
                                                @svg('heroicon-o-x-circle', 'w-5 h-5')
                                            </button>
                                            @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center">
                                                @svg('heroicon-o-document-magnifying-glass', 'w-12 h-12 text-gray-400 dark:text-gray-500 mb-3')
                                                <p class="font-semibold text-lg mb-1">No rentals found.</p>
                                                @if($search || $statusFilter)
                                                    <p>Try adjusting your search or filter criteria, or <button wire:click="$set('search', ''); $set('statusFilter', '')" class="text-indigo-600 dark:text-indigo-400 hover:underline">clear all filters</button>.</p>
                                                @else
                                                    <p>Consider creating a new rental if none exist for the current criteria.</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Links --}}
                    @if ($rentals->hasPages())
                        <div class="mt-6">
                            {{ $rentals->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
