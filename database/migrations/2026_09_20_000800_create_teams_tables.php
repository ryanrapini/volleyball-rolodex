<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            // The day they are playing; a team can be saved before a date is set.
            $table->date('tournament_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tournament_date']);
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained()->cascadeOnDelete();
            // waiting / yes / no — what they said when they were asked.
            $table->string('response', 10)->default('waiting');
            // The order they were picked in.
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['team_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
