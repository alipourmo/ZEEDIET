<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Session packages (spec section 6.3). A package is bought once and is never deleted: it is a financial record.
     * The remaining sessions are computed from device_sessions, never stored (see SessionPackage).
     */
    public function up(): void
    {
        Schema::create('session_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedSmallInteger('total_sessions');
            $table->unsignedBigInteger('price')->nullable();
            $table->date('purchased_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_packages');
    }
};
