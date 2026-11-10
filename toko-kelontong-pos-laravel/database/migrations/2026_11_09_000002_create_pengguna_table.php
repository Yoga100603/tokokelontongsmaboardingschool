<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $t) {
            $t->id();
            $t->foreignId('toko_id')->constrained('toko')->cascadeOnDelete();
            $t->string('nama', 120);
            $t->string('email', 120)->unique(); // collation *_ci → case-insensitive
            $t->string('kata_sandi');
            $t->enum('peran', ['pemilik', 'kasir']);
            $t->boolean('aktif')->default(true);
            $t->unsignedInteger('versi_sesi')->default(1);
            $t->string('token_reset', 128)->nullable();
            $t->dateTime('token_reset_kedaluwarsa')->nullable();
            $t->dateTime('terakhir_masuk')->nullable();
            $t->rememberToken();
            $t->dateTime('dibuat_pada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
};
