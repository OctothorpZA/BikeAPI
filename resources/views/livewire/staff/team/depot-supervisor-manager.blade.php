<?php

use Livewire\Volt\Component;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Jetstream\Jetstream; // Keep this for Jetstream::findRole() if used in Blade
use Illuminate\Support\Collection;
use App\Actions\Jetstream\UpdateTeamMemberRole;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\InviteTeamMember;
use Illuminate\Validation\Rule;

new #[Layout('layouts.staff-app')] #[Title('Manage Depot Supervisors')] class extends Component {
    public ?Team $selectedDepot = null;
    public Collection $ownedDepots;
    public Collection $depotMembers;
    public array $availableRoles; // Jetstream roles (key => name)

    public string $inviteEmail = '';
    public string $inviteRole = 'admin'; // Default to 'admin' for supervisor

    public function mount(): void
    {
        if (!Gate::allows('access-depot-supervisor-manager')) {
            abort(403, 'You are not authorized to manage depot supervisors.');
        }

        $user = Auth::user();
        if ($user->hasRole('Super Admin')) {
            $this->ownedDepots = Team::where('personal_team', false) // Filter out personal teams
                                    ->orderBy('name')
                                    ->get();
        } elseif ($user->hasRole('Owner')) {
            $this->ownedDepots = $user->ownedTeams()
                                    ->where('personal_team', false) // Filter out personal teams
                                    ->orderBy('name')
                                    ->get();
        } else {
            $this->ownedDepots = collect();
        }

        // Pre-filter in selectDepot to ensure only non-personal teams are processed further
        // if ($this->ownedDepots->isNotEmpty()) {
        //    $this->selectDepot($this->ownedDepots->first()->id); // Autoload first depot
        // }
        $this->depotMembers = collect();

        // Correctly fetch Jetstream roles from the configuration
        // The config('jetstream.roles') returns an array of role definitions.
        // Each definition is an array with 'key', 'name', 'description', 'permissions'.
        $jetstreamRolesConfig = config('jetstream.roles', []);
        $this->availableRoles = collect($jetstreamRolesConfig)->mapWithKeys(function ($role) {
            return [$role['key'] => $role['name']]; // Creates an associative array: 'key' => 'Name'
        })->all();
    }

    public function selectDepot(int $depotId): void
    {
        $depot = $this->ownedDepots->firstWhere('id', $depotId);

        // Ensure the selected depot is an operational (non-personal) team from the filtered list
        if ($depot && !$depot->personal_team) {
            Gate::authorize('update', $depot); // Uses TeamPolicy's update method

            $this->selectedDepot = $depot;
            $this->loadDepotMembers();
            $this->inviteEmail = ''; // Reset invite form
        } else {
            $this->selectedDepot = null;
            $this->depotMembers = collect();
            if ($depotId && !$depot) { // If an ID was passed but not found in ownedDepots (or was personal)
                 session()->flash('error', 'Selected depot is not valid or you are not authorized for it.');
            }
        }
    }

    public function loadDepotMembers(): void
    {
        if ($this->selectedDepot) {
            $this->selectedDepot->load('users', 'teamInvitations');
            $this->depotMembers = $this->selectedDepot->users->sortBy('name');
        }
    }

    public function updateMemberRole(int $userId, string $roleKey): void
    {
        if (!$this->selectedDepot) return;
        Gate::authorize('updateTeamMember', $this->selectedDepot);

        if (!array_key_exists($roleKey, $this->availableRoles)) {
            session()->flash('error', 'Invalid role selected.');
            return;
        }

        app(UpdateTeamMemberRole::class)->update(
            Auth::user(),
            $this->selectedDepot,
            $userId,
            $roleKey
        );
        $this->loadDepotMembers();
        session()->flash('message', 'Team member role updated.');
    }

    public function removeMember(int $userId): void
    {
        if (!$this->selectedDepot) return;
        $userToRemove = User::find($userId);
        if (!$userToRemove) return;

        Gate::authorize('removeTeamMember', [$this->selectedDepot, $userToRemove]);

        app(RemoveTeamMember::class)->remove(
            Auth::user(),
            $this->selectedDepot,
            $userId
        );
        $this->loadDepotMembers();
        session()->flash('message', 'Team member removed.');
    }

    public function inviteSupervisor(): void
    {
        if (!$this->selectedDepot) return;
        Gate::authorize('addTeamMember', $this->selectedDepot);

        $this->validate([
            'inviteEmail' => ['required', 'email'],
            // 'inviteRole' is hardcoded to 'admin' below
        ]);

        app(InviteTeamMember::class)->invite(
            Auth::user(),
            $this->selectedDepot,
            $this->inviteEmail,
            'admin' // Hardcoded to 'admin' (Supervisor role key)
        );

        $this->inviteEmail = '';
        $this->loadDepotMembers();
        session()->flash('message', 'Supervisor invitation sent to ' . $this->inviteEmail);
    }


    public function with(): array
    {
        return [
            'header_title' => __('Manage Depot Supervisors'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $header_title ?? __('Manage Depot Supervisors') }}
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
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Select a Depot to Manage Supervisors</h3>

                    @if($ownedDepots->isEmpty())
                        <p class="text-gray-600 dark:text-gray-400">You do not own or manage any operational depots suitable for supervisor assignment, or you are not authorized.</p>
                    @else
                        <div class="mb-6">
                            <label for="depot_selection" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Depot:</label>
                            <select id="depot_selection" wire:change="selectDepot($event.target.value)"
                                    class="mt-1 block w-full md:w-1/2 pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">-- Select a Depot --</option>
                                @foreach($ownedDepots as $depot)
                                    <option value="{{ $depot->id }}" {{ $selectedDepot && $selectedDepot->id == $depot->id ? 'selected' : '' }}>
                                        {{ $depot->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if($selectedDepot)
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-1">Managing Supervisors for: {{ $selectedDepot->name }}</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Supervisors have the '{{ $this->availableRoles['admin'] ?? 'Administrator' }}' role in this depot.</p>

                            {{-- Invite New Supervisor Form --}}
                            @can('addTeamMember', $selectedDepot)
                                <div class="mb-6 p-4 border dark:border-gray-700 rounded-md bg-gray-50 dark:bg-gray-800/50">
                                    <h5 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-2">Invite New Supervisor</h5>
                                    <div class="flex flex-col sm:flex-row gap-3 items-end">
                                        <div class="flex-grow">
                                            <label for="invite_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email address</label>
                                            <input type="email" wire:model.defer="inviteEmail" id="invite_email" placeholder="email@example.com"
                                                   class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            @error('inviteEmail') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                        </div>
                                        {{-- Role is fixed to 'admin' for supervisors in this context --}}
                                        {{-- <input type="hidden" wire:model="inviteRole" value="admin"> --}}
                                        <button wire:click="inviteSupervisor" wire:loading.attr="disabled"
                                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition dark:bg-blue-500 dark:hover:bg-blue-400">
                                            @svg('heroicon-o-user-plus', 'w-4 h-4 mr-2')
                                            Invite Supervisor
                                        </button>
                                    </div>
                                </div>
                            @endcan

                            {{-- Current Team Members --}}
                            <h5 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-2">Current Depot Members</h5>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Depot Role</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @forelse($depotMembers as $member)
                                            <tr wire:key="member-{{ $member->id }}">
                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $member->name }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $member->email }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-tight font-semibold rounded-full {{ $member->membership->role === 'admin' ? 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200' }}">
                                                        {{ $this->availableRoles[$member->membership->role] ?? $member->membership->role }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                    @if(Auth::user()->id !== $member->id && Gate::check('updateTeamMember', $selectedDepot))
                                                        @if($member->membership->role !== 'admin') {{-- If not already admin (Supervisor) --}}
                                                            <button wire:click="updateMemberRole({{ $member->id }}, 'admin')" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200" title="Make Supervisor">
                                                                Make Supervisor
                                                            </button>
                                                        @elseif(array_key_exists('editor', $this->availableRoles)) {{-- If 'editor' role key exists --}}
                                                            <button wire:click="updateMemberRole({{ $member->id }}, 'editor')" class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-200" title="Demote to Staff (Editor)">
                                                                Make Staff
                                                            </button>
                                                        @endif
                                                    @endif
                                                    @if(Auth::user()->id !== $member->id && Gate::check('removeTeamMember', [$selectedDepot, $member]))
                                                        <button wire:click="removeMember({{ $member->id }})" class="ml-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200" title="Remove from Depot">
                                                            Remove
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">No members found in this depot.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{-- Pending Invitations --}}
                            @if($selectedDepot->teamInvitations->isNotEmpty())
                            <div class="mt-6 pt-4 border-t dark:border-gray-600">
                                <h5 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-2">Pending Invitations</h5>
                                <ul>
                                    @foreach($selectedDepot->teamInvitations as $invitation)
                                    <li class="text-sm text-gray-600 dark:text-gray-400 py-1">
                                        {{ $invitation->email }} (as {{ $this->availableRoles[$invitation->role] ?? $invitation->role }})
                                        {{-- TODO: Add cancel invitation button if needed --}}
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
