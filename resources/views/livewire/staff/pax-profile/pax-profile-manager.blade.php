<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\PaxProfile;
use App\Models\User; // For type hinting
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.staff-app')] #[Title('Pax Profile Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $sortField = 'last_name';
    public string $sortDirection = 'asc';
    public string $typeFilter = ''; // 'cruise', 'local', 'linked_user'

    /**
     * Sort the list by a given field.
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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Provide data for the component's view.
     */
    public function with(): array
    {
        Gate::authorize('viewAny', PaxProfile::class);

        $loggedInUser = Auth::user(); // Currently logged-in staff member

        $paxProfilesQuery = PaxProfile::query()
            ->with(['user']) // Eager load linked User. 'team' relationship does not exist on PaxProfile.
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('phone_number', 'like', '%' . $this->search . '%')
                        // booking_reference is not on PaxProfile model, it's on Rental.
                        // ->orWhere('booking_reference', 'like', '%' . $this->search . '%')
                        ->orWhere('passport_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('user', function (Builder $userQuery) { // Search linked user
                            $userQuery->where('name', 'like', '%' . $this->search . '%')
                                      ->orWhere('email', 'like', '%' . $this->search . '%');
                        })
                        // Cannot search by team name directly on PaxProfile as relationship doesn't exist.
                        // ->orWhereHas('team', function (Builder $teamQuery) {
                        //     $teamQuery->where('name', 'like', '%' . $this->search . '%');
                        // })
                        // If searching by booking_reference is critical, search via rentals:
                        ->orWhereHas('rentals', function (Builder $rentalQuery) {
                            $rentalQuery->where('booking_reference', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->typeFilter, function(Builder $query) {
                match ($this->typeFilter) {
                    // Heuristic for cruise pax: has rentals with a booking_reference, and not linked to a system user.
                    'cruise' => $query->whereHas('rentals', fn(Builder $r) => $r->whereNotNull('booking_reference'))
                                       ->whereNull('user_id'),
                    // Heuristic for local: no rentals with booking_reference (or no rentals at all), and not linked to a system user.
                    'local' => $query->where(function(Builder $q) {
                                    $q->whereDoesntHave('rentals', fn(Builder $r) => $r->whereNotNull('booking_reference'))
                                      ->orWhereDoesntHave('rentals');
                                })->whereNull('user_id'),
                    'linked_user' => $query->whereNotNull('user_id'),
                    default => null,
                };
            })
            ->when(!$loggedInUser->hasRole(['Super Admin', 'Owner']) && $loggedInUser->currentTeam, function (Builder $query) use ($loggedInUser) {
                // If non-admin staff should only see PaxProfiles related to their team's rentals:
                $query->whereHas('rentals', function (Builder $rentalQuery) use ($loggedInUser) {
                    $rentalQuery->where('start_team_id', $loggedInUser->currentTeam->id)
                                ->orWhere('end_team_id', $loggedInUser->currentTeam->id);
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);

        return [
            'paxProfiles' => $paxProfilesQuery->paginate($this->perPage),
            'header_title' => __('Pax Profile Management'),
        ];
    }

    public function createPaxProfile(): void
    {
        session()->flash('message', 'Create Pax Profile functionality will be implemented here.');
    }

    public function editPaxProfile(int $paxProfileId): void
    {
        session()->flash('message', "Edit Pax Profile ID: {$paxProfileId} functionality will be implemented here.");
    }

    public function viewPaxProfile(int $paxProfileId): void
    {
        session()->flash('message', "View Pax Profile ID: {$paxProfileId} functionality will be implemented here.");
    }
}; ?>

<div>
    {{-- Page Header --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $header_title ?? __('Pax Profile Management') }}
        </h2>
    </x-slot>

    <div class="py-2 md:py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
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
                                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, email, phone, booking ref..."
                                       class="w-full sm:w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                @if($search)
                                <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    @svg('heroicon-o-x-circle', 'w-5 h-5')
                                </button>
                                @endif
                            </div>
                            <div class="relative w-full sm:w-auto">
                                <select wire:model.live="typeFilter" class="w-full sm:w-auto px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="">All Types</option>
                                    <option value="cruise">Cruise Pax (has booking ref)</option>
                                    <option value="local">Local (Not Linked to User, no booking ref)</option>
                                    <option value="linked_user">Linked to User Account</option>
                                </select>
                            </div>
                        </div>
                        @can('create', App\Models\PaxProfile::class)
                            <button wire:click="createPaxProfile" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition dark:bg-indigo-500 dark:hover:bg-indigo-400 flex-shrink-0">
                                @svg('heroicon-o-plus-circle', 'w-5 h-5 mr-2 -ml-1')
                                {{ __('New Pax Profile') }}
                            </button>
                        @endcan
                    </div>

                    {{-- Pax Profiles Table --}}
                    <div class="overflow-x-auto bg-white dark:bg-gray-800 shadow-md sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" wire:click="sortBy('id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        ID @if($sortField === 'id') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('last_name')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Name @if($sortField === 'last_name') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('email')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Email @if($sortField === 'email') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    <th scope="col" wire:click="sortBy('phone_number')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Phone @if($sortField === 'phone_number') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    {{-- Booking Ref is on Rental, not directly PaxProfile. Removed direct sort. --}}
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Booking Ref (via Rental)
                                    </th>
                                    <th scope="col" wire:click="sortBy('user_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                                        Linked User @if($sortField === 'user_id') (@if($sortDirection === 'asc') &uarr; @else &darr; @endif) @endif
                                    </th>
                                    {{-- Associated Team is not a direct relationship. Removed direct sort. --}}
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Team (via Rentals)
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($paxProfiles as $paxProfile)
                                    <tr wire:key="pax-{{ $paxProfile->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $paxProfile->id }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $paxProfile->full_name }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $paxProfile->email ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $paxProfile->phone_number ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{-- Display booking_reference from the first associated rental, if any --}}
                                            {{ $paxProfile->rentals->firstWhere('booking_reference')?->booking_reference ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            @if($paxProfile->user)
                                                <span class="text-green-600 dark:text-green-400 font-semibold">Yes</span> ({{ $paxProfile->user->name }})
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500">No</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{-- Display team from the first associated rental, if any --}}
                                            {{ $paxProfile->rentals->first()?->startTeam?->name ?? ($paxProfile->rentals->first()?->endTeam?->name ?? 'N/A') }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium space-x-1">
                                            @can('view', $paxProfile)
                                            <button wire:click="viewPaxProfile({{ $paxProfile->id }})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-200 p-1 rounded hover:bg-blue-100 dark:hover:bg-gray-700" title="View Details">
                                                @svg('heroicon-o-eye', 'w-5 h-5')
                                            </button>
                                            @endcan
                                            @can('update', $paxProfile)
                                            <button wire:click="editPaxProfile({{ $paxProfile->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200 p-1 rounded hover:bg-indigo-100 dark:hover:bg-gray-700" title="Edit">
                                                @svg('heroicon-o-pencil-square', 'w-5 h-5')
                                            </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center">
                                                @svg('heroicon-o-users', 'w-12 h-12 text-gray-400 dark:text-gray-500 mb-3')
                                                <p class="font-semibold text-lg mb-1">No Pax Profiles found.</p>
                                                @if($search || $typeFilter)
                                                    <p>Try adjusting your search or filter criteria, or <button wire:click="$set('search', ''); $set('typeFilter', '')" class="text-indigo-600 dark:text-indigo-400 hover:underline">clear all filters</button>.</p>
                                                @else
                                                    <p>Consider creating a new Pax Profile.</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Links --}}
                    @if ($paxProfiles->hasPages())
                        <div class="mt-6">
                            {{ $paxProfiles->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
