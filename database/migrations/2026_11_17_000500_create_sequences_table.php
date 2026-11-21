<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named counters. client_code gives the sequential codes C-000001, C-000002 ... (spec section 6.1).
     */
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table): void {
            $table->string('name', 40)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        DB::table('sequences')->insert(['name' => 'client_code', 'value' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
