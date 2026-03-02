<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two Factor Authentication</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-zinc-950 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md p-8 border border-gray-800 bg-black">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-light text-white mb-2">Two Factor Authentication</h1>
            <p class="text-gray-500 text-sm">Enter the 6-digit code from your authenticator app</p>
        </div>

        @if($errors->any())
            <div class="mb-4 p-3 border border-red-500/30 bg-red-500/5 text-red-400 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ url('/2fa') }}">
            <div class="mb-6">
                <label class="block text-xs text-gray-500 uppercase tracking-wider mb-2">
                    Authentication Code
                </label>
                <input
                    type="text"
                    name="one_time_password"
                    maxlength="6"
                    autofocus
                    autocomplete="off"
                    class="w-full bg-zinc-950 border border-gray-800 text-white px-4 py-3 text-center text-2xl tracking-widest focus:border-amber-500/50 focus:outline-none"
                    placeholder="000000"
                />
            </div>
            <button type="submit"
                class="w-full py-3 bg-gradient-to-r from-amber-600 to-yellow-600 text-black font-medium uppercase tracking-widest text-sm hover:from-amber-500 hover:to-yellow-500 transition-all duration-300">
                Verify
            </button>
        </form>
    </div>
</body>
</html>
