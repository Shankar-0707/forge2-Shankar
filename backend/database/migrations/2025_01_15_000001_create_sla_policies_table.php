<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();

            // Response time limits (minutes) per priority
            $table->unsignedInteger('low_response_minutes')->default(2880);    // 2 days
            $table->unsignedInteger('medium_response_minutes')->default(720);  // 12 hours
            $table->unsignedInteger('high_response_minutes')->default(240);    // 4 hours
            $table->unsignedInteger('urgent_response_minutes')->default(60);   // 1 hour

            // Resolution time limits (minutes) per priority
            $table->unsignedInteger('low_resolution_minutes')->default(10080);   // 7 days
            $table->unsignedInteger('medium_resolution_minutes')->default(4320); // 3 days
            $table->unsignedInteger('high_resolution_minutes')->default(1440);   // 1 day
            $table->unsignedInteger('urgent_resolution_minutes')->default(480);  // 8 hours

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
