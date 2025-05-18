<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\ShipDeparture;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

new #[Layout('layouts.staff-app')] #[Title('Ship Departure Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $sortField = 'departure_datetime';
    public string $sortDirection = 'desc';
    public string $activeFilter = ''; // 'active', 'inactive', or '' for all

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

    public function updatingActiveFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        Gate::authorize('viewAny', ShipDeparture::class);

        $shipDeparturesQuery = ShipDeparture::query()
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('ship_name', 'like', '%' . $this->search . '%')
                        ->orWhere('cruise_line_name', 'like', '%' . $this->search . '%')
                        ->orWhere('departure_port_name', 'like', '%' . $this->search . '%')
                        ->orWhere('arrival_port_name', 'like', '%' . $this->search . '%')
                        ->orWhere('voyage_number', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->activeFilter !== '', function (Builder $query) {
                $query->where('is_active', $this->activeFilter === 'active');
            })
            ->orderBy($this->sortField, $this->sortDirection);

        return [
            'shipDepartures' => $shipDeparturesQuery->paginate($this->perPage),
            'header_title' => __('Ship Departure Management'),
        ];
    }

    public function createShipDeparture(): void
    {
        // Gate::authorize('create', ShipDeparture::class);
        session()->flash('message', 'Create Ship Departure functionality will be implemented here.');
        // return redirect()->route('staff.ship-departures.create');
    }

    public function editShipDeparture(int $departureId): void
    {
        // $departure = ShipDeparture::findOrFail($departureId);
        // Gate::authorize('update', $departure);
        session()->flash('message', "Edit Ship Departure ID: {$departureId} functionality will be implemented here.");
        // return redirect()->route('staff.ship-departures.edit', $departureId);
    }

    public function toggleActive(int $departureId): void
    {
        // $departure = ShipDeparture::findOrFail($departureId);
        // Gate::authorize('update', $departure); // Ensure staff can update to toggle active status
        // $departure->update(['is_active' => !$departure->is_active]);
        // session()->flash('message', 'Ship Departure active status updated.');
        session()->flash('message', "Toggle active for Ship Departure ID: {$departureId} functionality will be implemented here.");
    }
}; ?>

<div>
    {{-- Page Header --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $header_title ?? __('Ship Departure Management') }}
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
                    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                        <div class="flex flex-col sm:flex-row flex-wrap gap-3 w-full md:w-auto">
                            <div class="relative">
                                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search ship, cruise line, port, voyage..."
                                       class="w-full sm:w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                @if($search)
                                <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    @svg('heroicon-o-x-circle', 'w-5 h-5')
                                </button>
                                @endif
                            </div>
                            <div class="relative">
                                <select wire:model.live="activeFilter" class="w-full sm:w-auto px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="">All Active Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        @can('create', App\Models\ShipDeparture::class)
                            <button wire:click="createShipDeparture" class="mt-3 md:mt-0 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition dark:bg-indigo-500 dark:hover:bg-indigo-400 flex-shrink-0">
                                @svg('heroicon-o-plus-circle', 'w-5 h-5 mr-2 -ml-1')
                                {{ __('New Ship Departure') }}
                            </button>
                        @endcan
                    </div>

                    {{-- Ship Departures Table --}}
                    <div class="overflow-x-auto bg-white dark:bg-gray-800 shadow-md sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" wire:click="sortBy('ship_name')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Ship Name @if($sortField === 'ship_name') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('cruise_line_name')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Cruise Line @if($sortField === 'cruise_line_name') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('voyage_number')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Voyage# @if($sortField === 'voyage_number') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('departure_port_name')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Departure Port @if($sortField === 'departure_port_name') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('departure_datetime')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Departs @if($sortField === 'departure_datetime') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('arrival_port_name')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Arrival Port @if($sortField === 'arrival_port_name') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('expected_arrival_datetime_at_port')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Arrives @if($sortField === 'expected_arrival_datetime_at_port') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('is_active')" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Active @if($sortField === 'is_active') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($shipDepartures as $departure)
                                    <tr wire:key="departure-{{ $departure->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $departure->ship_name }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $departure->cruise_line_name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $departure->voyage_number ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $departure->departure_port_name }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ Carbon::parse($departure->departure_datetime)->format('d M Y, H:i') }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $departure->arrival_port_name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $departure->expected_arrival_datetime_at_port ? Carbon::parse($departure->expected_arrival_datetime_at_port)->format('d M Y, H:i') : 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-center">
                                            @if($departure->is_active)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">Active</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium space-x-1">
                                            @can('update', $departure)
                                            <button wire:click="toggleActive({{ $departure->id }})" class="{{ $departure->is_active ? 'text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-200' : 'text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-200' }} p-1 rounded hover:bg-yellow-100 dark:hover:bg-gray-700" title="{{ $departure->is_active ? 'Deactivate' : 'Activate' }}">
                                                @if($departure->is_active) @svg('heroicon-o-pause-circle', 'w-5 h-5') @else @svg('heroicon-o-play-circle', 'w-5 h-5') @endif
                                            </button>
                                            <button wire:click="editShipDeparture({{ $departure->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200 p-1 rounded hover:bg-indigo-100 dark:hover:bg-gray-700" title="Edit">
                                                @svg('heroicon-o-pencil-square', 'w-5 h-5')
                                            </button>
                                            @endcan
                                            {{-- Delete might be conditional (e.g., if no rentals are linked) --}}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center">
                                                @svg('heroicon-o-anchor', 'w-12 h-12 text-gray-400 dark:text-gray-500 mb-3')
                                                <p class="font-semibold text-lg mb-1">No Ship Departures found.</p>
                                                @if($search || $activeFilter)
                                                    <p>Try adjusting your search or filter criteria.</p>
                                                @else
                                                    <p>Consider creating a new Ship Departure.</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Links --}}
                    @if ($shipDepartures->hasPages())
                        <div class="mt-6">
                            {{ $shipDepartures->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
