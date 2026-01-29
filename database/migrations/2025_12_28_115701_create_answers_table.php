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
        if (! Schema::hasTable('answers')) {
            Schema::create('answers', function (Blueprint $table) {
                $table->id();
                // 1. Who answered?
                $table->unsignedBigInteger('user_id')->nullable();
                $table->foreign('user_id')->on('users')->references('id')->onDelete('cascade');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->foreign('order_id')->on('orders')->references('id')->onDelete('cascade');
                $table->unsignedBigInteger('order_item_id')->nullable();
                $table->foreign('order_item_id')->on('order_items')->references('id')->onDelete('cascade');
                $table->unsignedBigInteger('cart_id')->nullable();
                $table->foreign('cart_id')->on('carts')->references('id')->onDelete('cascade');
                $table->unsignedBigInteger('cart_item_id')->nullable();
                $table->foreign('cart_item_id')->on('cart_items')->references('id')->onDelete('cascade');
                $table->unsignedBigInteger('service_quote_id')->nullable();
                $table->foreign('service_quote_id')->on('service_quotes')->references('id')->onDelete('cascade');
                $table->unsignedBigInteger('questionary_id')->nullable();
                $table->foreign('questionary_id')->on('questionary')->references('id')->onDelete('cascade');
//                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
//                $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
//                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->cascadeOnDelete();
//                $table->foreignId('cart_id')->nullable()->constrained()->cascadeOnDelete();
//                $table->foreignId('cart_item_id')->nullable()->constrained('cart_items')->cascadeOnDelete();
//                $table->foreignId('service_quote_id')->nullable()->constrained('service_quotes')->cascadeOnDelete();
//                $table->foreignId('questionary_id')->constrained('questionaries')->cascadeOnDelete();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
