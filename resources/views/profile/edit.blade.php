<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
    </div>

    <x-help-button title="Profile Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Your Profile</h3>
            <p class="mb-3">Manage your account information, password, and preferences.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Sections</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Profile Information</strong> — Update your name and email</li>
                <li><strong>Password</strong> — Change your login password</li>
                <li><strong>Delete Account</strong> — Permanently remove your account</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
