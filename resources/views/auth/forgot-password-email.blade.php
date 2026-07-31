<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('No email found for your Staff ID. Please enter your email address and we will send you a password reset link.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email.send') }}">
        @csrf

        <!-- Hidden Staff ID -->
        <input type="hidden" name="staff_no" value="{{ $staff_no }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Send Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
