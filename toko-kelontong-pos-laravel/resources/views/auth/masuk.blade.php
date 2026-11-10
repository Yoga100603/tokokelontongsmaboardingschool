<x-kartu-auth judul="Masuk ke toko Anda" sub="Untuk pemilik toko dan kasir.">
    @if (request('reset'))
        <x-pesan jenis="sukses">Kata sandi berhasil diubah. Silakan masuk dengan sandi baru.</x-pesan>
    @endif
    @if (request('sesi'))
        <x-pesan jenis="info">Sesi Anda berakhir. Silakan masuk kembali.</x-pesan>
    @endif

    <form method="POST" action="{{ route('masuk') }}" class="space-y-4">
        @csrf
        <x-bidang label="Email" nama="email" type="email" autocomplete="email" :value="old('email')" required autofocus />
        <x-bidang label="Kata sandi" nama="kata_sandi" type="password" autocomplete="current-password" required />
        <div class="-mt-2 text-right">
            <a href="{{ route('lupa-sandi') }}" class="text-xs font-semibold text-emerald-700 hover:underline">Lupa kata sandi?</a>
        </div>
        <x-tombol-kirim class="w-full py-2.5">Masuk</x-tombol-kirim>
    </form>

    @unless (app()->isProduction())
        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
            <p class="mb-1 font-semibold text-slate-700">Akun demo</p>
            <p>Pemilik: pemilik@demo.id · Kasir: kasir@demo.id</p>
            <p>Kata sandi keduanya: demo1234</p>
        </div>
    @endunless

    <x-slot:kaki>
        Belum punya akun toko?
        <a href="{{ route('daftar') }}" class="font-semibold text-emerald-700 hover:underline">Daftarkan toko</a>
    </x-slot:kaki>
</x-kartu-auth>
