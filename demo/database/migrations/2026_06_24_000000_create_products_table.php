<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
            $table->date('available_on')->nullable();
            $table->string('category')->nullable();
            $table->decimal('internal_cost', 10, 2)->nullable();
            $table->timestamps();
        });
    }
};
