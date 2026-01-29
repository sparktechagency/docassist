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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->foreign('service_id')->on('services')->references('id')->onDelete('cascade');
            $table->foreign('order_id')->on('orders')->references('id')->onDelete('cascade');

//            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
//            $table->foreignId('service_id')->constrained();
            $table->integer('quantity');
            $table->decimal('price', 12, 2); // Unit price at time of purchase
            $table->decimal('subtotal', 12, 2); // quantity * price
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dropping the table will automatically remove its FKs.
        Schema::dropIfExists('order_items');
    }
};
