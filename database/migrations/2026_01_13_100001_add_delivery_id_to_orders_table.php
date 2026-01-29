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
        Schema::table('orders', function (Blueprint $table) {
            // Add delivery_id column if it doesn't exist
            if (!Schema::hasColumn('orders', 'delivery_id')) {
                $table->unsignedBigInteger('delivery_id')->nullable();
                $table->foreign('delivery_id')->on('deliveries')->references('id')->onDelete('cascade');
//                $table->foreignId('delivery_id')
//                    ->nullable()
//                    ->after('total_amount')
//                    ->constrained('deliveries')
//                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_id')) {
                $table->dropForeignIdFor('deliveries');
                $table->dropColumn('delivery_id');
            }
        });
    }
};
