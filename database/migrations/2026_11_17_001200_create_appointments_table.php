<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments (spec section 6.3). Times are clinic local time (Asia/Tehran). A row is never deleted:
     * a cancelled or no-show appointment stays for the history, and releases its slot (spec 7.1).
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('specialist_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20);
            $table->string('source', 10)->default('staff');
            $table->string('cancel_reason', 255)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['device_id', 'starts_at', 'ends_at']);
            $table->index(['specialist_id', 'starts_at', 'ends_at']);
            $table->index(['room_id', 'starts_at', 'ends_at']);
            $table->index(['client_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
