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
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_chat_id')->unique();
            $table->string('conversation_id')->nullable();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->timestamps();

            $table->index('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_chats');
    }
};
