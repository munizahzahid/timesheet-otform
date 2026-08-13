<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="staff_no" :value="__('Staff ID')" />
            <x-text-input id="staff_no" name="staff_no" type="text" class="mt-1 block w-full" :value="old('staff_no', $user->staff_no)" required autocomplete="username" readonly />
            <x-input-error class="mt-2" :messages="$errors->get('staff_no')" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Staff ID cannot be changed.') }}</p>
        </div>

        <div>
            <x-input-label for="telegram_chat_id" :value="__('Telegram Chat ID')" />
            <x-text-input id="telegram_chat_id" name="telegram_chat_id" type="text" class="mt-1 block w-full" :value="old('telegram_chat_id', $user->telegram_chat_id)" placeholder="e.g., 123456789" autocomplete="off" />
            <x-input-error class="mt-2" :messages="$errors->get('telegram_chat_id')" />
            <p class="mt-1 text-xs text-gray-500">
                {{ __('Enter your Telegram chat ID to receive notifications. Leave blank to disable.') }}
                <a href="https://t.me/userinfobot" target="_blank" class="text-blue-600 hover:text-blue-800">{{ __('How to get your chat ID?') }}</a>
            </p>
        </div>

        <div>
            <button type="button" onclick="testTelegramNotification()" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                {{ __('Test Telegram Notification') }}
            </button>
            <p id="telegram-test-result" class="mt-2 text-xs"></p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>

    <script>
        function testTelegramNotification() {
            const chatId = document.getElementById('telegram_chat_id').value;
            const resultElement = document.getElementById('telegram-test-result');

            if (!chatId) {
                resultElement.textContent = 'Please enter your Telegram chat ID first.';
                resultElement.className = 'mt-2 text-xs text-red-600';
                return;
            }

            resultElement.textContent = 'Sending test message...';
            resultElement.className = 'mt-2 text-xs text-gray-600';

            fetch('{{ route('test.telegram') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ chat_id: chatId })
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`Server returned ${response.status}: ${text.substring(0, 200)}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    resultElement.textContent = 'Test message sent successfully! Check your Telegram.';
                    resultElement.className = 'mt-2 text-xs text-green-600';
                } else {
                    resultElement.textContent = 'Failed: ' + (data.message || 'Unknown error');
                    resultElement.className = 'mt-2 text-xs text-red-600';
                }
            })
            .catch(error => {
                resultElement.textContent = 'Error: ' + error.message;
                resultElement.className = 'mt-2 text-xs text-red-600';
                console.error('Test telegram error:', error);
            });
        }
    </script>
</section>
