<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slide extends Model
{
    protected $fillable = ['presentation_id', 'position', 'title', 'body', 'background_path', 'design'];

    protected function casts(): array
    {
        return ['design' => 'array'];
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function reactions()
    {
        return $this->hasMany(SlideReaction::class);
    }
}
