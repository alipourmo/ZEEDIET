<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Services offered by the clinic (spec section 6.2). Services are deactivated, never deleted: appointments will
     * refer to them, so history must stay readable.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('kind', 20);
            $table->unsignedSmallInteger('default_duration_min');
            $table->boolean('requires_device')->default(false);
            $table->boolean('requires_specialist')->default(false);
            $table->unsignedBigInteger('default_price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
