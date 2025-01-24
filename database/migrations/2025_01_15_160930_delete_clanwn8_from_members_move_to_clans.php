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
        Schema::table('clan_members', function (Blueprint $table) {
            $table->dropColumn('total_clan_wn8');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clan_members', function (Blueprint $table) {
            $table->integer('total_clan_wn8')->nullable();
        });
    }
};
