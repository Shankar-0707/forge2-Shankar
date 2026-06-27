<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->longText('description')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'priority']);
            $table->index('requester_id');
            $table->index('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
