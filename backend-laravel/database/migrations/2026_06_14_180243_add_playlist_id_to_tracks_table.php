<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tracks', function (Blueprint $table) {
            // Cria a coluna permitindo que ela seja nula (músicas soltas sem playlist)
            // e cria a relação com a tabela playlists
            $table->foreignId('playlist_id')->nullable()->constrained('playlists')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropForeign(['playlist_id']);
            $table->dropColumn('playlist_id');
        });
    }
};
