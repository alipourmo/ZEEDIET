<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The measurement types (spec section 6.4). Admin may add types later without code changes.
     */
    public function up(): void
    {
        Schema::create('measurement_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('label_key', 120);
            $table->string('unit', 10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_types');
    }
};
