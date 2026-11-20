<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Device sessions (spec section 6.3). One row per treatment on a device. Never deleted.
     *
     * The unique key (client, device, session_number) is the last line of defence for the numbering rule in
     * SessionRecorder: two sessions for the same client and device can never share a number.
     */
    public function up(): void
    {
        Schema::create('device_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('session_packages')->restrictOnDelete();
            $table->foreignId('device_id')->constrained('devices')->restrictOnDelete();
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('session_number');
            $table->string('status', 20);
            $table->json('parameters')->nullable();
            $table->text('note')->nullable();
            $table->dateTime('performed_at');
            $table->timestamps();

            $table->unique(['client_id', 'device_id', 'session_number']);
            $table->index(['package_id', 'status']);
            $table->index(['operator_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
    }
};
