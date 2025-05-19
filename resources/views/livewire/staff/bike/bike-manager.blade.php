<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Bike;
use App\Models\Team; // For type hinting and dropdowns
use App\Models\Rental; // For checking active rentals
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule; // For validation rules
use Illuminate\Support\Collection; // Ensure this is Illuminate\Support\Collection

new #[Layout('layouts.staff-app')] #[Title('Bike Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public bool $showBikeModal = false;
    public ?int $editingBikeId = null;
    public string $bike_identifier = '';
    public ?string $nickname = null;
    public string $type = 'standard';
    public string $status = 'available';
    public ?int $team_id = null;
    public ?float $current_latitude = null;
    public ?float $current_longitude = null;
    public ?string $notes = null;

    public bool $showDeleteModal = false;
    public ?int $deletingBikeId = null;

    public Collection $availableTeams;

    public array $bikeTypes = ['standard', 'electric', 'cargo', 'tandem', 'kids'];
    public array $bikeStatuses = ['available', 'rented', 'maintenance', 'decommissioned', 'missing'];


    public function mount(): void
    {
        $user = Auth::user();
        if ($user->hasRole('Super Admin')) {
            $this->availableTeams = Team::where('personal_team', false)->orderBy('name')->get()->toBase();
        } elseif ($user->hasRole('Owner')) {
            $this->availableTeams = $user->ownedTeams()->where('personal_team', false)->orderBy('name')->get()->toBase();
        } elseif ($user->hasRole('Supervisor') && $user->currentTeam) {
            $this->availableTeams = collect([$user->currentTeam]);
            if ($this->availableTeams->count() === 1) {
                $this->team_id = $user->currentTeam->id;
            }
        } else {
            $this->availableTeams = collect();
        }
    }

    protected function rules(): array
    {
        return [
            'bike_identifier' => ['required', 'string', 'max:255', Rule::unique('bikes', 'bike_identifier')->ignore($this->editingBikeId)],
            'nickname' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in($this->bikeTypes)],
            'status' => ['required', 'string', Rule::in($this->bikeStatuses)],
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where(function ($query) {
                if ($this->availableTeams->isNotEmpty()) {
                    $query->whereIn('id', $this->availableTeams->pluck('id')->toArray());
                } else {
                    $query->whereRaw('1 = 0');
                }
            })],
            'current_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'current_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

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

    public function openCreateModal(): void
    {
        Gate::authorize('create', Bike::class);
        $this->resetForm();
        $this->showBikeModal = true;
    }

    public function openEditModal(int $bikeId): void
    {
        $bike = Bike::findOrFail($bikeId);
        Gate::authorize('update', $bike);

        $this->editingBikeId = $bike->id;
        $this->bike_identifier = $bike->bike_identifier;
        $this->nickname = $bike->nickname;
        $this->type = $bike->type;
        $this->status = $bike->status; // Original status for comparison
        $this->team_id = $bike->team_id;
        $this->current_latitude = (float) $bike->current_latitude;
        $this->current_longitude = (float) $bike->current_longitude;
        $this->notes = $bike->notes;

        $this->showBikeModal = true;
    }

    public function saveBike(): void
    {
        $validatedData = $this->validate(); // Use validated data

        $bike = $this->editingBikeId ? Bike::findOrFail($this->editingBikeId) : new Bike();

        if ($this->editingBikeId) {
            Gate::authorize('update', $bike);
        } else {
            Gate::authorize('create', Bike::class);
        }

        // Check if trying to change status of a bike in an active rental
        // Compare the proposed status ($validatedData['status']) with the bike's current status on DB ($bike->status)
        if ($this->editingBikeId && $bike->status !== $validatedData['status']) {
            if (in_array($validatedData['status'], ['available', 'maintenance', 'decommissioned', 'missing'])) {
                if ($bike->rentals()->where('status', 'active')->exists()) {
                    // Add error to Livewire's error bag for direct display, more reliable than session flash here
                    $this->addError('status', 'Cannot change status. Bike is in an active rental.');
                    // $this->status = $bike->status; // Revert the public property to original to reflect on form
                    return; // Stop saving
                }
            }
        }

        $bike->fill($validatedData); // Use validated data for fill
        $bike->current_latitude = $validatedData['current_latitude'] ?: null; // Ensure null if empty
        $bike->current_longitude = $validatedData['current_longitude'] ?: null; // Ensure null if empty

        $bike->save();

        session()->flash('message', $this->editingBikeId ? 'Bike updated successfully.' : 'Bike created successfully.');
        $this->closeBikeModal();
    }

    public function openDeleteModal(int $bikeId): void
    {
        $bike = Bike::findOrFail($bikeId);
        Gate::authorize('delete', $bike);
        $this->deletingBikeId = $bikeId;
        $this->showDeleteModal = true;
    }

    public function deleteBike(): void
    {
        if ($this->deletingBikeId) {
            $bike = Bike::findOrFail($this->deletingBikeId);
            Gate::authorize('delete', $bike);

            if ($bike->rentals()->where('status', 'active')->exists()) {
                session()->flash('error', 'Cannot delete bike. It is currently part of an active rental.');
                $this->closeDeleteModal(); // Close the confirmation modal
                return; // Stop execution
            }
            // Only proceed if the above condition is false
            $bike->delete();
            session()->flash('message', 'Bike deleted successfully.');
        }
        $this->closeDeleteModal(); // Ensure modal is closed
    }

    public function closeBikeModal(): void
    {
        $this->showBikeModal = false;
        $this->resetForm();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingBikeId = null;
    }

    private function resetForm(): void
    {
        $this->editingBikeId = null;
        $this->bike_identifier = '';
        $this->nickname = null;
        $this->type = 'standard';
        $this->status = 'available';
        $user = Auth::user();
        if ($user->hasRole('Supervisor') && $user->currentTeam && $this->availableTeams->count() === 1) {
            $this->team_id = $user->currentTeam->id;
        } else {
            $this->team_id = null;
        }
        $this->current_latitude = null;
        $this->current_longitude = null;
        $this->notes = null;
        $this->resetErrorBag(); // Clear all validation errors
    }

    public function with(): array
    {
        Gate::authorize('viewAny', Bike::class);
        $user = Auth::user();

        $bikesQuery = Bike::query()
            ->with('team')
            ->when($user->currentTeam && $user->hasAnyRole(['Supervisor', 'Staff']), function (Builder $query) use ($user) {
                return $query->where('team_id', $user->currentTeam->id);
            })
            ->when($user->hasRole('Owner') && !$user->hasRole('Super Admin'), function(Builder $query) use ($user) {
                $ownerTeamIds = $user->ownedTeams()->where('personal_team', false)->pluck('id');
                return $query->whereIn('team_id', $ownerTeamIds);
            })
            ->when($user->hasRole('Super Admin'), function(Builder $query){
                $query->whereHas('team', fn(Builder $q) => $q->where('personal_team', false));
            })
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('bike_identifier', 'like', '%' . $this->search . '%')
                        ->orWhere('nickname', 'like', '%' . $this->search . '%')
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
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $header_title ?? __('Bike Management') }}
        </h2>
    </x-slot>

    <div class="py-2 md:py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- General session messages --}}
            @if (session()->has('message'))
                <div class="mb-4 p-4 bg-green-100 dark:bg-green-700 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-100 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="mb-4 p-4 bg-red-100 dark:bg-red-700 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-100 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif
            {{-- Specific error for status update failure, if added via $this->addError() --}}
            @error('status_update_error')
                <div class="mb-4 p-4 bg-red-100 dark:bg-red-700 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-100 rounded relative" role="alert">
                    <span class="block sm:inline">{{ $message }}</span>
                </div>
            @enderror


            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
                        <div class="relative w-full sm:w-auto mb-4 sm:mb-0">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search ID, nickname, type, status, depot..."
                                   class="w-full sm:w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                            @if($search)
                            <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                @svg('heroicon-o-x-circle', 'w-5 h-5')
                            </button>
                            @endif
                        </div>
                        @can('create', App\Models\Bike::class)
                            <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                @svg('heroicon-o-plus-circle', 'w-5 h-5 mr-2 -ml-1')
                                {{ __('Create New Bike') }}
                            </button>
                        @endcan
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th wire:click="sortBy('bike_identifier')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">ID @if($sortField === 'bike_identifier') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('nickname')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Nickname @if($sortField === 'nickname') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('type')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Type @if($sortField === 'type') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('status')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Status @if($sortField === 'status') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('team_id')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Depot @if($sortField === 'team_id') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('current_latitude')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Location @if($sortField === 'current_latitude') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Added @if($sortField === 'created_at') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
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
                                                    @case('maintenance') bg-orange-100 text-orange-800 dark:bg-orange-700 dark:text-orange-100 @break
                                                    @case('decommissioned') bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200 @break
                                                    @case('missing') bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100 @break
                                                    @default bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-100
                                                @endswitch
                                            ">
                                                {{ ucfirst($bike->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $bike->team?->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ ($bike->current_latitude && $bike->current_longitude) ? number_format($bike->current_latitude, 5) . ' / ' . number_format($bike->current_longitude, 5) : 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $bike->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-1">
                                            @can('update', $bike)
                                            <button wire:click="openEditModal({{ $bike->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200 p-1 rounded hover:bg-indigo-100 dark:hover:bg-gray-700" title="Edit">
                                                @svg('heroicon-o-pencil-square', 'w-5 h-5')
                                            </button>
                                            @endcan
                                            @can('delete', $bike)
                                            <button wire:click="openDeleteModal({{ $bike->id }})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200 p-1 rounded hover:bg-red-100 dark:hover:bg-gray-700" title="Delete">
                                                 @svg('heroicon-o-trash', 'w-5 h-5')
                                            </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No bikes found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($bikes->hasPages())
                        <div class="mt-6">
                            {{ $bikes->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Create/Edit Bike Modal --}}
            @if($showBikeModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-data @keydown.escape.window="$wire.closeBikeModal()">
                <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-opacity-80" aria-hidden="true" @click="$wire.closeBikeModal()"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-lg sm:align-middle">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white" id="modal-title">
                            {{ $editingBikeId ? 'Edit Bike' : 'Create New Bike' }}
                        </h3>
                        <form wire:submit.prevent="saveBike">
                            <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                <div class="sm:col-span-3">
                                    <label for="bike_identifier" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bike Identifier <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model.defer="bike_identifier" id="bike_identifier" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    @error('bike_identifier') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="nickname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nickname</label>
                                    <input type="text" wire:model.defer="nickname" id="nickname" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    @error('nickname') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type <span class="text-red-500">*</span></label>
                                    <select wire:model.defer="type" id="type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                        @foreach($bikeTypes as $typeOption)
                                        <option value="{{ $typeOption }}">{{ ucfirst($typeOption) }}</option>
                                        @endforeach
                                    </select>
                                    @error('type') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status <span class="text-red-500">*</span></label>
                                    <select wire:model.defer="status" id="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                        @foreach($bikeStatuses as $statusOption)
                                        <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                                        @endforeach
                                    </select>
                                    @error('status') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                    {{-- Display specific status update error --}}
                                    @error('status_update_error') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-6">
                                    <label for="team_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Depot (Team) <span class="text-red-500">*</span></label>
                                    <select wire:model.defer="team_id" id="team_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" @if($availableTeams->count() <= 1 && Auth::user()->hasRole('Supervisor')) disabled @endif>
                                        <option value="">Select Depot</option>
                                        @foreach($availableTeams as $team)
                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('team_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="current_latitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude</label>
                                    <input type="number" step="any" wire:model.defer="current_latitude" id="current_latitude" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    @error('current_latitude') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="current_longitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude</label>
                                    <input type="number" step="any" wire:model.defer="current_longitude" id="current_longitude" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    @error('current_longitude') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-6">
                                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                                    <textarea wire:model.defer="notes" id="notes" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                                    @error('notes') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="mt-8 sm:flex sm:flex-row-reverse">
                                <button type="submit"
                                        class="inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                    Save Bike
                                </button>
                                <button wire:click="closeBikeModal" type="button"
                                        class="inline-flex justify-center w-full px-4 py-2 mt-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            {{-- Delete Bike Confirmation Modal --}}
            @if($showDeleteModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title-delete" role="dialog" aria-modal="true" x-data @keydown.escape.window="$wire.closeDeleteModal()">
                <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-opacity-80" aria-hidden="true" @click="$wire.closeDeleteModal()"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-lg sm:align-middle">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white" id="modal-title-delete">
                            Delete Bike
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Are you sure you want to delete this bike? This action will soft delete the record. It can be restored later if needed.
                            </p>
                        </div>
                        <div class="mt-6 sm:flex sm:flex-row-reverse">
                            <button wire:click="deleteBike" type="button"
                                    class="inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto dark:bg-red-500 dark:hover:bg-red-400">
                                Delete Bike
                            </button>
                            <button wire:click="closeDeleteModal" type="button"
                                    class="inline-flex justify-center w-full px-4 py-2 mt-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
