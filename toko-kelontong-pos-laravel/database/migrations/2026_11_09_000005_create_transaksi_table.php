<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('toko_id')->constrained('toko')->cascadeOnDelete();
            $t->foreignId('pengguna_id')->constrained('pengguna');
            $t->string('nomor_transaksi', 40);
            $t->date('tanggal');
            $t->dateTime('waktu');
            $t->unsignedBigInteger('total');
            $t->unsignedBigInteger('total_modal')->default(0);
            $t->unsignedBigInteger('uang_bayar');
            $t->unsignedBigInteger('kembalian');
            $t->string('metode_bayar', 20)->default('tunai');
            $t->string('nama_pelanggan', 120)->default('');
            $t->enum('status', ['selesai', 'dibatalkan'])->default('selesai');
            $t->unique(['toko_id', 'nomor_transaksi']);
            $t->index(['toko_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
