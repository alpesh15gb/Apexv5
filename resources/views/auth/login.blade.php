@extends('layouts.guest')

@section('content')
    <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white shadow-md overflow-hidden sm:rounded-lg border border-slate-100">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-apex-900">ApexV5</h1>
            <p class="text-slate-500 mt-2 text-sm">Sign in to your account</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Whoops!</strong>
                <span class="block sm:inline">{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <label for="email" class="block font-medium text-sm text-slate-700">Email</label>
                <input id="email"
                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-apex-500 focus:ring-apex-500 py-2 px-3 border"
                    type="email" name="email" :value="old('email')" required autofocus />
            </div>

            <!-- Password -->
            <div class="mt-4">
                <label for="password" class="block font-medium text-sm text-slate-700">Password</label>
                <input id="password"
                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-apex-500 focus:ring-apex-500 py-2 px-3 border"
                    type="password" name="password" required autocomplete="current-password" />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox"
                        class="rounded border-gray-300 text-apex-600 shadow-sm focus:border-apex-500 focus:ring focus:ring-apex-500 focus:ring-opacity-50"
                        name="remember">
                    <span class="ml-2 text-sm text-slate-600">Remember me</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                <button type="submit"
                    class="w-full bg-apex-600 hover:bg-apex-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                    Sign in
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <p class="text-xs text-slate-400">
                &copy; {{ date('Y') }} Apex Human Capital. All rights reserved.
            </p>
        </div>
    </div>
@endsection