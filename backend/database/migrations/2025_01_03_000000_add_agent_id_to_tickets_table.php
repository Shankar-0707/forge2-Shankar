<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('agent_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['organization_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'agent_id']);
            $table->dropForeign(['agent_id']);
            $table->dropColumn('agent_id');
        });
    }
};
