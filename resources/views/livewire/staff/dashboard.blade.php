<div>
    {{-- The layout's <x-slot name="header"> will be populated by $header_title from the render method,
         or directly by the #[Title] attribute or a public $header property in the component.
         The staff-app.blade.php uses {{ $header ?? $title ?? 'Staff Portal' }}
         The #[Title] attribute sets $title.
         You can also pass 'header' key from the render() method:
         return view('livewire.staff.dashboard')->with('header', 'Staff Dashboard Title');
    --}}

    <div class="py-2 md:py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                        <div>
                            <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
                                {{ $greeting }}
                            </h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                You are logged in as: <span class="font-semibold">{{ $userRole }}</span>
                                @if(Auth::user()?->currentTeam)
                                    <span class="mx-1">|</span> Current Depot: <span class="font-semibold">{{ Auth::user()->currentTeam->name }}</span>
                                @endif
                            </p>
                        </div>
                        {{-- Optional: Add a primary action button here if needed --}}
                        {{-- <x-button>New Rental</x-button> --}}
                    </div>
                </div>

                <div class="bg-gray-200/50 dark:bg-gray-800/50 p-6 lg:p-8">
                    @if(empty($widgets))
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                            </svg>
                            <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">No specific actions for your role</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your dashboard is ready. Content relevant to your role will appear here.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach ($widgets as $widget)
                                <div class="bg-white dark:bg-gray-900/70 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 flex flex-col">
                                    <div class="flex items-start">
                                        @if(isset($widget['icon']))
                                            @svg($widget['icon'], 'h-7 w-7 text-indigo-600 dark:text-indigo-400 mr-4 flex-shrink-0')
                                        @endif
                                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 leading-tight">
                                            {{ $widget['title'] }}
                                        </h3>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 flex-grow">
                                        {{ $widget['content'] }}
                                    </p>
                                    @if(isset($widget['link']))
                                        <div class="mt-4">
                                            <a href="{{ $widget['link'] }}"
                                               @if(isset($widget['external']) && $widget['external']) target="_blank" rel="noopener noreferrer" @endif
                                               class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-200">
                                                Go to {{ $widget['title'] }}
                                                <svg class="ml-1 w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
