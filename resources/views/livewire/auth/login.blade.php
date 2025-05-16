<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        RateLimiter::clear($this->throttleKey());
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }
        event(new Lockout(request()));
        $seconds = RateLimiter::availableIn($this->throttleKey());
        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

    {{-- Display general session errors (like from Socialite redirects or other issues) --}}
    @if (session('error'))
        <div class="mb-4 font-medium text-sm text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 p-3 rounded-md">
            {{ session('error') }}
        </div>
    @endif
    {{-- Display validation errors --}}
    @if ($errors->any())
        <div class="mb-4 p-3 rounded-md bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700">
            <div class="font-medium text-red-600 dark:text-red-400">{{ __('Whoops! Something went wrong.') }}</div>
            <ul class="mt-3 list-disc list-inside text-sm text-red-600 dark:text-red-500">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    {{-- Session Status (e.g. for password reset link sent) --}}
    @session('status')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            {{ $value }}
        </div>
    @endsession

    <form wire:submit="login" class="flex flex-col gap-6">
        <flux:input wire:model="email" :label="__('Email address')" type="email" required autofocus autocomplete="email" placeholder="email@example.com"/>
        <div class="relative">
            <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="current-password" :placeholder="__('Password')" viewable/>
            @if (Route::has('password.request'))
                <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                    {{ __('Forgot your password?') }}
                </flux:link>
            @endif
        </div>
        <flux:checkbox wire:model="remember" :label="__('Remember me')" />
        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Log in') }}</flux:button>
        </div>

        {{-- Socialite Login Links for STAFF --}}
        <div class="mt-6">
            <div class="relative">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-300 dark:border-gray-600"></div></div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400">{{ __('Or Staff Sign In with') }}</span>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-1 gap-4">
                {{-- Google Login Button for STAFF --}}
                <a href="{{ route('auth.staff.social.redirect', 'google') }}" {{-- <-- CORRECTED ROUTE NAME --}}
                   class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                    <svg class="w-5 h-5 me-2" aria-hidden="true" focusable="false" data-prefix="fab" data-icon="google" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 488 512"><path fill="currentColor" d="M488 261.8C488 403.3 391.1 504 248 504 110.8 504 0 393.2 0 256S110.8 8 248 8c66.8 0 123 24.5 166.3 64.9l-67.5 64.9C258.5 52.6 94.3 116.6 94.3 256c0 86.5 69.1 156.6 153.7 156.6 98.2 0 135-70.4 140.8-106.9H248v-85.3h236.1c2.3 12.7 3.9 24.9 3.9 41.4z"></path></svg>
                    <span>{{ __('Sign in with Google (Staff)') }}</span>
                </a>
            </div>
        </div>
    </form>

    @if (Route::has('register'))
        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600">
            {{ __('Don\'t have an account?') }}
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
    @endif
</div>
