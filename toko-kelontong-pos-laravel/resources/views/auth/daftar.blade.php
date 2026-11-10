<x-kartu-auth judul="Daftarkan toko Anda" sub="Gratis, tanpa instalasi. Data setiap toko terpisah dan aman.">
    <form method="POST" action="{{ route('daftar') }}" class="space-y-4">
        @csrf
        <x-bidang label="Nama toko" nama="nama_toko" wajib minlength="3" maxlength="80" :value="old('nama_toko')"
            placeholder="cth. Toko Sembako Makmur" autofocus />
        <x-bidang label="Nama pemilik" nama="nama" wajib maxlength="80" :value="old('nama')" autocomplete="name" />
        <x-bidang label="Email" nama="email" wajib type="email" :value="old('email')" autocomplete="email" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-bidang label="Kata sandi" nama="kata_sandi" wajib type="password" minlength="8" autocomplete="new-password"
                petunjuk="Minimal 8 karakter." />
            <x-bidang label="Ulangi kata sandi" nama="kata_sandi_confirmation" wajib type="password" minlength="8"
                autocomplete="new-password" />
        </div>
        <x-tombol-kirim class="w-full py-2.5">Daftar &amp; Mulai</x-tombol-kirim>
    </form>

    <x-slot:kaki>
        Sudah punya akun?
        <a href="{{ route('masuk') }}" class="font-semibold text-emerald-700 hover:underline">Masuk</a>
    </x-slot:kaki>
</x-kartu-auth>
