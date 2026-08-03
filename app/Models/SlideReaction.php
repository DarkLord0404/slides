<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlideReaction extends Model
{
    protected $fillable = ['live_session_id', 'participant_id', 'slide_id', 'type'];
}
