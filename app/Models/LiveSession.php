<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSession extends Model
{
    protected $fillable = ['presentation_id', 'active_slide_id', 'code', 'status', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function activeSlide()
    {
        return $this->belongsTo(Slide::class, 'active_slide_id');
    }

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }
}
