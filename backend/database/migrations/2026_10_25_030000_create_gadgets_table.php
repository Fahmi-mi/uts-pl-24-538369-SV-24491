<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gadgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('brand', 100);
            $table->enum('category', ['smartphone', 'laptop', 'audio', 'wearable', 'camera', 'accessory', 'other']);
            $table->string('store', 100)->nullable();
            $table->string('product_url', 2048)->nullable();
            $table->unsignedBigInteger('target_price');
            $table->unsignedBigInteger('current_price');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['wishlist', 'purchased', 'dropped'])->default('wishlist');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gadgets');
    }
};
