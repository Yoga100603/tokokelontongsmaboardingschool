<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi_stok', function (Blueprint $t) {
            $t->id();
            $t->foreignId('toko_id')->constrained('toko')->cascadeOnDelete();
            $t->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $t->foreignId('pengguna_id')->nullable()->constrained('pengguna');
            $t->enum('jenis', ['awal', 'masuk', 'keluar', 'penjualan', 'penyesuaian']);
            $t->integer('jumlah'); // negatif = stok berkurang
            $t->unsignedInteger('stok_sebelum');
            $t->unsignedInteger('stok_sesudah');
            $t->string('keterangan')->default('');
            $t->string('referensi', 64)->default('');
            $t->dateTime('dibuat_pada');
            $t->index(['barang_id', 'dibuat_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_stok');
    }
};
