<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_struk', function (Blueprint $t) {
            $t->id();
            $t->foreignId('toko_id')->constrained('toko')->cascadeOnDelete();
            $t->foreignId('transaksi_id')->constrained('transaksi')->cascadeOnDelete();
            $t->foreignId('pengguna_id')->nullable()->constrained('pengguna');
            $t->enum('aksi', ['cetak', 'simpan', 'kirim']);
            $t->string('tujuan', 64)->default('');
            $t->dateTime('dibuat_pada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_struk');
    }
};
