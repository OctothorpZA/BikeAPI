<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\PointOfInterest;
use App\Models\User; // For type hinting
use App\Models\Team;  // For type hinting
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.staff-app')] #[Title('Point of Interest Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public string $categoryFilter = '';
    public string $approvalFilter = ''; // 'approved', 'pending', 'rejected' (assuming rejected is a state if not just !is_approved)
    public string $activeFilter = ''; // 'active', 'inactive'

    public array $categories;

    public function mount(): void
    {
        // Fetch distinct categories for the filter dropdown
        // Or use the constants from the PointOfInterest model
        $this->categories = [
            PointOfInterest::CATEGORY_DEPOT,
            PointOfInterest::CATEGORY_STAFF_PICK,
            PointOfInterest::CATEGORY_GENERAL,
            // Add any other categories you might have
        ];
        // Alternatively, if you want to dynamically get them from existing POIs:
        // $this->categories = PointOfInterest::query()->select('category')->distinct()->pluck('category')->toArray();
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
     public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }
    public function updatingApprovalFilter(): void
    {
        $this->resetPage();
    }
    public function updatingActiveFilter(): void
    {
        $this->resetPage();
    }


    public function with(): array
    {
        Gate::authorize('viewAny', PointOfInterest::class);

        $user = Auth::user();

        $poisQuery = PointOfInterest::query()
            ->with(['team', 'createdByUser', 'approvedByUser'])
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%')
                        ->orWhere('category', 'like', '%' . $this->search . '%')
                        ->orWhere('address_line_1', 'like', '%' . $this->search . '%')
                        ->orWhere('city', 'like', '%' . $this->search . '%')
                        ->orWhere('state_province', 'like', '%' . $this->search . '%')
                        ->orWhereHas('team', function (Builder $teamQuery) {
                            $teamQuery->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->categoryFilter, function (Builder $query) {
                $query->where('category', $this->categoryFilter);
            })
            ->when($this->approvalFilter !== '', function (Builder $query) {
                // Assuming 'pending' means is_approved = false (or null if nullable)
                // And 'approved' means is_approved = true
                // 'rejected' might imply is_approved = false and perhaps another field, or just not approved.
                // For simplicity, 'pending' = false, 'approved' = true.
                $isApproved = match($this->approvalFilter) {
                    'approved' => true,
                    'pending' => false,
                    // 'rejected' => false, // if rejected is just another form of not approved
                    default => null, // Should not happen with select
                };
                if (!is_null($isApproved)) {
                    $query->where('is_approved', $isApproved);
                }
            })
            ->when($this->activeFilter !== '', function(Builder $query) {
                $isActive = match($this->activeFilter) {
                    'active' => true,
                    'inactive' => false,
                    default => null,
                };
                 if (!is_null($isActive)) {
                    $query->where('is_active', $isActive);
                }
            })
            // Role-based visibility:
            // Super Admins/Owners see all.
            // Supervisors might see all for their team or all non-depot POIs + their depot POIs.
            // Staff might only see approved POIs or those related to their team.
            // This can be refined based on specific business rules.
            // For now, the policy's viewAny and view methods will handle granular access.
            // If a Supervisor should only see POIs linked to their currentTeam or created by their team members:
            ->when($user->hasRole('Supervisor') && $user->currentTeam, function (Builder $query) use ($user) {
                $query->where(function(Builder $q) use ($user) {
                    $q->where('team_id', $user->currentTeam->id) // POIs directly linked to their depot
                      ->orWhereHas('createdByUser', function (Builder $creatorQuery) use ($user) {
                          // POIs created by users who are members of the supervisor's current team
                          $creatorQuery->whereHas('teams', function (Builder $teamMembershipQuery) use ($user) {
                              $teamMembershipQuery->where('teams.id', $user->currentTeam->id);
                          });
                      });
                });
            })
            // Staff might only see active and approved POIs, or those linked to their team.
            ->when($user->hasRole('Staff') && $user->currentTeam, function (Builder $query) use ($user) {
                 $query->where('is_active', true)
                       ->where('is_approved', true)
                       ->where(function(Builder $q) use ($user) {
                            $q->where('team_id', $user->currentTeam->id) // POIs directly linked to their depot
                            ->orWhereNull('team_id'); // Or general POIs not tied to a specific depot
                       });
            })
            ->orderBy($this->sortField, $this->sortDirection);

        return [
            'pois' => $poisQuery->paginate($this->perPage),
            'header_title' => __('Point of Interest Management'),
        ];
    }

    public function createPoi(): void
    {
        // Gate::authorize('create', PointOfInterest::class);
        session()->flash('message', 'Create POI functionality will be implemented here.');
        // return redirect()->route('staff.points-of-interest.create');
    }

    public function editPoi(int $poiId): void
    {
        // $poi = PointOfInterest::findOrFail($poiId);
        // Gate::authorize('update', $poi);
        session()->flash('message', "Edit POI ID: {$poiId} functionality will be implemented here.");
        // return redirect()->route('staff.points-of-interest.edit', $poiId);
    }

    public function approvePoi(int $poiId): void
    {
        // $poi = PointOfInterest::findOrFail($poiId);
        // Gate::authorize('approve', $poi); // Assuming an 'approve' ability in policy
        // $poi->update(['is_approved' => true, 'approved_by_user_id' => Auth::id()]);
        session()->flash('message', "POI ID: {$poiId} approval functionality will be implemented here.");
    }

    public function toggleActive(int $poiId): void
    {
        // $poi = PointOfInterest::findOrFail($poiId);
        // Gate::authorize('update', $poi);
        // $poi->update(['is_active' => !$poi->is_active]);
        session()->flash('message', "POI ID: {$poiId} active toggle functionality will be implemented here.");
    }
}; ?>

