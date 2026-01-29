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
        Schema::create('order_item_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->foreign('order_item_id')->on('order_items')->references('id')->onDelete('cascade');
//            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('delivery_detail_id')->nullable();
            $table->foreign('delivery_detail_id')->on('delivery_details')->references('id')->onDelete('cascade');
//            $table->foreignId('delivery_detail_id')->constrained('delivery_details')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_item_deliveries', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->dropForeign(['delivery_detail_id']);
        });
        Schema::dropIfExists('order_item_deliveries');
    }
};
