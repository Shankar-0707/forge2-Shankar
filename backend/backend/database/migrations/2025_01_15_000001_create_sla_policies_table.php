<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent']);
            $table->unsignedInteger('response_time_limit')   // minutes
                  ->comment('Max time to first response (minutes)');
            $table->unsignedInteger('resolution_time_limit') // minutes
                  ->comment('Max time to resolution (minutes)');
            $table->boolean('is_active')->default(true);
            $table->boolean('business_hours_only')->default(false);
            $table->timestamps();

            // One policy per org per priority (NULL org = global default)
            $table->unique(['organization_id', 'priority'], 'uniq_org_priority');
            $table->index(['priority', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
