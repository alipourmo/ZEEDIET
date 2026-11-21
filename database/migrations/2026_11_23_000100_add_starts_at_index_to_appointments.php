<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Performance (spec section 12, S5-T6). The calendar and the day lists filter by starts_at over all devices and
// specialists. The existing indexes all start with device, specialist or room, so they cannot serve that range.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['starts_at']);
        });
    }
};
