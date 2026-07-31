<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? No problem. Enter your Staff ID and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Staff ID -->
        <div>
            <x-input-label for="staff_no" :value="__('Staff ID')" />
            <x-text-input id="staff_no" class="block mt-1 w-full" type="text" name="staff_no" :value="old('staff_no')" required autofocus placeholder="T094" />
            <x-input-error :messages="$errors->get('staff_no')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Send Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
