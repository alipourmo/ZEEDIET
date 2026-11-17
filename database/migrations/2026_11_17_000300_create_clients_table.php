<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clients (spec section 6.1). Soft delete only: the application never hard-deletes a client.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('mobile', 11)->unique();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10);
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->string('pregnancy_status', 20)->default('none');
            $table->string('referral_source', 120)->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('consent_data_at')->nullable();
            $table->timestamp('consent_photos_at')->nullable();
            $table->string('status', 10)->default('active');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
