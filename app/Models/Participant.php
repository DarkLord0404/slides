<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $fillable = ['live_session_id', 'token', 'name', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function responses()
    {
        return $this->hasMany(Response::class);
    }

    public function reactions()
    {
        return $this->hasMany(SlideReaction::class);
    }
}
