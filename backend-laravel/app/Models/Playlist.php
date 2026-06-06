<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    // Colunas que podem ser preenchidas via código
    protected $fillable = ['name', 'description'];

    // Relacionamento: Uma playlist tem muitas músicas (através da tabela pivot)
    public function tracks()
    {
        return $this->belongsToMany(Track::class);
    }
}