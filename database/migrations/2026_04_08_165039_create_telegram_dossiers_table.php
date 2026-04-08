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
        Schema::create('telegram_dossiers', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id')->unique();
            $table->integer('idiot_score')->default(0);
            $table->json('known_facts')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_dossiers');
    }
};
