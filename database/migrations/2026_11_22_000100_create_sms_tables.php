<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SMS templates and the SMS log (spec sections 6.5 and 9). A template's body is editable by the admin. The log
     * records every send attempt. For the otp template the stored body has the code redacted (spec 7.5).
     */
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sms_log', function (Blueprint $table): void {
            $table->id();
            $table->string('mobile', 11);
            $table->string('template_key', 40)->nullable();
            $table->text('body');
            $table->string('provider', 20);
            $table->string('provider_message_id', 100)->nullable();
            $table->string('status', 10);
            $table->string('error', 500)->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['template_key', 'related_type', 'related_id', 'created_at'], 'sms_log_dedupe_idx');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_log');
        Schema::dropIfExists('sms_templates');
    }
};
