<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Response extends Model
{
    protected $fillable = ['activity_id', 'participant_id', 'answer', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
