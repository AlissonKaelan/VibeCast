<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('playlist_track', function (Blueprint $table) {
            $table->id();
            // Liga a playlist...
            $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
            // ...à música!
            $table->foreignId('track_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('playlist_track');
    }
};
