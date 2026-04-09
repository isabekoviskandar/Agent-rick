<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('telegram_dossiers', function (Blueprint $table) {
            $table->json('behavioral_notes')->nullable()->after('known_facts');
            $table->json('vulnerability_notes')->nullable()->after('behavioral_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_dossiers', function (Blueprint $table) {
            $table->dropColumn(['behavioral_notes', 'vulnerability_notes']);
        });
    }
};
