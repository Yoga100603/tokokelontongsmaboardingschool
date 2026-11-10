<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutup_buku', function (Blueprint $t) {
            $t->id();
            $t->foreignId('toko_id')->constrained('toko')->cascadeOnDelete();
            $t->date('tanggal');
            $t->unsignedInteger('jumlah_transaksi');
            $t->unsignedBigInteger('total_penjualan');
            $t->unsignedBigInteger('total_modal');
            $t->bigInteger('laba_kotor');
            $t->unsignedBigInteger('uang_tunai')->default(0);
            $t->text('catatan')->nullable();
            $t->foreignId('ditutup_oleh')->nullable()->constrained('pengguna');
            $t->dateTime('ditutup_pada');
            $t->unique(['toko_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutup_buku');
    }
};
