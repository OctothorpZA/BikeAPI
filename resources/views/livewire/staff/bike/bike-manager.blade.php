<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Bike;
use App\Models\Team; // For type hinting
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder; // Required for query type hint

new #[Layout('layouts.staff-app')] #[Title('Bike Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    // Optional: Properties for modals (create/edit/delete) can be added later
    // public bool $showCreateModal = false;
    // public bool $showEditModal = false;
    // public bool $showDeleteModal = false;
    // public ?Bike $editingBike = null;
    // public ?Bike $deletingBike = null;

    /**
     * Sort the bike list by a given field.
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
        $this->resetPage(); // Reset to first page after sorting
    }

    /**
     * Provide data for the component's view.
     */
    public function with(): array
    {
        // Ensure the user has permission to view any bikes.
        // This uses the BikePolicy's viewAny method.
        Gate::authorize('viewAny', Bike::class);

        $user = Auth::user();
        $currentTeam = $user->currentTeam;

        $bikesQuery = Bike::query()
            ->with('team') // Eager load the team relationship
            ->when($currentTeam && !$user->hasRole(['Super Admin', 'Owner']), function (Builder $query) use ($currentTeam) {
                // Supervisors and Staff only see bikes from their current team
                return $query->where('team_id', $currentTeam->id);
            })
            ->when($user->hasRole('Owner'), function(Builder $query) use ($user) {
                // Owners see bikes from all teams they own or are part of.
                // Assuming an Owner might be part of multiple teams they own.
                // If an Owner strictly owns teams and isn't just a member, adjust logic.
                return $query->whereIn('team_id', $user->allTeams()->pluck('id'));
            })
            // Super Admins see all bikes (no team_id restriction here)
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('bike_identifier', 'like', '%' . $this->search . '%')
                        ->orWhere('nickname', 'like', '%' . $this->search . '%') // Added nickname to search
                        ->orWhere('type', 'like', '%' . $this->search . '%')
                        ->orWhere('status', 'like', '%' . $this->search . '%')
                        ->orWhereHas('team', function (Builder $teamQuery) {
                            $teamQuery->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);

        return [
            'bikes' => $bikesQuery->paginate($this->perPage),
            'header_title' => __('Bike Management'),
        ];
    }

    /**
     * Placeholder for opening a create bike modal.
     */
    public function createBike(): void
    {
        // Gate::authorize('create', Bike::class);
        // $this->showCreateModal = true;
        // Implement modal logic later
        session()->flash('message', 'Create bike functionality will be implemented here.');
    }

    /**
     * Placeholder for opening an edit bike modal.
     */
    public function editBike(int $bikeId): void
    {
        // $bike = Bike::findOrFail($bikeId);
        // Gate::authorize('update', $bike);
        // $this->editingBike = $bike;
        // $this->showEditModal = true;
        // Implement modal logic later
        session()->flash('message', "Edit bike ID: {$bikeId} functionality will be implemented here.");
    }

    /**
     * Placeholder for opening a delete bike confirmation modal.
     */
    public function confirmDeleteBike(int $bikeId): void
    {
        // $bike = Bike::findOrFail($bikeId);
        // Gate::authorize('delete', $bike);
        // $this->deletingBike = $bike;
        // $this->showDeleteModal = true;
        // Implement modal logic later
        session()->flash('message', "Delete bike ID: {$bikeId} functionality will be implemented here.");
    }
}; ?>

