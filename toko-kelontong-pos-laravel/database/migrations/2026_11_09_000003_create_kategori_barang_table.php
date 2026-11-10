<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_barang', function (Blueprint $t) {
            $t->id();
            $t->foreignId('toko_id')->constrained('toko')->cascadeOnDelete();
            $t->string('nama', 80);
            $t->unique(['toko_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_barang');
    }
};
