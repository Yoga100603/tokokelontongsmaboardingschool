<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_transaksi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transaksi_id')->constrained('transaksi')->cascadeOnDelete();
            $t->foreignId('barang_id')->nullable()->constrained('barang')->nullOnDelete();
            $t->string('nama_barang', 150);
            $t->string('satuan', 30)->default('pcs');
            $t->unsignedInteger('jumlah'); // > 0 divalidasi di service
            $t->unsignedBigInteger('harga_jual');
            $t->unsignedBigInteger('harga_modal')->default(0);
            $t->unsignedBigInteger('subtotal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_transaksi');
    }
};
