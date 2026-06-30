<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('stream_url')->unique(); // O link ao vivo da rádio
            $table->string('logo_url')->nullable(); // Foto/Logo da rádio
            $table->string('country')->nullable(); // Ex: 'Brasil', 'EUA'
            $table->string('tags')->nullable(); // Ex: 'Notícias, Pop, Lo-Fi'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radios');
    }
};