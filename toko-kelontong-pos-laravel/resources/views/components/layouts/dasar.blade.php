<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#059669">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Aplikasi manajemen toko dan kasir berbasis web untuk UMKM: kasir, stok, struk, laporan, stok opname, dan katalog publik.">
    <title>{{ isset($judul) ? $judul.' · TokoKasir' : 'TokoKasir — Aplikasi Toko & Kasir UMKM' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('kepala')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased selection:bg-emerald-500 selection:text-white">
    {{ $slot }}
    @stack('skrip')
</body>
</html>
