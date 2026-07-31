<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view (staff ID).
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Display the email input view (when user has no email in database).
     */
    public function showEmailForm(Request $request): View
    {
        return view('auth.forgot-password-email', [
            'staff_no' => $request->session()->get('password_reset_staff_no'),
        ]);
    }

    /**
     * Handle staff ID submission and check if user has email.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'staff_no' => ['required', 'string'],
        ]);

        $staffNo = $request->input('staff_no');
        $user = User::where('staff_no', $staffNo)->first();

        if (!$user) {
            // Don't reveal if user exists or not for security
            return back()->with('status', 'If your staff ID is registered, you will receive a password reset link shortly.');
        }

        // If user has email in database, send reset link to that email
        if ($user->email) {
            $status = Password::sendResetLink(['email' => $user->email]);

            return $status == Password::RESET_LINK_SENT
                        ? back()->with('status', 'If your staff ID is registered, you will receive a password reset link shortly.')
                        : back()->withErrors(['staff_no' => 'Unable to send reset link. Please contact IT support.']);
        }

        // User has no email in database - ask for email
        $request->session()->put('password_reset_staff_no', $staffNo);
        return redirect()->route('password.email.form');
    }

    /**
     * Handle email submission and send reset link.
     *
     * @throws ValidationException
     */
    public function sendResetLinkWithEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'staff_no' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $staffNo = $request->input('staff_no');
        $email = $request->input('email');

        $user = User::where('staff_no', $staffNo)->first();

        if (!$user) {
            return back()->with('status', 'If your staff ID is registered, you will receive a password reset link shortly.');
        }

        // Update user with the provided email
        $user->email = $email;
        $user->save();

        // Send reset link
        $status = Password::sendResetLink(['email' => $email]);

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', 'If your staff ID is registered, you will receive a password reset link shortly.')
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => 'Unable to send reset link. Please contact IT support.']);
    }
}
