<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The specialist's notes on a client (spec section 6.5). Append-only: a correction is a new note. The latest note's
     * next_follow_up_on is the client's next follow-up.
     */
    public function up(): void
    {
        Schema::create('specialist_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('specialist_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->date('next_follow_up_on')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'id']);
            $table->index('next_follow_up_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_notes');
    }
};
