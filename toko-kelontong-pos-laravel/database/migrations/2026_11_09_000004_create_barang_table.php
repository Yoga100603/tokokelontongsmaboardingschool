<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $t) {
            $t->id();
            $t->foreignId('toko_id')->constrained('toko')->cascadeOnDelete();
            $t->foreignId('kategori_id')->nullable()->constrained('kategori_barang')->nullOnDelete();
            $t->string('kode_barang', 64);
            $t->string('nama_barang', 150);
            $t->string('satuan', 30)->default('pcs');
            $t->unsignedBigInteger('harga_modal')->default(0);
            $t->unsignedBigInteger('harga_jual');
            $t->unsignedInteger('stok')->default(0);
            $t->unsignedInteger('stok_minimum')->default(5);
            $t->string('foto_url')->default('');
            $t->boolean('tampil_di_katalog')->default(true);
            $t->boolean('aktif')->default(true);
            $t->dateTime('dibuat_pada');
            $t->dateTime('diubah_pada');
            $t->unique(['toko_id', 'kode_barang']);
            $t->index(['toko_id', 'nama_barang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
