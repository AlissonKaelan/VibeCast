<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    // A coluna playlist_id antiga não precisa mais estar aqui, mas se estiver não tem problema
    protected $fillable = [
        'title', 'artist', 'spotify_id', 'youtube_id', 'cover_url', 'file_path', 'duration'
    ];
    // Relacionamento: Uma música pode pertencer a muitas playlists (através da tabela pivot)
    public function playlists()
    {
        return $this->belongsToMany(Playlist::class);
    }
}