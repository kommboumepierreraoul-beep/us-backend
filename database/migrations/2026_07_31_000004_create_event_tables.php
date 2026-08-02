<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('campus')->index();
            $table->string('status')->default('open')->index();
            $table->string('venue')->nullable();
            $table->string('city')->nullable()->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('cover_url')->nullable();
            $table->boolean('is_premium')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('event_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('ticket_code')->nullable()->unique();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invitations');
        Schema::dropIfExists('events');
    }
};
