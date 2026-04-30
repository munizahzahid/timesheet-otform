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
                <img src="{{ asset('images/TALENT SYNERGY SDN BHD.png') }}" alt="Talent Synergy Sdn Bhd" class="h-14 w-auto rounded-full">
                <span class="text-2xl font-bold">TSSB Portal</span>
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
                <img src="{{ asset('images/TALENT SYNERGY SDN BHD.png') }}" alt="Talent Synergy Sdn Bhd" class="h-12 w-auto rounded-full">
                <span class="text-xl font-bold text-gray-900">TSSB Portal</span>
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

                    <!-- Email Address -->
                    <div class="mb-5">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </div>
                            <input type="email" id="email" name="email"
                                   value="{{ old('email') }}"
                                   required autofocus autocomplete="username"
                                   class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-blue-700 focus:ring-2 focus:ring-blue-700/20 transition-all duration-200 outline-none placeholder-gray-400"
                                   placeholder="you@example.com">
                        </div>
                        @if ($errors->has('email'))
                            <p class="mt-2 text-sm text-red-600">{{ $errors->first('email') }}</p>
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
                                   class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-blue-700 focus:ring-2 focus:ring-blue-700/20 transition-all duration-200 outline-none placeholder-gray-400"
                                   placeholder="••••••••">
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
                        Need help? Contact <a href="#" class="text-blue-700 hover:text-blue-800 font-medium">IT Support</a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <p class="text-center text-sm text-gray-500 mt-6">
                © {{ date('Y') }} TSSB Portal. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
