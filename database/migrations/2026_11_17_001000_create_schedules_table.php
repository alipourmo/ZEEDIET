<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly working hours of a resource (spec section 6.2). weekday: 0 = Saturday ... 6 = Friday.
     * A resource with no rows is unavailable. Rows are replaced as a set, never patched one by one.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_type', 10);
            $table->unsignedBigInteger('resource_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['resource_type', 'resource_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
