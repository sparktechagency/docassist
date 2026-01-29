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
        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'delivery_id')) {
                $table->unsignedBigInteger('delivery_id')->nullable();
                $table->foreign('delivery_id')->on('deliveries')->references('id')->onDelete('cascade');
//                $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'delivery_id')) {
                $table->dropForeignIdFor('delivery');
            }
        });
    }
};
