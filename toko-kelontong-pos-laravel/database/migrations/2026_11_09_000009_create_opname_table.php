<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opname', function (Blueprint $t) {
            $t->id();
            $t->foreignId('toko_id')->constrained('toko')->cascadeOnDelete();
            $t->string('kode', 40);
            $t->enum('status', ['draft', 'diajukan', 'disetujui', 'ditolak'])->default('draft');
            $t->text('catatan')->nullable();
            $t->text('alasan_tolak')->nullable();
            $t->foreignId('dibuat_oleh')->nullable()->constrained('pengguna');
            $t->dateTime('diajukan_pada')->nullable();
            $t->foreignId('diputuskan_oleh')->nullable()->constrained('pengguna');
            $t->dateTime('diputuskan_pada')->nullable();
            $t->dateTime('dibuat_pada');
            $t->unique(['toko_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opname');
    }
};