<div>
    {{-- Page Header (from component's with() method) --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $header_title ?? __('Bike Management') }}
        </h2>
    </x-slot>

    <div class="py-2 md:py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    {{-- Session Message --}}
                    @if (session()->has('message'))
                        <div class="mb-4 p-4 bg-green-100 dark:bg-green-700 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-100 rounded relative" role="alert">
                            <span class="block sm:inline">{{ session('message') }}</span>
                        </div>
                    @endif

                    {{-- Header Section with Search and Create Button --}}
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
                        <div class="relative w-full sm:w-auto mb-4 sm:mb-0">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search ID, nickname, type, status, depot..."
                                   class="w-full sm:w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                            @if($search)
                            <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                            </button>
                            @endif
                        </div>
                        @can('create', App\Models\Bike::class)
                            <button wire:click="createBike" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                @svg('heroicon-o-plus-circle', 'w-5 h-5 mr-2 -ml-1')
                                {{ __('Create New Bike') }}
                            </button>
                        @endcan
                    </div>

                    {{-- Bikes Table --}}
                    <div class="overflow-x-auto bg-white dark:bg-gray-800 shadow-md sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" wire:click="sortBy('bike_identifier')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        ID
                                        @if($sortField === 'bike_identifier')
                                            @if($sortDirection === 'asc') @svg('heroicon-s-chevron-up', 'w-4 h-4 inline') @else @svg('heroicon-s-chevron-down', 'w-4 h-4 inline') @endif
                                        @endif
                                    </th>
                                     <th scope="col" wire:click="sortBy('nickname')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Nickname
                                        @if($sortField === 'nickname')
                                            @if($sortDirection === 'asc') @svg('heroicon-s-chevron-up', 'w-4 h-4 inline') @else @svg('heroicon-s-chevron-down', 'w-4 h-4 inline') @endif
                                        @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('type')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Type
                                        @if($sortField === 'type')
                                            @if($sortDirection === 'asc') @svg('heroicon-s-chevron-up', 'w-4 h-4 inline') @else @svg('heroicon-s-chevron-down', 'w-4 h-4 inline') @endif
                                        @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('status')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Status
                                        @if($sortField === 'status')
                                            @if($sortDirection === 'asc') @svg('heroicon-s-chevron-up', 'w-4 h-4 inline') @else @svg('heroicon-s-chevron-down', 'w-4 h-4 inline') @endif
                                        @endif
                                    </th>
                                     <th scope="col" wire:click="sortBy('team_id')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Depot
                                        @if($sortField === 'team_id')
                                            @if($sortDirection === 'asc') @svg('heroicon-s-chevron-up', 'w-4 h-4 inline') @else @svg('heroicon-s-chevron-down', 'w-4 h-4 inline') @endif
                                        @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('current_latitude')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Location (Lat/Lng)
                                        @if($sortField === 'current_latitude') {{-- Sorting by latitude, longitude would be similar --}}
                                            @if($sortDirection === 'asc') @svg('heroicon-s-chevron-up', 'w-4 h-4 inline') @else @svg('heroicon-s-chevron-down', 'w-4 h-4 inline') @endif
                                        @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Added
                                        @if($sortField === 'created_at')
                                            @if($sortDirection === 'asc') @svg('heroicon-s-chevron-up', 'w-4 h-4 inline') @else @svg('heroicon-s-chevron-down', 'w-4 h-4 inline') @endif
                                        @endif
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($bikes as $bike)
                                    <tr wire:key="bike-{{ $bike->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $bike->bike_identifier }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $bike->nickname ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $bike->type }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                @switch($bike->status)
                                                    @case('available') bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100 @break
                                                    @case('rented') bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100 @break
                                                    @case('maintenance') bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100 @break
                                                    @case('decommissioned') bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200 @break
                                                    @default bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-100
                                                @endswitch
                                            ">
                                                {{ ucfirst($bike->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $bike->team?->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ $bike->current_latitude ? number_format($bike->current_latitude, 5) : 'N/A' }} /
                                            {{ $bike->current_longitude ? number_format($bike->current_longitude, 5) : 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $bike->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            @can('update', $bike)
                                            <button wire:click="editBike({{ $bike->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200" title="Edit">
                                                @svg('heroicon-o-pencil-square', 'w-5 h-5')
                                            </button>
                                            @endcan
                                            @can('delete', $bike)
                                            <button wire:click="confirmDeleteBike({{ $bike->id }})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200" title="Delete">
                                                 @svg('heroicon-o-trash', 'w-5 h-5')
                                            </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400"> {{-- Incremented colspan --}}
                                            <div class="flex flex-col items-center">
                                                @svg('heroicon-o-cube-transparent', 'w-12 h-12 text-gray-400 dark:text-gray-500 mb-3') {{-- Changed icon --}}
                                                <p class="font-semibold text-lg mb-1">No bikes found.</p>
                                                @if($search)
                                                    <p>Try adjusting your search criteria or <button wire:click="$set('search', '')" class="text-indigo-600 dark:text-indigo-400 hover:underline">clear the search</button>.</p>
                                                @else
                                                    <p>Consider adding a new bike if none exist for the current depot/filter.</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Links --}}
                    @if ($bikes->hasPages())
                        <div class="mt-6">
                            {{ $bikes->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Placeholders (to be implemented later) --}}
    {{-- @if($showCreateModal) ... @endif --}}
    {{-- @if($showEditModal && $editingBike) ... @endif --}}
    {{-- @if($showDeleteModal && $deletingBike) ... @endif --}}
</div>