<div>
    {{-- Page Header --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $header_title ?? __('Point of Interest Management') }}
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
                                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, category, city..."
                                       class="w-full sm:w-60 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                @if($search)
                                <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    @svg('heroicon-o-x-circle', 'w-5 h-5')
                                </button>
                                @endif
                            </div>
                            <div class="relative">
                                <select wire:model.live="categoryFilter" class="w-full sm:w-auto px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="relative">
                                <select wire:model.live="approvalFilter" class="w-full sm:w-auto px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="">All Approval</option>
                                    <option value="approved">Approved</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                             <div class="relative">
                                <select wire:model.live="activeFilter" class="w-full sm:w-auto px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="">All Active Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        @can('create', App\Models\PointOfInterest::class)
                            <button wire:click="createPoi" class="mt-3 md:mt-0 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition dark:bg-indigo-500 dark:hover:bg-indigo-400 flex-shrink-0">
                                @svg('heroicon-o-plus-circle', 'w-5 h-5 mr-2 -ml-1')
                                {{ __('New POI') }}
                            </button>
                        @endcan
                    </div>

                    {{-- POIs Table --}}
                    <div class="overflow-x-auto bg-white dark:bg-gray-800 shadow-md sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" wire:click="sortBy('name')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Name @if($sortField === 'name') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('category')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Category @if($sortField === 'category') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('team_id')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Team @if($sortField === 'team_id') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('city')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">City @if($sortField === 'city') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('is_approved')" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Approved @if($sortField === 'is_approved') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('is_active')" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Active @if($sortField === 'is_active') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" wire:click="sortBy('created_at')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer">Created @if($sortField === 'created_at') (@if($sortDirection === 'asc')&uarr;@else&darr;@endif)@endif</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($pois as $poi)
                                    <tr wire:key="poi-{{ $poi->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $poi->name }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $poi->category }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $poi->team?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $poi->city ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-center">
                                            @if($poi->is_approved)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">Yes</span>
                                                <span class="text-xs text-gray-400 dark:text-gray-500 block">by {{ $poi->approvedByUser?->name ?? 'System' }}</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100">No</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-center">
                                            @if($poi->is_active)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">Active</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ $poi->created_at->format('d M Y') }}
                                            <span class="text-xs text-gray-400 dark:text-gray-500 block">by {{ $poi->createdByUser?->name ?? 'System' }}</span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium space-x-1">
                                            @can('update', $poi) {{-- Or a more specific 'approve' permission --}}
                                                @if(!$poi->is_approved)
                                                    <button wire:click="approvePoi({{ $poi->id }})" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-200 p-1 rounded hover:bg-green-100 dark:hover:bg-gray-700" title="Approve POI">
                                                        @svg('heroicon-o-check-circle', 'w-5 h-5')
                                                    </button>
                                                @endif
                                                <button wire:click="toggleActive({{ $poi->id }})" class="{{ $poi->is_active ? 'text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-200' : 'text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-200' }} p-1 rounded hover:bg-yellow-100 dark:hover:bg-gray-700" title="{{ $poi->is_active ? 'Deactivate' : 'Activate' }}">
                                                    @if($poi->is_active) @svg('heroicon-o-pause-circle', 'w-5 h-5') @else @svg('heroicon-o-play-circle', 'w-5 h-5') @endif
                                                </button>
                                            @endcan
                                            @can('update', $poi)
                                            <button wire:click="editPoi({{ $poi->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200 p-1 rounded hover:bg-indigo-100 dark:hover:bg-gray-700" title="Edit">
                                                @svg('heroicon-o-pencil-square', 'w-5 h-5')
                                            </button>
                                            @endcan
                                            {{-- Delete might be too sensitive, especially for Depot POIs --}}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center">
                                                @svg('heroicon-o-map', 'w-12 h-12 text-gray-400 dark:text-gray-500 mb-3')
                                                <p class="font-semibold text-lg mb-1">No Points of Interest found.</p>
                                                @if($search || $categoryFilter || $approvalFilter || $activeFilter)
                                                    <p>Try adjusting your search or filter criteria.</p>
                                                @else
                                                    <p>Consider creating a new Point of Interest.</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Links --}}
                    @if ($pois->hasPages())
                        <div class="mt-6">
                            {{ $pois->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
