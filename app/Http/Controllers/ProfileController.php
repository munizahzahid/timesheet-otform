<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Test Telegram notification.
     */
    public function testTelegram(Request $request): JsonResponse
    {
        $request->validate([
            'chat_id' => ['required', 'string', 'regex:/^-?\d+$/'],
        ]);

        $telegram = new TelegramNotificationService();
        if (!$telegram->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram bot token not configured.',
                'debug' => [
                    'env_file_exists' => file_exists(base_path('.env')),
                    'env_token' => env('TELEGRAM_BOT_TOKEN') ? 'SET' : 'EMPTY',
                    'config_token' => config('services.telegram.bot_token') ? 'SET' : 'EMPTY',
                    'env_value' => env('TELEGRAM_BOT_TOKEN') ? 'EXISTS' : 'MISSING',
                    'config_value' => config('services.telegram.bot_token') ? 'EXISTS' : 'MISSING',
                    'raw_env' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? 'NOT IN $_ENV',
                    'raw_server' => $_SERVER['TELEGRAM_BOT_TOKEN'] ?? 'NOT IN $_SERVER',
                    'base_path' => base_path(),
                    'env_path' => base_path('.env'),
                ],
            ]);
        }

        $message = "🔔 Test Notification\n\nThis is a test message from Timesheet/OT Form system.\n\nIf you received this, your Telegram chat ID is correctly configured!";

        $success = $telegram->sendMessage($request->chat_id, $message);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Message sent successfully' : 'Failed to send message. Please check your chat ID.',
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
