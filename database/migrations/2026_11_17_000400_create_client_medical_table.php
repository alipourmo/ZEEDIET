<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One medical record per client (spec section 6.1). Deleting a client never cascades here, and medical rows
     * are never hard-deleted by the application.
     */
    public function up(): void
    {
        Schema::create('client_medical', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->restrictOnDelete();
            $table->text('food_allergies')->nullable();
            $table->text('diseases')->nullable();
            $table->text('surgeries')->nullable();
            $table->text('medications')->nullable();
            $table->text('genetics_notes')->nullable();
            $table->text('diet_relevant_notes')->nullable();
            $table->json('device_contraindications');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_medical');
    }
};
