<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments (spec section 6.5). A payment is append-only: it is never updated or deleted. A mistake is fixed by a
     * reversal, a new row with the negative amount and reverses_id set to the original (see Payment).
     *
     * amount is signed. Spec 6.5 says unsigned, but a reversal needs a negative amount (spec 8.2), so the column is
     * signed. A payment is always positive; the service enforces that.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('session_packages')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->string('method', 20);
            $table->dateTime('paid_at');
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            // Set on a reversing entry only. Unique, so one payment can be reversed once.
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'paid_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
