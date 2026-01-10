<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Apex Mobile Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-900 text-white h-screen flex flex-col justify-center items-center px-6">

    <div class="w-full max-w-sm">
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold tracking-wider text-blue-500">ApexV5</h1>
            <p class="text-slate-400 mt-2 text-sm">Employee Self Service</p>
        </div>

        @if(session('error'))
            <div class="bg-red-500/10 border border-red-500 text-red-500 px-4 py-3 rounded mb-6 text-sm text-center">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('mobile.login.post') }}" method="POST" class="space-y-6">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Employee
                    Code</label>
                <input type="text" name="emp_code"
                    class="w-full bg-slate-800 border-none rounded-lg px-4 py-3 text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 outline-none transition"
                    placeholder="e.g. HO/001" required>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Password /
                    PIN</label>
                <input type="password" name="password"
                    class="w-full bg-slate-800 border-none rounded-lg px-4 py-3 text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 outline-none transition"
                    placeholder="Enter PIN (Default: 1234)" required>
            </div>

            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-4 rounded-lg shadow-lg shadow-blue-600/30 transition transform active:scale-95">
                Login
            </button>
        </form>

        <p class="mt-8 text-center text-xs text-slate-600">
            &copy; {{ date('Y') }} Apex Human Capital
        </p>
    </div>

</body>

</html>