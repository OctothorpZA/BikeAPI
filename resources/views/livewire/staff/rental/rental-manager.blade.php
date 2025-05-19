<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Rental;
use App\Models\User;
use App\Models\Bike;
use App\Models\Team;
use App\Models\ShipDeparture;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;

new #[Layout('layouts.staff-app')] #[Title('Rental Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $sortField = 'start_time';
    public string $sortDirection = 'desc';
    public string $statusFilter = '';

    public array $rentalStatuses = [
        'pending', 'confirmed', 'active', 'completed', 'cancelled', 'no-show',
    ];
    public array $paymentStatuses = ['pending', 'paid', 'partially_paid', 'refunded', 'failed', 'disputed'];
    public array $paymentMethods = ['cash', 'card_online', 'card_terminal', 'bank_transfer', 'voucher', 'other'];

    public bool $showRentalModal = false;
    public ?Rental $selectedRental = null;
    public string $modalMode = 'view';

    public ?string $editableNotes = null;
    public ?int $editableShipDepartureId = null;
    public ?string $editableExpectedEndTime = null;
    public ?int $editableEndTeamId = null;
    public ?int $editableStaffUserId = null;
    public ?string $editablePaymentStatus = null;
    public ?string $editablePaymentMethod = null;
    public ?string $editableTransactionId = null;
    public ?float $editableRentalPrice = null;

    public Collection $availableShipDepartures;
    public Collection $availableDepots;
    public Collection $availableStaffUsers;

    public bool $showCancelModal = false;
    public ?Rental $cancellingRental = null;

    public function mount(): void
    {
        $this->availableShipDepartures = ShipDeparture::where('is_active', true)
            ->orderBy('departure_datetime', 'desc')->take(100)->get()->toBase();
        $this->availableDepots = Team::where('personal_team', false)->orderBy('name')->get()->toBase();
        $this->availableStaffUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['Staff', 'Supervisor', 'Owner', 'Super Admin']);
        })->orderBy('name')->get()->toBase();
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

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    protected function rentalEditRules(): array
    {
        $rules = [
            'editableNotes' => ['nullable', 'string', 'max:2000'],
            'editableShipDepartureId' => ['nullable', 'integer', Rule::exists('ship_departures', 'id')],
            'editableEndTeamId' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('personal_team', false)],
            'editableStaffUserId' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'editablePaymentStatus' => ['required', Rule::in($this->paymentStatuses)],
            'editablePaymentMethod' => ['nullable', Rule::in($this->paymentMethods)],
            'editableTransactionId' => ['nullable', 'string', 'max:255'],
            'editableRentalPrice' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ];
        $isShipLinked = $this->editableShipDepartureId !== null && $this->editableShipDepartureId !== '';
        if (!$isShipLinked && $this->selectedRental && !in_array($this->selectedRental->status, ['completed', 'cancelled'])) {
            $startTime = $this->selectedRental->start_time ? $this->selectedRental->start_time->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i');
            $rules['editableExpectedEndTime'] = ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:' . $startTime];
        } else {
            $rules['editableExpectedEndTime'] = ['nullable', 'date_format:Y-m-d\TH:i'];
        }
        return $rules;
    }

    public function openViewModal(int $rentalId): void
    {
        $this->selectedRental = Rental::with([
            'paxProfile.user', 'bike.team', 'staffUser', 'startTeam', 'endTeam', 'shipDeparture'
        ])->find($rentalId);
        if (!$this->selectedRental) {
            session()->flash('error', 'Rental not found.');
            return;
        }
        Gate::authorize('view', $this->selectedRental);
        $this->modalMode = 'view';
        $this->showRentalModal = true;
    }

    public function openEditModal(int $rentalId): void
    {
        $this->selectedRental = Rental::with(['paxProfile.user', 'bike.team', 'staffUser', 'startTeam', 'endTeam', 'shipDeparture'])->find($rentalId);
        if (!$this->selectedRental) {
            session()->flash('error', 'Rental not found.');
            return;
        }
        Gate::authorize('update', $this->selectedRental);
        $this->editableNotes = $this->selectedRental->notes;
        $this->editableShipDepartureId = $this->selectedRental->ship_departure_id;
        $this->editableExpectedEndTime = $this->selectedRental->expected_end_time ? $this->selectedRental->expected_end_time->format('Y-m-d\TH:i') : null;
        $this->editableEndTeamId = $this->selectedRental->end_team_id;
        $this->editableStaffUserId = $this->selectedRental->staff_user_id;
        $this->editablePaymentStatus = $this->selectedRental->payment_status;
        $this->editablePaymentMethod = $this->selectedRental->payment_method;
        $this->editableTransactionId = $this->selectedRental->transaction_id;
        $this->editableRentalPrice = $this->selectedRental->rental_price;
        $this->modalMode = 'edit';
        $this->showRentalModal = true;
    }

    public function saveEditedRental(): void
    {
        if (!$this->selectedRental) {
            session()->flash('error', 'No rental selected for update.');
            return;
        }
        Gate::authorize('update', $this->selectedRental);

        $validatedData = $this->validate($this->rentalEditRules());
        $currentStatus = $this->selectedRental->status;
        $updateData = [];

        $updateData['notes'] = $validatedData['editableNotes'];
        $updateData['payment_status'] = $validatedData['editablePaymentStatus'];
        $updateData['payment_method'] = $validatedData['editablePaymentMethod'] ?: null;
        $updateData['transaction_id'] = $validatedData['editableTransactionId'];

        if (Auth::user()->hasAnyRole(['Super Admin', 'Owner', 'Supervisor'])) {
            $updateData['staff_user_id'] = $validatedData['editableStaffUserId'] ?: null;
        }

        if (!in_array($currentStatus, ['active', 'completed', 'cancelled'])) {
            $updateData['ship_departure_id'] = $validatedData['editableShipDepartureId'] ?: null;
            $updateData['end_team_id'] = $validatedData['editableEndTeamId'] ?: null;
            $updateData['rental_price'] = $validatedData['editableRentalPrice'] === '' ? null : $validatedData['editableRentalPrice'];
            $isShipLinked = $updateData['ship_departure_id'] !== null;
            if (!$isShipLinked && !empty($validatedData['editableExpectedEndTime'])) {
                $updateData['expected_end_time'] = Carbon::parse($validatedData['editableExpectedEndTime']);
            } elseif ($isShipLinked) {
                $updateData['expected_end_time'] = $validatedData['editableExpectedEndTime'] ? Carbon::parse($validatedData['editableExpectedEndTime']) : null;
            } else {
                 $updateData['expected_end_time'] = $this->selectedRental->expected_end_time;
            }
        } elseif (in_array($currentStatus, ['completed', 'cancelled'])) {
            $allowedKeysForFinalStates = ['notes', 'payment_status', 'payment_method', 'transaction_id'];
            if (Auth::user()->hasAnyRole(['Super Admin', 'Owner', 'Supervisor'])){
                $allowedKeysForFinalStates[] = 'staff_user_id';
            }
            $updateData = array_intersect_key($updateData, array_flip($allowedKeysForFinalStates));
        }

        if (empty($updateData)) {
             session()->flash('error', 'No changes detected or no fields are editable for the current rental status.');
             $this->closeRentalModal();
             return;
        }

        $this->selectedRental->update($updateData);
        session()->flash('message', 'Rental ID: ' . $this->selectedRental->id . ' updated successfully.');
        $this->closeRentalModal();
    }

    public function openCancelModal(int $rentalId): void
    {
        $this->cancellingRental = Rental::find($rentalId);
         if (!$this->cancellingRental) {
            session()->flash('error', 'Rental not found.');
            return;
        }
        if (!in_array($this->cancellingRental->status, ['pending', 'confirmed'])) {
            session()->flash('error', 'This rental cannot be cancelled (Status: ' . ucfirst($this->cancellingRental->status) . ').');
            $this->cancellingRental = null;
            return;
        }
        Gate::authorize('update', $this->cancellingRental);
        $this->showCancelModal = true;
    }

    public function confirmCancelRental(): void
    {
        if (!$this->cancellingRental) return;
        Gate::authorize('update', $this->cancellingRental);

        if (!in_array($this->cancellingRental->status, ['pending', 'confirmed'])) {
            session()->flash('error', 'This rental can no longer be cancelled.');
            $this->closeCancelModal();
            return;
        }
        $this->cancellingRental->status = 'cancelled';
        if ($this->cancellingRental->bike && $this->cancellingRental->bike->status === 'rented') {
            $otherActiveRentalsForBike = Rental::where('bike_id', $this->cancellingRental->bike_id)
                                            ->where('status', 'active')
                                            ->where('id', '!=', $this->cancellingRental->id)
                                            ->exists();
            if (!$otherActiveRentalsForBike) {
                $this->cancellingRental->bike->update(['status' => 'available']);
            }
        }
        $this->cancellingRental->save();
        session()->flash('message', 'Rental ID: ' . $this->cancellingRental->id . ' has been cancelled.');
        $this->closeCancelModal();
    }

    public function closeRentalModal(): void
    {
        $this->showRentalModal = false;
        $this->selectedRental = null;
        $this->resetValidation();
        $this->editableNotes = null;
        $this->editableShipDepartureId = null;
        $this->editableExpectedEndTime = null;
        $this->editableEndTeamId = null;
        $this->editableStaffUserId = null;
        $this->editablePaymentStatus = null;
        $this->editablePaymentMethod = null;
        $this->editableTransactionId = null;
        $this->editableRentalPrice = null;
    }
    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
        $this->cancellingRental = null;
    }

    public function createRental(): void
    {
        Gate::authorize('create', Rental::class);
        session()->flash('message', 'Create new rental form/modal will be implemented here.');
    }

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

    public function getPaymentStatusColorClass(string $status): string
    {
        return match ($status) {
            'paid' => 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100',
            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100',
            'partially_paid' => 'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-100',
            'refunded' => 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100',
            'disputed' => 'bg-orange-100 text-orange-800 dark:bg-orange-700 dark:text-orange-100',
            default => 'bg-gray-200 text-gray-700 dark:bg-gray-500 dark:text-gray-100',
        };
    }

    public function getPaymentMethodIcon(string $method = null): string
    {
        // Using Heroicons (outline style) as Blade components
        // Ensure you have blade-heroicons installed or replace with your icon solution
        // Returning HTML, so use {!! ... !!} in Blade
        return match ($method) {
            'cash' => svg('heroicon-o-banknotes', 'w-4 h-4 inline-block mr-1 text-green-600 dark:text-green-400 align-middle')->toHtml(),
            'card_online', 'card_terminal' => svg('heroicon-o-credit-card', 'w-4 h-4 inline-block mr-1 text-blue-600 dark:text-blue-400 align-middle')->toHtml(),
            'bank_transfer' => svg('heroicon-o-building-library', 'w-4 h-4 inline-block mr-1 text-purple-600 dark:text-purple-400 align-middle')->toHtml(),
            'voucher' => svg('heroicon-o-ticket', 'w-4 h-4 inline-block mr-1 text-yellow-600 dark:text-yellow-400 align-middle')->toHtml(),
            default => '',
        };
    }


    public function with(): array
    {
        Gate::authorize('viewAny', Rental::class);
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $rentalsQuery = Rental::query()
            ->with(['paxProfile.user', 'bike.team', 'startTeam', 'endTeam'])
            ->when($currentTeam && !$user->hasRole(['Super Admin', 'Owner']), function (Builder $query) use ($currentTeam) {
                $query->where(function (Builder $q) use ($currentTeam) {
                    $q->where('start_team_id', $currentTeam->id)
                      ->orWhere('end_team_id', $currentTeam->id)
                      ->orWhereHas('bike', function(Builder $bikeQuery) use ($currentTeam) {
                          $bikeQuery->where('team_id', $currentTeam->id);
                      });
                });
            })
            ->when($user->hasRole('Owner') && !$user->hasRole('Super Admin'), function(Builder $query) use ($user) {
                $ownerTeamIds = $user->allTeams()->where('personal_team', false)->pluck('id');
                 $query->where(function (Builder $q) use ($ownerTeamIds) {
                    $q->whereIn('start_team_id', $ownerTeamIds)
                      ->orWhereIn('end_team_id', $ownerTeamIds)
                      ->orWhereHas('bike', function(Builder $bikeQuery) use ($ownerTeamIds) {
                          $bikeQuery->whereIn('team_id', $ownerTeamIds);
                      });
                });
            })
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('id', 'like', '%' . $this->search . '%')
                        ->orWhere('booking_reference', 'like', '%' . $this->search . '%')
                        ->orWhereHas('paxProfile.user', function (Builder $userQuery) {
                            $userQuery->where('name', 'like', '%' . $this->search . '%')
                                      ->orWhere('email', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('paxProfile', function (Builder $paxQuery) {
                            $paxQuery->where('first_name', 'like', '%' . $this->search . '%')
                                     ->orWhere('last_name', 'like', '%' . $this->search . '%');
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
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $header_title ?? __('Rental Management') }}
        </h2>
    </x-slot>

    <div class="py-2 md:py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    @if (session()->has('message'))
                        <div class="mb-4 p-4 bg-green-100 dark:bg-green-700 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-100 rounded" role="alert">
                            <span class="block sm:inline">{{ session('message') }}</span>
                        </div>
                    @endif
                    @if (session()->has('error'))
                        <div class="mb-4 p-4 bg-red-100 dark:bg-red-700 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-100 rounded" role="alert">
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                        {{-- Filters and Search --}}
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

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th wire:click="sortBy('id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">ID / Ref @if($sortField === 'id') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('pax_profile_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">User / Pax @if($sortField === 'pax_profile_id') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('bike_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Bike @if($sortField === 'bike_id') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('status')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Status @if($sortField === 'status') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('start_time')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Start @if($sortField === 'start_time') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('end_time')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">End @if($sortField === 'end_time') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('start_team_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Start Depot @if($sortField === 'start_team_id') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('end_team_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">End Depot @if($sortField === 'end_team_id') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($rentals as $rental)
                                    <tr wire:key="rental-{{ $rental->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $rental->id }} <br>
                                            <span class="text-xs text-gray-500 dark:text-gray-400" title="Booking Reference">{{ $rental->booking_reference ?? 'N/A' }}</span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            @if($rental->paxProfile?->user)
                                                {{ $rental->paxProfile->user->name }} (User)
                                            @elseif($rental->paxProfile)
                                                {{ $rental->paxProfile->full_name }} (Pax)
                                            @else N/A @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ $rental->bike?->bike_identifier ?? 'N/A' }}
                                            <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $rental->bike?->team?->name ?? 'Unknown Depot' }}</span>
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
                                            <button wire:click="openViewModal({{ $rental->id }})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-200 p-1 rounded hover:bg-blue-100 dark:hover:bg-gray-700" title="View Details">
                                                @svg('heroicon-o-eye', 'w-5 h-5')
                                            </button>
                                            @endcan
                                            @can('update', $rental)
                                            <button wire:click="openEditModal({{ $rental->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200 p-1 rounded hover:bg-indigo-100 dark:hover:bg-gray-700" title="Edit Rental">
                                                @svg('heroicon-o-pencil-square', 'w-5 h-5')
                                            </button>
                                                @if(in_array($rental->status, ['pending', 'confirmed']))
                                                <button wire:click="openCancelModal({{ $rental->id }})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200 p-1 rounded hover:bg-red-100 dark:hover:bg-gray-700" title="Cancel Rental">
                                                    @svg('heroicon-o-x-circle', 'w-5 h-5')
                                                </button>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No rentals found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($rentals->hasPages())
                        <div class="mt-6">
                            {{ $rentals->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- View/Edit Rental Modal --}}
            @if($showRentalModal && $selectedRental)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-data @keydown.escape.window="$wire.closeRentalModal()">
                <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-opacity-80" aria-hidden="true" @click="$wire.closeRentalModal()"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div class="inline-block w-full max-w-3xl p-6 my-8 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-lg sm:align-middle">
                        <div class="flex justify-between items-center pb-3 border-b dark:border-gray-700">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                                {{ $modalMode === 'view' ? 'View Rental Details' : 'Edit Rental' }} (ID: {{ $selectedRental->id }})
                            </h3>
                            <button wire:click="closeRentalModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                @svg('heroicon-o-x-mark', 'w-6 h-6')
                            </button>
                        </div>

                        <form wire:submit.prevent="saveEditedRental">
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-6 gap-x-6 gap-y-4 max-h-[70vh] overflow-y-auto pr-2">
                                {{-- Static Info Section (Always Visible at top of modal content) --}}
                                <div class="md:col-span-6 space-y-3 mb-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-md border dark:border-gray-600">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-4">
                                        <div class="sm:col-span-1"><p><strong class="dark:text-gray-300">Booking Ref:</strong> <span class="dark:text-gray-400">{{ $selectedRental->booking_reference ?? 'N/A' }}</span></p></div>
                                        <div class="sm:col-span-2"><p><strong class="dark:text-gray-300">Status:</strong> <span class="px-2 py-0.5 inline-flex text-xs leading-tight font-semibold rounded-full {{ $this->getStatusColorClass($selectedRental->status) }}">{{ ucfirst(str_replace('-', ' ', $selectedRental->status)) }}</span></p></div>
                                    </div>
                                    <div class="pt-2 border-t dark:border-gray-600">
                                        <strong class="dark:text-gray-300 block mb-1">Customer Details:</strong>
                                        <div class="pl-2 dark:text-gray-400 text-sm">
                                            @if($selectedRental->paxProfile)
                                                <p class="font-medium">{{ $selectedRental->paxProfile->full_name }} @if($selectedRental->paxProfile->user) (Linked User: {{ $selectedRental->paxProfile->user->name }}) @endif</p>
                                                <p>Email: {{ $selectedRental->paxProfile->email ?? ($selectedRental->paxProfile->user?->email ?? 'N/A') }}</p>
                                                @if($selectedRental->paxProfile->phone_number)<p>Phone: {{ $selectedRental->paxProfile->phone_number }}</p>@endif
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </div>
                                    <p class="pt-2 border-t dark:border-gray-600"><strong class="dark:text-gray-300">Bike:</strong> <span class="dark:text-gray-400">{{ $selectedRental->bike?->bike_identifier ?? 'N/A' }} ({{ $selectedRental->bike?->type }}) - Current Depot: {{ $selectedRental->bike?->team?->name ?? 'N/A' }}</span></p>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 pt-2 border-t dark:border-gray-600">
                                        <p><strong class="dark:text-gray-300">Start Time:</strong> <span class="dark:text-gray-400">{{ $selectedRental->start_time ? Carbon::parse($selectedRental->start_time)->format('d M Y, H:i T') : 'N/A' }}</span></p>
                                        <p><strong class="dark:text-gray-300">End Time (Actual):</strong> <span class="dark:text-gray-400">{{ $selectedRental->end_time ? Carbon::parse($selectedRental->end_time)->format('d M Y, H:i T') : 'N/A' }}</span></p>
                                    </div>
                                     <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                                        <p><strong class="dark:text-gray-300">Start Depot:</strong> <span class="dark:text-gray-400">{{ $selectedRental->startTeam?->name ?? 'N/A' }}</span></p>
                                        <p><strong class="dark:text-gray-300">End Depot:</strong> <span class="dark:text-gray-400">{{ $selectedRental->endTeam?->name ?? 'N/A' }}</span></p>
                                    </div>

                                    @if($selectedRental->shipDeparture)
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 pt-2 border-t dark:border-gray-600">
                                            <p><strong class="dark:text-gray-300">Ship Departure:</strong> <span class="dark:text-gray-400">{{ $selectedRental->shipDeparture->ship_name }} @ {{ Carbon::parse($selectedRental->shipDeparture->departure_datetime)->format('d M Y, H:i T') }}</span></p>
                                            <p><strong class="text-red-600 dark:text-red-400">Return By Deadline:</strong> <span class="font-bold text-red-600 dark:text-red-400">{{ Carbon::parse($selectedRental->shipDeparture->departure_datetime)->subMinutes(75)->format('d M Y, H:i T') }}</span></p>
                                        </div>
                                    @else
                                        <p class="pt-2 border-t dark:border-gray-600"><strong class="dark:text-gray-300">Ship Departure:</strong> <span class="dark:text-gray-400">N/A</span></p>
                                    @endif

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 pt-2 border-t dark:border-gray-600">
                                        <p><strong class="dark:text-gray-300">Expected End Time:</strong> <span class="dark:text-gray-400">{{ $selectedRental->expected_end_time ? Carbon::parse($selectedRental->expected_end_time)->format('d M Y, H:i T') : 'N/A' }}</span></p>
                                        <p><strong class="dark:text-gray-300">Rental Price:</strong> <span class="dark:text-gray-400">{{ $selectedRental->rental_price !== null ? '$'.number_format($selectedRental->rental_price, 2) : 'N/A' }}</span></p>
                                    </div>
                                    <p class="pt-2 border-t dark:border-gray-600"><strong class="dark:text-gray-300">Processed by Staff:</strong> <span class="dark:text-gray-400">{{ $selectedRental->staffUser?->name ?? 'N/A' }}</span></p>

                                    {{-- Payment Info - Static View part --}}
                                    <div class="pt-2 mt-2 border-t dark:border-gray-600">
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Payment Information</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 text-sm">
                                            <p><strong class="dark:text-gray-400">Status:</strong> <span class="px-1 py-0.5 inline-flex text-xs leading-tight font-semibold rounded-full {{ $this->getPaymentStatusColorClass($selectedRental->payment_status) }}">{{ ucfirst(str_replace('_',' ',$selectedRental->payment_status)) }}</span></p>
                                            <p><strong class="dark:text-gray-400">Method:</strong> <span class="dark:text-gray-500">{!! $this->getPaymentMethodIcon($selectedRental->payment_method) !!} {{ $selectedRental->payment_method ? ucfirst(str_replace('_', ' ', $selectedRental->payment_method)) : 'N/A' }}</span></p>
                                            <p><strong class="dark:text-gray-400">Transaction ID:</strong> <span class="dark:text-gray-500">{{ $selectedRental->transaction_id ?? 'N/A' }}</span></p>
                                        </div>
                                    </div>
                                    @if($selectedRental->notes) {{-- Show notes in view mode only if they exist --}}
                                        <div class="pt-2 mt-2 border-t dark:border-gray-600">
                                            <strong class="dark:text-gray-300">Notes:</strong>
                                            <pre class="whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-700 p-2 rounded-md max-h-40 overflow-y-auto">{{ $selectedRental->notes }}</pre>
                                        </div>
                                    @endif
                                </div>

                                @if($modalMode === 'edit')
                                    <div class="md:col-span-6 mb-2"><h4 class="text-md font-semibold text-gray-800 dark:text-gray-200">Editable Details</h4></div>

                                    <div class="md:col-span-6">
                                        <label for="editableNotes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                                        <textarea wire:model.defer="editableNotes" id="editableNotes" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                                        @error('editableNotes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    @if(!in_array($selectedRental->status, ['active', 'completed', 'cancelled']))
                                        <div class="md:col-span-3">
                                            <label for="editableShipDepartureId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ship Departure</label>
                                            <select wire:model.defer="editableShipDepartureId" id="editableShipDepartureId" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                                <option value="">None</option>
                                                @foreach($availableShipDepartures as $ship)
                                                    <option value="{{ $ship->id }}" @if($ship->id == $editableShipDepartureId) selected @endif>{{ $ship->ship_name }} @ {{ Carbon::parse($ship->departure_datetime)->format('d M Y H:i') }}</option>
                                                @endforeach
                                            </select>
                                            @error('editableShipDepartureId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="md:col-span-3">
                                            <label for="editableExpectedEndTime" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Expected End Time</label>
                                            <input type="datetime-local" wire:model.defer="editableExpectedEndTime" id="editableExpectedEndTime" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" @if($editableShipDepartureId && $availableShipDepartures->firstWhere('id', (int)$editableShipDepartureId)?->expected_arrival_datetime_at_port) disabled title="Typically derived from ship arrival if ship is set" @endif>
                                            @error('editableExpectedEndTime') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="md:col-span-3">
                                            <label for="editableEndTeamId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Depot (Return)</label>
                                            <select wire:model.defer="editableEndTeamId" id="editableEndTeamId" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                                <option value="">Not Set / Same as Start</option>
                                                @foreach($availableDepots as $depot)
                                                    <option value="{{ $depot->id }}" @if($depot->id == $editableEndTeamId) selected @endif>{{ $depot->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('editableEndTeamId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                         <div class="md:col-span-3">
                                            <label for="editableRentalPrice" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Rental Price</label>
                                            <input type="number" step="0.01" wire:model.defer="editableRentalPrice" id="editableRentalPrice" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            @error('editableRentalPrice') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    @else
                                         <div class="md:col-span-6 mt-2">
                                            <p class="text-sm text-yellow-600 dark:text-yellow-400">Operational details (Ship, Times, Depots, Price) cannot be edited for rentals with status '{{ucfirst($selectedRental->status)}}'.</p>
                                         </div>
                                    @endif

                                    <div class="md:col-span-3 {{ (in_array($selectedRental->status, ['active','completed', 'cancelled'])) ? 'mt-4 pt-4 border-t dark:border-gray-700' : '' }}">
                                        <label for="editableStaffUserId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Processed by Staff</label>
                                        <select wire:model.defer="editableStaffUserId" id="editableStaffUserId" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                            @unless(Auth::user()->hasAnyRole(['Super Admin', 'Owner', 'Supervisor'])) disabled title="Only Supervisor or Admin can change processor" @endunless >
                                            <option value="">None</option>
                                            @foreach($availableStaffUsers as $staff)
                                                <option value="{{ $staff->id }}" @if($staff->id == $editableStaffUserId) selected @endif>{{ $staff->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('editableStaffUserId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                @endif {{-- End of $modalMode === 'edit' specific fields --}}

                                {{-- Payment fields - inputs for edit mode --}}
                                @if($modalMode === 'edit')
                                <div class="md:col-span-6 mt-4 pt-4 border-t dark:border-gray-700 grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="payment_status_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Status</label>
                                        <select wire:model.defer="editablePaymentStatus" id="payment_status_edit" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                            @foreach($paymentStatuses as $pStatus) <option value="{{ $pStatus }}">{{ ucfirst(str_replace('_',' ',$pStatus)) }}</option> @endforeach
                                        </select>
                                        @error('editablePaymentStatus') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label for="payment_method_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Method</label>
                                        <select wire:model.defer="editablePaymentMethod" id="payment_method_edit" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                            <option value="">None</option>
                                            @foreach($paymentMethods as $pMethod) <option value="{{ $pMethod }}">{!! $this->getPaymentMethodIcon($pMethod) !!} {{ ucfirst(str_replace('_', ' ', $pMethod)) }}</option> @endforeach
                                        </select>
                                        @error('editablePaymentMethod') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label for="transaction_id_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Transaction ID</label>
                                        <input type="text" wire:model.defer="editableTransactionId" id="transaction_id_edit" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        @error('editableTransactionId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                @endif

                            </div>

                            <div class="mt-6 sm:flex sm:flex-row-reverse">
                                @if($modalMode === 'edit')
                                <button type="submit"
                                        class="inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                    Save Changes
                                </button>
                                @endif
                                <button wire:click="closeRentalModal" type="button"
                                        class="inline-flex justify-center w-full px-4 py-2 mt-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                                    {{ $modalMode === 'view' ? 'Close' : 'Cancel Edit' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            {{-- Cancel Rental Confirmation Modal --}}
            @if($showCancelModal && $cancellingRental)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title-cancel" role="dialog" aria-modal="true" x-data @keydown.escape.window="$wire.closeCancelModal()">
                <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-opacity-80" aria-hidden="true" @click="$wire.closeCancelModal()"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-lg sm:align-middle">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white" id="modal-title-cancel">
                            Confirm Cancel Rental
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Are you sure you want to cancel Rental ID: {{ $cancellingRental->id }} (Ref: {{ $cancellingRental->booking_reference ?? 'N/A' }})?
                                This action cannot be undone easily.
                            </p>
                        </div>
                        <div class="mt-6 sm:flex sm:flex-row-reverse">
                            <button wire:click="confirmCancelRental" type="button"
                                    class="inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 sm:ml-3 sm:w-auto dark:bg-red-500 dark:hover:bg-red-400">
                                Yes, Cancel Rental
                            </button>
                            <button wire:click="closeCancelModal" type="button"
                                    class="inline-flex justify-center w-full px-4 py-2 mt-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                                No, Keep Rental
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
