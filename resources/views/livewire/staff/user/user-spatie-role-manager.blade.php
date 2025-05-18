<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

new #[Layout('layouts.staff-app')] #[Title('Manage User Spatie Roles')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public Collection $allSpatieRoles; // All assignable roles
    public Collection $staffSpatieRoles; // Staff-specific roles for grouping
    public ?Role $pwaSpatieRole = null;   // PWA User role, if it exists

    public array $userRoles = []; // For managing roles of a specific user in a modal/form

    public ?User $editingUser = null; // User whose roles are being edited
    public bool $showEditRolesModal = false; // Explicit property to control modal visibility

    public function mount(): void
    {
        if (!Gate::allows('access-user-spatie-role-manager')) {
            abort(403, 'You are not authorized to manage user Spatie roles.');
        }

        $query = Role::query()->where('guard_name', 'web');

        if (!Auth::user()->hasRole('Super Admin')) {
            // Non-Super Admins cannot assign/see Super Admin role in the list
            $query->where('name', '!=', 'Super Admin');
        }

        $allRolesFetched = $query->orderBy('name')->get();

        // Separate PWA User role for distinct display
        $this->pwaSpatieRole = $allRolesFetched->firstWhere('name', 'PWA User');
        $this->staffSpatieRoles = $allRolesFetched->reject(function ($role) {
            return $role->name === 'PWA User';
        });

        // For the checkboxes, combine them but ensure PWA User is listed if available
        $this->allSpatieRoles = $this->staffSpatieRoles;
        if ($this->pwaSpatieRole) {
            $this->allSpatieRoles = $this->allSpatieRoles->push($this->pwaSpatieRole);
        }
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

    public function openEditRolesModal(int $userId): void
    {
        $this->editingUser = User::with('roles')->find($userId);
        if (!$this->editingUser) {
            session()->flash('error', 'User not found.');
            $this->showEditRolesModal = false;
            return;
        }

        $currentUser = Auth::user();

        if (!$currentUser->can('assign spatie roles')) {
             session()->flash('error', 'You are not authorized to assign roles.');
             $this->editingUser = null;
             $this->showEditRolesModal = false;
             return;
        }

        // Super Admin can edit anyone, including themselves (if they appear in the list).
        // Non-Super Admins cannot edit Super Admins.
        if ($this->editingUser->hasRole('Super Admin') && !$currentUser->hasRole('Super Admin')) {
            session()->flash('error', 'You cannot edit the roles of a Super Admin.');
            $this->editingUser = null;
            $this->showEditRolesModal = false;
            return;
        }
        // Non-Super Admins cannot edit their own Spatie roles via this panel.
        if ($this->editingUser->id === $currentUser->id && !$currentUser->hasRole('Super Admin')) {
             session()->flash('error', 'You cannot edit your own roles through this panel.');
             $this->editingUser = null;
             $this->showEditRolesModal = false;
             return;
        }

        $this->userRoles = $this->editingUser->roles->pluck('name')->toArray();
        $this->showEditRolesModal = true;
    }

    public function updateUserRoles(): void
    {
        if (!$this->editingUser) {
            session()->flash('error', 'No user selected for role update.');
            return;
        }

        $currentUser = Auth::user();

        if (!$currentUser->can('assign spatie roles')) {
             session()->flash('error', 'You are not authorized to assign roles.');
             $this->closeEditModal();
             return;
        }
        if ($this->editingUser->hasRole('Super Admin') && !$currentUser->hasRole('Super Admin')) {
            session()->flash('error', 'You cannot change roles for a Super Admin.');
            $this->closeEditModal();
            return;
        }
         if ($this->editingUser->id === $currentUser->id && !$currentUser->hasRole('Super Admin')) {
             session()->flash('error', 'You cannot edit your own roles through this panel.');
             $this->closeEditModal();
             return;
        }


        // Get roles assignable by the current user (allSpatieRoles is already filtered in mount)
        $assignableByCurrentUserRoleNames = $this->allSpatieRoles->pluck('name')->toArray();
        $rolesToAssign = array_intersect($this->userRoles, $assignableByCurrentUserRoleNames);

        // If current user is Owner (and not Super Admin), apply Owner-specific restrictions
        if ($currentUser->hasRole('Owner') && !$currentUser->hasRole('Super Admin')) {
            $isMemberOfOwnersTeam = $this->editingUser->teams()->whereIn('teams.id', $currentUser->allTeams()->pluck('id'))->exists();

            if (!$isMemberOfOwnersTeam && !$this->editingUser->is($currentUser)) {
                 session()->flash('error', 'Owners can only manage roles for users within their teams.');
                 $this->closeEditModal();
                 return;
            }

            // Prevent Owner from assigning 'Owner' or 'Super Admin' to others.
            // (Super Admin is already filtered from $assignableByCurrentUserRoleNames for Owners)
            // So, we only need to check for 'Owner' role here.
            if (in_array('Owner', $rolesToAssign) && $this->editingUser->id !== $currentUser->id) {
                 session()->flash('error', "Owners cannot assign the 'Owner' role to other users.");
                 $this->closeEditModal();
                 return;
            }
        }

        $this->editingUser->syncRoles($rolesToAssign);
        session()->flash('message', "Roles for {$this->editingUser->name} updated successfully.");
        $this->closeEditModal();
    }

    public function closeEditModal(): void
    {
        $this->editingUser = null;
        $this->userRoles = [];
        $this->showEditRolesModal = false;
    }


    public function with(): array
    {
        $usersQuery = User::query()
            ->with('roles', 'teams')
            ->whereDoesntHave('roles', function (Builder $query) {
                $query->where('name', 'PWA User'); // Exclude PWA Users from the main list
            })
            ->when($this->search, function (Builder $query) {
                $searchTerm = strtolower($this->search);
                $query->where(function (Builder $q) use ($searchTerm) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                      ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"])
                      ->orWhereHas('roles', function (Builder $roleQuery) use ($searchTerm) {
                          $roleQuery->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                      });
                });
            })
            ->when(Auth::user()->hasRole('Owner') && !Auth::user()->hasRole('Super Admin'), function (Builder $query) {
                $ownerTeamIds = Auth::user()->allTeams()->pluck('id')->toArray();
                if (!empty($ownerTeamIds)) {
                    $query->whereHas('teams', function (Builder $teamQuery) use ($ownerTeamIds) {
                        $teamQuery->whereIn(config('jetstream.teams.foreign_key', 'team_id'), $ownerTeamIds);
                    });
                } else {
                    $query->whereRaw('1 = 0'); // Owner with no teams sees no one (except maybe self if not excluded)
                }
            })
            // Super Admins should not be limited by the Owner's team scope.
            ->orderBy($this->sortField, $this->sortDirection);

        return [
            'users' => $usersQuery->paginate($this->perPage),
            'header_title' => __('Manage User Spatie Roles'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $header_title ?? __('Manage User Spatie Roles') }}
        </h2>
    </x-slot>

    <div class="py-2 md:py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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

            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
                        <div class="relative w-full sm:w-auto mb-4 sm:mb-0">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, email, role..."
                                   class="w-full sm:w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                            @if($search)
                            <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                @svg('heroicon-o-x-circle', 'w-5 h-5')
                            </button>
                            @endif
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th wire:click="sortBy('name')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Name @if($sortField === 'name') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th wire:click="sortBy('email')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Email @if($sortField === 'email') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Spatie Roles</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Member of Depots</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($users as $userToList)
                                    <tr wire:key="user-{{ $userToList->id }}">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $userToList->name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $userToList->email }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            @foreach($userToList->roles->sortBy('name') as $role) {{-- Sort roles by name for consistent display --}}
                                                <span class="px-2 py-1 inline-flex text-xs leading-tight font-semibold rounded-full mr-1 mb-1
                                                    @if($role->name === 'Super Admin') bg-red-200 text-red-800 dark:bg-red-700 dark:text-red-100
                                                    @elseif($role->name === 'Owner') bg-yellow-200 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100
                                                    @elseif($role->name === 'Supervisor') bg-blue-200 text-blue-800 dark:bg-blue-700 dark:text-blue-100
                                                    @elseif($role->name === 'Staff') bg-green-200 text-green-800 dark:bg-green-700 dark:text-green-100
                                                    @elseif($role->name === 'PWA User') bg-purple-200 text-purple-800 dark:bg-purple-700 dark:text-purple-100
                                                    @else bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200
                                                    @endif">
                                                    {{ $role->name }}
                                                </span>
                                            @endforeach
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ $userToList->teams->pluck('name')->join(', ') ?: 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                            @php
                                                $currentUser = Auth::user();
                                                $canEditThisUser = true;
                                                if ($userToList->hasRole('Super Admin') && !$currentUser->hasRole('Super Admin')) {
                                                    $canEditThisUser = false; // Non-SA cannot edit SA
                                                }
                                                // Super Admins CAN edit their own roles if they appear in the list.
                                                // Non-Super Admins CANNOT edit their own Spatie roles via this panel.
                                                if ($userToList->id === $currentUser->id && !$currentUser->hasRole('Super Admin')) {
                                                    $canEditThisUser = false;
                                                }
                                            @endphp
                                            @if($currentUser->can('assign spatie roles') && $canEditThisUser)
                                                <button wire:click="openEditRolesModal({{ $userToList->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200">
                                                    Edit Roles
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">No users found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($users->hasPages())
                        <div class="mt-6">
                            {{ $users->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Edit User Roles Modal --}}
            @if($showEditRolesModal && $editingUser)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-data @keydown.escape.window="$wire.closeEditModal()">
                <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true" @click="$wire.closeEditModal()"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-lg sm:align-middle">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white" id="modal-title">
                            Edit Roles for {{ $editingUser->name }} ({{ $editingUser->email }})
                        </h3>
                        <div class="mt-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Current Spatie Roles:
                                @forelse($editingUser->roles->sortBy('name') as $role)
                                    <span class="font-semibold">{{ $role->name }}@if(!$loop->last), @endif</span>
                                @empty
                                    None
                                @endforelse
                            </p>
                            <fieldset class="mt-4">
                                <legend class="text-base font-medium text-gray-900 dark:text-gray-200">Assign Staff Roles:</legend>
                                <div class="mt-2 space-y-2">
                                    @foreach($staffSpatieRoles as $role) {{-- Iterate over staff roles first --}}
                                        @if($role->name === 'Super Admin' && !Auth::user()->hasRole('Super Admin'))
                                            {{-- Skip showing Super Admin for non-Super Admins --}}
                                        @else
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input id="role-{{ $role->id }}" wire:model.defer="userRoles" type="checkbox" value="{{ $role->name }}"
                                                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="role-{{ $role->id }}" class="font-medium text-gray-700 dark:text-gray-300">{{ $role->name }}</label>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                                @if($pwaSpatieRole)
                                <legend class="text-base font-medium text-gray-900 dark:text-gray-200 mt-4 pt-4 border-t dark:border-gray-700">Assign Public Role:</legend>
                                <div class="mt-2 space-y-2">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="role-{{ $pwaSpatieRole->id }}" wire:model.defer="userRoles" type="checkbox" value="{{ $pwaSpatieRole->name }}"
                                                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="role-{{ $pwaSpatieRole->id }}" class="font-medium text-gray-700 dark:text-gray-300">{{ $pwaSpatieRole->name }}</label>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">(Public user role, not for staff)</p>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </fieldset>
                        </div>
                        <div class="mt-6 sm:flex sm:flex-row-reverse">
                            <button wire:click="updateUserRoles" type="button"
                                    class="inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                Save Roles
                            </button>
                            <button wire:click="closeEditModal" type="button"
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
