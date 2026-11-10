<x-kartu-auth judul="Buat kata sandi baru">
    @if (! $token)
        <x-pesan jenis="galat">Tautan tidak lengkap. Buka tautan dari email reset kata sandi.</x-pesan>
    @endif

    <form method="POST" action="{{ route('reset-sandi') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-bidang label="Kata sandi baru" nama="kata_sandi" type="password" minlength="8" autocomplete="new-password"
            petunjuk="Minimal 8 karakter." required autofocus />
        <x-bidang label="Ulangi kata sandi baru" nama="kata_sandi_confirmation" type="password" minlength="8"
            autocomplete="new-password" required />
        <x-tombol-kirim class="w-full py-2.5" :disabled="! $token">Simpan kata sandi</x-tombol-kirim>
    </form>

    <x-slot:kaki>
        <a href="{{ route('lupa-sandi') }}" class="font-semibold text-emerald-700 hover:underline">Minta tautan baru</a>
    </x-slot:kaki>
</x-kartu-auth>
