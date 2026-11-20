<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One dated set of measurements, one visit's report (spec section 6.4). A published set is immutable: a correction
     * is a new set with supersedes_id, and the old set gets superseded_at when the correction is published.
     */
    public function up(): void
    {
        Schema::create('measurement_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->date('measured_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('measurement_sets')->restrictOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status', 'superseded_at']);
            $table->index(['recorded_by', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_sets');
    }
};
