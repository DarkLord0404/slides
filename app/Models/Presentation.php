<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Presentation extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'theme'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function slides()
    {
        return $this->hasMany(Slide::class)->orderBy('position');
    }

    public function sessions()
    {
        return $this->hasMany(LiveSession::class);
    }
}
