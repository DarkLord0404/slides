<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', fn (Blueprint $table) => $table->json('theme_settings')->nullable()->after('theme'));
    }

    public function down(): void
    {
        Schema::table('presentations', fn (Blueprint $table) => $table->dropColumn('theme_settings'));
    }
};
