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
        Schema::create('service_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->foreign('service_id')->on('services')->references('id')->onDelete('cascade');
            $table->unsignedBigInteger('quote_id')->nullable();
            $table->foreign('quote_id')->on('quotes')->references('id')->onDelete('cascade');
//            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
//            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_quotes', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
            $table->dropForeign(['service_id']);
        });
        Schema::dropIfExists('service_quotes');
    }
};
