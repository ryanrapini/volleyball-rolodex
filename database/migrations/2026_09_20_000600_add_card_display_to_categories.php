<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a category presents itself on a person's card, and the colours of the
     * tags themselves.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('show_on_card')->default(true);
            $table->boolean('show_name_on_card')->default(true);
            // The tag colour for a yes / no category. Choice categories colour
            // each option instead.
            $table->string('colour', 9)->nullable();
        });

        Schema::table('category_options', function (Blueprint $table) {
            $table->string('colour', 9)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('category_options', function (Blueprint $table) {
            $table->dropColumn('colour');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['show_on_card', 'show_name_on_card', 'colour']);
        });
    }
};
