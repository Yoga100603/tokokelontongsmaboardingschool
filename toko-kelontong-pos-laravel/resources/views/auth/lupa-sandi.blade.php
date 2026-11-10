<x-kartu-auth judul="Lupa kata sandi" sub="Masukkan email akun Anda. Kami kirim tautan untuk membuat kata sandi baru.">
    <form method="POST" action="{{ route('lupa-sandi') }}" class="space-y-4">
        @csrf
        <x-bidang label="Email" nama="email" type="email" :value="old('email')" required autofocus />
        <x-tombol-kirim class="w-full py-2.5">Kirim tautan reset</x-tombol-kirim>
    </form>
    <p class="mt-4 text-xs text-slate-500">
        Kasir yang lupa kata sandi juga dapat meminta pemilik toko mengatur ulang sandinya dari menu Akun &amp; Pengaturan.
    </p>

    <x-slot:kaki>
        <a href="{{ route('masuk') }}" class="font-semibold text-emerald-700 hover:underline">← Kembali ke halaman masuk</a>
    </x-slot:kaki>
</x-kartu-auth>
