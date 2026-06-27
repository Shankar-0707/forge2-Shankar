<?php

use App\Http\Middleware\EnsureSameTenant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Define disposable probe routes inside the test application
    // so the middleware is exercised end-to-end without coupling
    // the test to the production route table.
    Route::middleware(['api', EnsureSameTenant::class])->group(function () {
        Route::get('/probe', fn () => response()->json(['ok' => true]));
        Route::get('/orgs/{organization}/probe', fn () => response()->json(['ok' => true]));
        Route::post('/probe', fn () => response()->json(['ok' => true]));
    });

    $this->org       = Organization::factory()->create();
    $this->otherOrg  = Organization::factory()->create();
    $this->user      = User::factory()->create([
        'organization_id' => $this->org->id,
    ]);
});

it('allows requests that carry no tenant identifier', function () {
    $this->actingAs($this->user)
        ->getJson('/probe')
        ->assertOk();
});

it('allows access to the users own organization via route binding', function () {
    $this->actingAs($this->user)
        ->getJson("/orgs/{$this->org->id}/probe")
        ->assertOk();
});

it('blocks cross-tenant access via route binding', function () {
    $this->actingAs($this->user)
        ->getJson("/orgs/{$this->otherOrg->id}/probe")
        ->assertForbidden();
});

it('blocks cross-tenant organization_id in query string', function () {
    $this->actingAs($this->user)
        ->getJson('/probe?organization_id='.$this->otherOrg->id)
        ->assertForbidden();
});

it('blocks cross-tenant organization_id in request body', function () {
    $this->actingAs($this->user)
        ->postJson('/probe', [
            'organization_id' => $this->otherOrg->id,
        ])
        ->assertForbidden();
});

it('blocks alternative tenant keys like tenant_id', function () {
    $this->actingAs($this->user)
        ->getJson('/probe?tenant_id='.$this->otherOrg->id)
        ->assertForbidden();
});

it('allows matching organization_id in request body', function () {
    $this->actingAs($this->user)
        ->postJson('/probe', [
            'organization_id' => $this->org->id,
        ])
        ->assertOk();
});

it('does not abort when user has no organization assigned', function () {
    $unscopedUser = User::factory()->create(['organization_id' => null]);

    $this->actingAs($unscopedUser)
        ->getJson('/probe')
        ->assertOk();
});

it('passes unauthenticated requests through to upstream auth', function () {
    // Without an authenticated user, the tenant guard intentionally
    // no-ops so the auth middleware can produce the proper 401.
    $this->getJson('/probe')->assertOk();
});
