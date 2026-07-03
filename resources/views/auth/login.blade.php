<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - TSSB Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        body {
            font-family: 'Figtree', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }
    </style>
</head>
<body class="min-h-screen flex">
    <!-- Left Side - Branding & Info -->
    <div class="hidden lg:flex lg:w-1/2 gradient-bg items-center justify-center p-12 relative overflow-hidden">
        <!-- Decorative Elements -->
        <div class="absolute top-20 left-20 w-72 h-72 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>

        <div class="relative z-10 text-white max-w-lg">
            <!-- Logo -->
            <div class="flex items-center gap-3 mb-8">
                <img src="{{ asset('images/Logo TSSB.jpeg') }}" alt="Logo TSSB" class="h-14 w-auto rounded-full">
            </div>

            <!-- Welcome Message -->
            <h1 class="text-4xl font-bold mb-4">Welcome Back</h1>
            <p class="text-lg text-white/90 mb-8 leading-relaxed">
                Your centralized platform for timesheet management, overtime tracking, and seamless approval workflows.
            </p>

            <!-- Features -->
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold">Easy Timesheet Submission</p>
                        <p class="text-sm text-white/80">Submit and track your work hours effortlessly</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold">Streamlined OT Requests</p>
                        <p class="text-sm text-white/80">Quick approval process for overtime claims</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold">Real-time Approvals</p>
                        <p class="text-sm text-white/80">Managers can review and approve requests instantly</p>
                    </div>
                </div>
            </div>

            <!-- Quote -->
            <div class="mt-12 pt-8 border-t border-white/20">
                <p class="text-sm text-white/70 italic">
                    "Efficiency is doing things right; effectiveness is doing the right things."
                </p>
                <p class="text-xs text-white/50 mt-2">— Peter Drucker</p>
            </div>
        </div>
    </div>

    <!-- Right Side - Login Form -->
    <div class="w-full lg:w-1/2 bg-gray-50 flex items-center justify-center p-8">
        <div class="w-full max-w-md">
            <!-- Mobile Logo -->
            <div class="lg:hidden flex items-center justify-center gap-3 mb-6">
                <img src="{{ asset('images/Logo TSSB.jpeg') }}" alt="Logo TSSB" class="h-12 w-auto rounded-full">
            </div>

            <!-- Login Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Sign In</h2>
                    <p class="text-gray-500 mt-2">Enter your credentials to access your account</p>
                </div>

                <!-- Session Status -->
                @if (session('status'))
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-600">{{ session('status') }}</p>
                    </div>
                @endif

                <!-- Login Form -->
                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Staff ID -->
                    <div class="mb-5">
                        <label for="staff_no" class="block text-sm font-medium text-gray-700 mb-2">Staff ID</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                </svg>
                            </div>
                            <input type="text" id="staff_no" name="staff_no"
                                   value="{{ old('staff_no') }}"
                                   required autofocus autocomplete="username"
                                   class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-blue-700 focus:ring-2 focus:ring-blue-700/20 transition-all duration-200 outline-none placeholder-gray-400"
                                   placeholder="T094">
                        </div>
                        @if ($errors->has('staff_no'))
                            <p class="mt-2 text-sm text-red-600">{{ $errors->first('staff_no') }}</p>
                        @endif
                    </div>

                    <!-- Password -->
                    <div class="mb-5">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input type="password" id="password" name="password"
                                   required autocomplete="current-password"
                                   class="w-full pl-10 pr-12 py-3 rounded-lg border border-gray-300 focus:border-blue-700 focus:ring-2 focus:ring-blue-700/20 transition-all duration-200 outline-none placeholder-gray-400"
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('password', this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                                <svg id="password-eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                        @if ($errors->has('password'))
                            <p class="mt-2 text-sm text-red-600">{{ $errors->first('password') }}</p>
                        @endif
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="remember" id="remember"
                                   class="w-4 h-4 text-blue-700 border-gray-300 rounded focus:ring-blue-700 focus:ring-2">
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}"
                               class="text-sm text-blue-700 hover:text-blue-800 font-medium transition-colors">
                                Forgot password?
                            </a>
                        @endif
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-blue-700 to-blue-900 text-white font-semibold py-3 px-4 rounded-lg hover:from-blue-800 hover:to-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2 transition-all duration-200 shadow-lg shadow-blue-700/20">
                        Sign In
                    </button>
                </form>

                <!-- Help Text -->
                <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                    <p class="text-sm text-gray-500">
                        Need help? Contact <a href="https://wa.me/60178254072" target="_blank" class="text-blue-700 hover:text-blue-800 font-medium">IT Support</a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <p class="text-center text-sm text-gray-500 mt-6">
                © {{ date('Y') }} Talent Synergy SDN BHD. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('svg');

            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
            }
        }
    </script>
</body>
</html>
