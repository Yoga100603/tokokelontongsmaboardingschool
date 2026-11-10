<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toko', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 120);
            $t->string('slug', 80)->unique();
            $t->string('alamat')->default('');
            $t->string('telepon', 30)->default('');
            $t->string('email', 120)->default('');
            $t->text('deskripsi')->nullable();
            $t->string('logo_url')->default('');
            $t->string('jam_buka')->default('');
            $t->string('catatan_struk')->default('Terima kasih atas kunjungan Anda!');
            $t->boolean('katalog_aktif')->default(true);
            $t->dateTime('dibuat_pada');
            $t->dateTime('diubah_pada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toko');
    }
};
