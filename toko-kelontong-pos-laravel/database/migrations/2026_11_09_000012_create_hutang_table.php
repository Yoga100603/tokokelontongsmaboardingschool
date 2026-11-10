<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hutang', function (Blueprint $t) {
            $t->id();
            $t->foreignId('toko_id')->constrained('toko')->cascadeOnDelete();
            $t->foreignId('transaksi_id')->nullable()->constrained('transaksi')->nullOnDelete();
            $t->string('nama_pelanggan', 120);
            $t->string('kontak', 60)->default('');
            $t->unsignedBigInteger('jumlah'); // > 0 divalidasi di request
            $t->text('catatan')->nullable();
            $t->enum('status', ['aktif', 'lunas'])->default('aktif');
            $t->dateTime('dibuat_pada');
            $t->dateTime('lunas_pada')->nullable();
            $t->index(['toko_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hutang');
    }
};
