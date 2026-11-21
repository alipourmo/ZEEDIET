<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One value in a set (spec section 6.4). Deleted with its set, which is only possible when the set is removed by
     * the database itself, never by the application.
     */
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('measurement_set_id')->constrained('measurement_sets')->cascadeOnDelete();
            $table->foreignId('measurement_type_id')->constrained('measurement_types')->restrictOnDelete();
            $table->decimal('value', 6, 2);
            $table->timestamps();

            $table->unique(['measurement_set_id', 'measurement_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
