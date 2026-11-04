@props([
    'title' => 'Sistem Usulan Obat Formularium RS',
    'activeTab' => 'form',
])

<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — RS Aisyiyah Siti Fatimah Tulangan</title>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex flex-col min-h-full font-sans text-slate-800 antialiased bg-slate-50 selection:bg-emerald-600 selection:text-white">
    <!-- Hospital Header -->
    <header class="bg-gradient-to-r from-emerald-800 to-emerald-700 text-white shadow-md sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between py-3.5 sm:py-4 gap-3">
                <div class="flex items-center space-x-3 text-center sm:text-left">
                    <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-xs border border-white/20 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-emerald-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-bold tracking-tight text-white leading-tight">
                            Formularium Obat RS
                        </h1>
                        <p class="text-xs text-emerald-100 font-medium tracking-wide">
                            RS Aisyiyah Siti Fatimah Tulangan • Farmasi
                        </p>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <nav class="flex items-center space-x-1.5 bg-emerald-900/50 p-1 rounded-xl border border-white/10 text-sm">
                    <a href="{{ route('proposals.create') }}" 
                       class="px-4 py-1.5 rounded-lg font-medium transition-all duration-150 flex items-center gap-1.5 {{ $activeTab === 'form' ? 'bg-white text-emerald-900 shadow-xs' : 'text-emerald-100 hover:text-white hover:bg-white/10' }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Formulir Usulan
                    </a>
                    <a href="{{ route('proposals.index') }}" 
                       class="px-4 py-1.5 rounded-lg font-medium transition-all duration-150 flex items-center gap-1.5 {{ $activeTab === 'recap' ? 'bg-white text-emerald-900 shadow-xs' : 'text-emerald-100 hover:text-white hover:bg-white/10' }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Rekapitulasi Usulan
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="mt-auto border-t border-slate-200/80 bg-white py-4 text-center text-xs text-slate-500">
        <p>&copy; {{ date('Y') }} RS Aisyiyah Siti Fatimah Tulangan • Sistem Usulan Formularium Obat Terintegrasi</p>
    </footer>
</body>
</html>
