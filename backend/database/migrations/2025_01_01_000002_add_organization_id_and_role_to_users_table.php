<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->enum('role', ['admin', 'agent', 'customer'])
                ->default('customer')
                ->after('organization_id');

            $table->index(['organization_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'role']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'role']);
        });
    }
};
