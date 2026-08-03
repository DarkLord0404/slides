<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = ['slide_id', 'type', 'question', 'options', 'settings'];

    protected function casts(): array
    {
        return ['options' => 'array', 'settings' => 'array'];
    }

    public function slide()
    {
        return $this->belongsTo(Slide::class);
    }

    public function responses()
    {
        return $this->hasMany(Response::class);
    }
}
