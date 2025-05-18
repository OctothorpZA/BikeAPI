<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Laravel') . ' - Staff Portal' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900">
    <div x-data="{ openSidebar: window.innerWidth >= 768 }" @resize.window="if (window.innerWidth >= 768) openSidebar = true; else openSidebar = false;" class="flex h-screen">
        <aside
            x-show="openSidebar"
            @click.away="if (window.innerWidth < 768) openSidebar = false"
            class="fixed inset-y-0 left-0 z-30 w-64 bg-white dark:bg-gray-800 shadow-xl transform transition-transform duration-300 ease-in-out md:relative md:translate-x-0 md:shadow-lg print:hidden"
            :class="{'translate-x-0': openSidebar, '-translate-x-full': !openSidebar && window.innerWidth < 768}"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform -translate-x-full"
            x-transition:enter-end="opacity-100 transform translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-x-0"
            x-transition:leave-end="opacity-0 transform -translate-x-full"
            x-cloak
        >
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between h-16 px-4 border-b dark:border-gray-700 flex-shrink-0">
                    <a href="{{ route('staff.dashboard') }}" class="flex items-center">
                        <x-application-mark class="block h-9 w-auto text-indigo-600 dark:text-indigo-400" />
                        <span class="ml-3 text-lg font-semibold text-gray-700 dark:text-gray-200">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    <button @click="openSidebar = false" class="md:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
                    {{-- Dashboard Link --}}
                    <x-nav-link href="{{ route('staff.dashboard') }}" :active="request()->routeIs('staff.dashboard')">
                        @svg('heroicon-o-home', 'w-5 h-5 mr-2')
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    {{-- Management Section Title --}}
                    <h3 class="px-3 mt-6 mb-2 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        Management
                    </h3>

                    <x-nav-link href="{{ route('staff.bikes.index') }}" :active="request()->routeIs('staff.bikes.*')">
                        @svg('heroicon-o-cube', 'w-5 h-5 mr-2')
                        {{ __('Bike Management') }}
                    </x-nav-link>
                    <x-nav-link href="{{ route('staff.rentals.index') }}" :active="request()->routeIs('staff.rentals.*')">
                        @svg('heroicon-o-clipboard-document-list', 'w-5 h-5 mr-2')
                        {{ __('Rental Management') }}
                    </x-nav-link>
                    <x-nav-link href="{{ route('staff.pax-profiles.index') }}" :active="request()->routeIs('staff.pax-profiles.*')">
                        @svg('heroicon-o-user-group', 'w-5 h-5 mr-2')
                        {{ __('Pax Profiles') }}
                    </x-nav-link>
                    <x-nav-link href="{{ route('staff.points-of-interest.index') }}" :active="request()->routeIs('staff.points-of-interest.*')">
                        @svg('heroicon-o-map-pin', 'w-5 h-5 mr-2')
                        {{ __('Points of Interest') }}
                    </x-nav-link>
                    <x-nav-link href="{{ route('staff.ship-departures.index') }}" :active="request()->routeIs('staff.ship-departures.*')">
                        @svg('heroicon-o-paper-airplane', 'w-5 h-5 mr-2 transform rotate-45')
                        {{ __('Ship Departures') }}
                    </x-nav-link>

                    {{-- Depot & User Administration Section --}}
                    {{-- Use the Spatie permission 'assign spatie roles' for the User Role Manager link --}}
                    {{-- Use the Spatie permission 'assign depot staff' for the Depot Supervisor Manager link --}}
                    @canany(['assign depot staff', 'assign spatie roles'])
                        <h3 class="px-3 mt-6 mb-2 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                            User & Depot Admin
                        </h3>
                        @can('assign depot staff')
                        <x-nav-link href="{{ route('staff.team.depot-supervisor-manager') }}" :active="request()->routeIs('staff.team.depot-supervisor-manager')">
                            @svg('heroicon-o-user-circle', 'w-5 h-5 mr-2')
                            {{ __('Manage Depot Supervisors') }}
                        </x-nav-link>
                        @endcan
                        @can('assign spatie roles') {{-- Permission for managing global Spatie roles --}}
                        <x-nav-link href="{{ route('staff.user.spatie-role-manager') }}" :active="request()->routeIs('staff.user.spatie-role-manager')">
                            @svg('heroicon-o-shield-check', 'w-5 h-5 mr-2')
                            {{ __('Manage User Roles') }}
                        </x-nav-link>
                        @endcan
                    @endcanany
                </nav>

                @auth
                <div class="px-4 py-3 border-t dark:border-gray-700 flex-shrink-0">
                    <div class="flex items-center">
                        @if (Auth::user()->profile_photo_path)
                            <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                        @else
                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-gray-300 dark:bg-gray-600">
                                <span class="text-sm font-medium leading-none text-gray-600 dark:text-gray-300">{{ substr(Auth::user()->name, 0, 1) }}</span>
                            </span>
                        @endif
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white">{{ Auth::user()->name }}</p>
                            @if(Auth::user()->currentTeam)
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ Auth::user()->currentTeam->name }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                @endauth
            </div>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-800 shadow print:hidden">
                <button @click="openSidebar = !openSidebar" class="md:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div class="flex-1 ml-4 md:ml-0"></div>
                <div class="flex items-center space-x-3 sm:space-x-4">
                    @auth
                        @if (Auth::user()->allTeams()->count() > 1)
                            <div class="relative">
                               <x-dropdown align="right" width="60">
                                    <x-slot name="trigger">
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                                {{ Auth::user()->currentTeam->name }}
                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                                </svg>
                                            </button>
                                        </span>
                                    </x-slot>
                                    <x-slot name="content">
                                        <div class="w-60">
                                            <div class="block px-4 py-2 text-xs text-gray-400">{{ __('Manage Team') }}</div>
                                            <x-dropdown-link href="{{ route('teams.show', Auth::user()->currentTeam->id) }}">{{ __('Team Settings') }}</x-dropdown-link>
                                            @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                                                <x-dropdown-link href="{{ route('teams.create') }}">{{ __('Create New Team') }}</x-dropdown-link>
                                            @endcan
                                            @if (Auth::user()->allTeams()->count() > 1)
                                            <div class="border-t border-gray-200 dark:border-gray-600"></div>
                                            <div class="block px-4 py-2 text-xs text-gray-400">{{ __('Switch Teams') }}</div>
                                            @foreach (Auth::user()->allTeams() as $team)
                                                <x-switchable-team :team="$team" component="dropdown-link" />
                                            @endforeach
                                            @endif
                                        </div>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        @endif
                        <button @click="darkMode = !darkMode" title="Toggle dark mode" class="p-2 rounded-full text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            <svg x-show="!darkMode" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                            <svg x-show="darkMode" x-cloak class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path></svg>
                        </button>
                        <div class="relative">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                        <button class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 dark:focus:border-gray-600 transition">
                                            <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                        </button>
                                    @else
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                                {{ Auth::user()->name }}
                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                            </button>
                                        </span>
                                    @endif
                                </x-slot>
                                <x-slot name="content">
                                    <div class="block px-4 py-2 text-xs text-gray-400">{{ __('Manage Account') }}</div>
                                    <x-dropdown-link href="{{ route('profile.show') }}">{{ __('Profile') }}</x-dropdown-link>
                                    @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                        <x-dropdown-link href="{{ route('api-tokens.index') }}">{{ __('API Tokens') }}</x-dropdown-link>
                                    @endif
                                    <div class="border-t border-gray-200 dark:border-gray-600"></div>
                                    <form method="POST" action="{{ route('logout') }}" x-data>
                                        @csrf
                                        <x-dropdown-link href="{{ route('logout') }}" @click.prevent="$root.submit();">{{ __('Log Out') }}</x-dropdown-link>
                                    </form>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endauth
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6 lg:p-8">
                @if (isset($header))
                    <header class="mb-6">
                        <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 dark:text-white">{{ $header }}</h1>
                    </header>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
    @stack('modals')
    @stack('scripts')
</body>
</html>
