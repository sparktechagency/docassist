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
        Schema::create('custom_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quote_id')->nullable();
            $table->foreign('quote_id')->on('quotes')->references('id')->onDelete('cascade');
//            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->text('document_request')->nullable();
            $table->string('drc'); // Document Return Country
            $table->string('duc'); // Document Use Country
            $table->string('residence_country')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_quotes', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
        });
        Schema::dropIfExists('custom_quotes');
    }
};
