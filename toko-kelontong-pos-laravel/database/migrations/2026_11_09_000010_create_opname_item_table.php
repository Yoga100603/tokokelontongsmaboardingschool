<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opname_item', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opname_id')->constrained('opname')->cascadeOnDelete();
            $t->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $t->unsignedInteger('stok_sistem');
            $t->unsignedInteger('stok_fisik')->nullable();
            $t->integer('selisih')->nullable();
            $t->string('alasan')->default('');
            $t->unique(['opname_id', 'barang_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opname_item');
    }
};
