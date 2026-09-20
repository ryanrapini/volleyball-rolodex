<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Approval gates the AI assistant only — the rest of the app is open
            // to any verified account.
            $table->timestamp('ai_approved_at')->nullable();
            $table->boolean('is_admin')->default(false);
        });

        // Accounts that already existed were never subject to the gate, and the
        // oldest one is the owner.
        DB::table('users')->update(['ai_approved_at' => now()]);

        $owner = DB::table('users')->orderBy('id')->value('id');

        if ($owner !== null) {
            DB::table('users')->where('id', $owner)->update(['is_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_approved_at', 'is_admin']);
        });
    }
};
