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

    <x-help-button title="Bantuan Profil">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Profil Anda</h3>
            <p class="mb-3">Urus maklumat akaun, kata laluan, dan keutamaan anda.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Bahagian</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Maklumat Profil</strong> — Kemaskini nama dan emel anda</li>
                <li><strong>Kata Laluan</strong> — Tukar kata laluan log masuk anda</li>
                <li><strong>Padam Akaun</strong> — Buang akaun anda secara kekal</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
