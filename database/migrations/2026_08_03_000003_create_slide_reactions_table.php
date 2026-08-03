<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('slide_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slide_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('like');
            $table->timestamps();
            $table->unique(['live_session_id', 'participant_id', 'slide_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slide_reactions');
    }
};
