<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    protected $fillable = [
        'title', 
        'artist', 
        'youtube_id', 
        'cover_url', 
        'duration_seconds'
    ];

    // Relacionamento inverso
    public function playlists()
    {
        return $this->belongsToMany(Playlist::class);
    }
}