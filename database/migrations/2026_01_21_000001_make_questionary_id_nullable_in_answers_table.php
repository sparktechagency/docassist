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
        Schema::table('answers', function (Blueprint $table) {
            // Make questionary_id nullable since answers can be for required documents too
//            $table->foreignId('questionary_id')->nullable()->change();

//            $table->unsignedBigInteger('questionary_id')->nullable();
//            $table->foreign('questionary_id')->on('questionaries')->references('id')->onDelete('cascade');
            $table->foreign('questionary_id', 'fk_answers_questionary')
                ->references('id')
                ->on('questionaries')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->foreignId('questionary_id')->nullable(false)->change();
        });
    }
};
