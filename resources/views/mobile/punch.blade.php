<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Punch - ApexV5</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-black text-white h-screen flex flex-col">

    <!-- Header -->
    <div class="p-4 flex justify-between items-center bg-transparent absolute top-0 w-full z-10">
        <div>
            <h2 class="text-lg font-bold">{{ $employee->name }}</h2>
            <p class="text-xs text-stone-300">{{ $employee->device_emp_code }}</p>
        </div>
        <a href="{{ route('mobile.logout') }}" class="text-stone-400 hover:text-white"><i
                class="fas fa-sign-out-alt fa-lg"></i></a>
    </div>

    <!-- Camera Viewport -->
    <div class="relative flex-1 bg-gray-900 overflow-hidden">
        <video id="camera-stream" autoplay playsinline muted class="w-full h-full object-cover"></video>

        <!-- Location Overlay -->
        <div class="absolute bottom-6 left-0 w-full text-center px-4">
            <div id="location-status"
                class="inline-flex items-center bg-black/50 backdrop-blur-md px-3 py-1 rounded-full text-xs text-yellow-400 border border-yellow-500/30">
                <i class="fas fa-satellite-dish animate-pulse mr-2"></i> Acquiring Location...
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="bg-stone-900 p-6 rounded-t-3xl -mt-4 relative z-20 shadow-[0_-4px_20px_rgba(0,0,0,0.5)]">
        <!-- Time -->
        <div class="text-center mb-6">
            <h1 class="text-4xl font-mono font-bold tracking-widest" id="clock">00:00:00</h1>
            <p class="text-stone-400 text-sm mt-1">{{ now()->format('l, d M Y') }}</p>
        </div>

        <!-- Punch Buttons -->
        <div class="grid grid-cols-2 gap-4">
            <button onclick="submitPunch('check_in')" id="btn-in" disabled
                class="bg-green-600 hover:bg-green-500 disabled:bg-stone-700 disabled:text-stone-500 text-white py-4 rounded-xl font-bold text-lg shadow-lg flex flex-col items-center justify-center gap-1 transition-all">
                <i class="fas fa-sign-in-alt mb-1"></i>
                PUNCH IN
            </button>

            <button onclick="submitPunch('check_out')" id="btn-out" disabled
                class="bg-red-600 hover:bg-red-500 disabled:bg-stone-700 disabled:text-stone-500 text-white py-4 rounded-xl font-bold text-lg shadow-lg flex flex-col items-center justify-center gap-1 transition-all">
                <i class="fas fa-sign-out-alt mb-1"></i>
                PUNCH OUT
            </button>
        </div>

        <!-- Today's Status -->
        <div class="mt-6 border-t border-stone-800 pt-4 text-center">
            @if($attendance && $attendance->in_time)
                <div class="text-sm text-stone-300">
                    <span class="text-green-400">In:</span> {{ \Carbon\Carbon::parse($attendance->in_time)->format('H:i') }}
                    <span class="mx-2 text-stone-600">|</span>
                    <span class="text-red-400">Out:</span>
                    {{ $attendance->out_time ? \Carbon\Carbon::parse($attendance->out_time)->format('H:i') : '--:--' }}
                </div>
            @else
                <p class="text-xs text-stone-500 uppercase tracking-widest">No Attendance Today</p>
            @endif
        </div>
    </div>

    <!-- Hidden Canvas for Capture -->
    <canvas id="photo-canvas" class="hidden"></canvas>

    <!-- Loading Overlay -->
    <div id="loading" class="fixed inset-0 bg-black/80 z-50 flex flex-col items-center justify-center hidden">
        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500 mb-4"></div>
        <p class="text-blue-400 font-bold tracking-wide">Processing Punch...</p>
    </div>

    <!-- JS Logic -->
    <script>
        // DOM Elements
        const video = document.getElementById('camera-stream');
        const locStatus = document.getElementById('location-status');
        const btnIn = document.getElementById('btn-in');
        const btnOut = document.getElementById('btn-out');
        const canvas = document.getElementById('photo-canvas');
        const loading = document.getElementById('loading');

        // State
        let currentLat = null;
        let currentLong = null;
        let locationLocked = false;

        // 1. Start Clock
        setInterval(() => {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
        }, 1000);

        // 2. Access Camera
        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                    audio: false
                });
                video.srcObject = stream;
            } catch (err) {
                alert('Camera access denied. Please allow camera permissions.');
                locStatus.innerText = 'Camera Error';
                locStatus.classList.replace('text-yellow-400', 'text-red-500');
            }
        }
        startCamera();

        // 3. Get Location
        if ("geolocation" in navigator) {
            navigator.geolocation.watchPosition(
                (position) => {
                    currentLat = position.coords.latitude;
                    currentLong = position.coords.longitude;
                    const accuracy = position.coords.accuracy;

                    if (accuracy <= 100) { // Require reasonable accuracy
                        locationLocked = true;
                        locStatus.innerHTML = `<i class="fas fa-map-marker-alt mr-2"></i> Location Locked (${accuracy.toFixed(0)}m)`;
                        locStatus.className = "inline-flex items-center bg-green-500/20 backdrop-blur-md px-3 py-1 rounded-full text-xs text-green-400 border border-green-500/30";

                        // Enable buttons
                        btnIn.disabled = false;
                        btnOut.disabled = false;
                    } else {
                        locStatus.innerText = `Weak GPS Signal (${accuracy.toFixed(0)}m)`
                    }
                },
                (error) => {
                    locStatus.innerText = 'GPS Permission Denied';
                    locStatus.classList.replace('text-yellow-400', 'text-red-500');
                },
                { enableHighAccuracy: true }
            );
        } else {
            locStatus.innerText = 'GPS not supported';
        }

        // 4. Submit Punch
        async function submitPunch(type) {
            if (!locationLocked || !currentLat) return;

            loading.classList.remove('hidden');

            // Capture Photo
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageBase64 = canvas.toDataURL('image/jpeg', 0.8);

            // Payload
            const data = {
                image: imageBase64,
                lat: currentLat,
                long: currentLong,
                type: type,
                _token: document.querySelector('meta[name="csrf-token"]').content
            };

            try {
                const response = await fetch("{{ route('mobile.punch.store') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                    loading.classList.add('hidden');
                }
            } catch (e) {
                alert('Network Error');
                loading.classList.add('hidden');
            }
        }
    </script>
</body>

</html>