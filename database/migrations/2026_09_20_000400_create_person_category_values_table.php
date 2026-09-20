<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_category_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained()->cascadeOnDelete();
            // Set for pick-one and pick-any categories...
            $table->foreignUuid('category_option_id')->nullable()
                ->constrained('category_options')->cascadeOnDelete();
            // ...and a plain boolean for yes/no categories.
            $table->boolean('value')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'category_id']);
            $table->unique(['person_id', 'category_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_category_values');
    }
};
