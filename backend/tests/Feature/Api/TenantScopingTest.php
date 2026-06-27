<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserBelongsToOrganization;
use App\Models\Organization;
use App\Models\User;
use App\Traits\ScopesByTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create a dummy model to exercise the trait against.
    if (! class_exists('App\Tests\Stubs\ScopedStub')) {
        eval(<<<'PHP'
            namespace App\Tests\Stubs;
            use Illuminate\Database\Eloquent\Model;
            class ScopedStub extends Model {
                protected $table = 'scoped_stubs';
                protected $guarded = [];
            }
        PHP);
    }
});

it('derives tenant id from the authenticated user', function (): void {
    $org  = Organization::factory()->create();
    $user = User::factory()->for($org)->create();

    $controller = new class {
        use ScopesByTenant;
    };

    $this->actingAs($user, 'sanctum');

    expect($controller->tenantId())->toBe($org->id);
});

it('returns 0 for tenant id when there is no authenticated user', function (): void {
    $controller = new class {
        use ScopesByTenant;
    };

    expect($controller->tenantId())->toBe(0);
});

it('attaches tenant id when creating attributes', function (): void {
    $org  = Organization::factory()->create();
    $user = User::factory()->for($org)->create();

    $controller = new class {
        use ScopesByTenant;
    };

    $this->actingAs($user, 'sanctum');

    $payload = $controller->withTenant(['name' => 'Acme Ticket']);

    expect($payload)
        ->toHaveKey('name', 'Acme Ticket')
        ->and($payload['organization_id'])->toBe($org->id);
});

it('aborts with 404 when a model does not belong to the tenant', function (): void {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user = User::factory()->for($orgA)->create();

    // Manually craft a model that belongs to org B.
    $foreignModel = new class extends Model {
        protected $guarded = [];
    };
    $foreignModel->setRawAttributes(['id' => 1, 'organization_id' => $orgB->id]);

    $controller = new class {
        use ScopesByTenant;
    };

    $this->actingAs($user, 'sanctum');

    $controller->ensureTenantOwnership($foreignModel);
})->throws(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

it('passes tenant ownership check when the model belongs to the user org', function (): void {
    $org  = Organization::factory()->create();
    $user = User::factory()->for($org)->create();

    $owned = new class extends Model {
        protected $guarded = [];
    };
    $owned->setRawAttributes(['id' => 1, 'organization_id' => $org->id]);

    $controller = new class {
        use ScopesByTenant;
    };

    $this->actingAs($user, 'sanctum');

    expect(fn () => $controller->ensureTenantOwnership($owned))->not->toThrow(\Throwable::class);
});

it('blocks unauthenticated requests to protected routes', function (): void {
    Route::middleware(['auth:sanctum', EnsureUserBelongsToOrganization::class])
        ->get('/v1/protected', fn () => response()->json(['ok' => true]));

    $this->getJson('/v1/protected')
        ->assertUnauthorized();
});

it('rejects authenticated users without an organization', function (): void {
    Route::middleware(['auth:sanctum', EnsureUserBelongsToOrganization::class])
        ->get('/v1/protected', fn () => response()->json(['ok' => true]));

    $user = User::factory()->create(['organization_id' => null]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/v1/protected')
        ->assertForbidden()
        ->assertJson(['message' => 'Account is not associated with an organization.']);
});

it('lets authenticated users with an organization through', function (): void {
    Route::middleware(['auth:sanctum', EnsureUserBelongsToOrganization::class])
        ->get('/v1/protected', fn () => response()->json(['ok' => true]));

    $org  = Organization::factory()->create();
    $user = User::factory()->for($org)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/v1/protected')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('exposes a public health endpoint', function (): void {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok');
});

it('exposes the authenticated user context at /me', function (): void {
    $org  = Organization::factory()->create(['name' => 'Acme Inc.']);
    $user = User::factory()->for($org)->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.name', 'Ada Lovelace')
        ->assertJsonPath('organization.name', 'Acme Inc.');
});
