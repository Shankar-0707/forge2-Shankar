<?php

namespace App\Providers;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Unwrap JSON resource collections for flatter API responses
        JsonResource::withoutWrapping();

        // ──────────────────────────────────────────────
        // Global Organization Scope for Tickets
        // ──────────────────────────────────────────────
        // Every Ticket query is automatically scoped to the
        // authenticated user's organization_id. This ensures
        // strict multi-tenant isolation at the database layer.
        //
        // Use Ticket::withoutOrganization() to bypass when
        // running system-level tasks (e.g. console commands).
        // ──────────────────────────────────────────────

        Ticket::addGlobalScope('organization', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.organization_id',
                    auth()->user()->organization_id
                );
            }
        });
    }
}
