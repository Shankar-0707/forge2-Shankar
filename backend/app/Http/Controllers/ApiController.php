<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Traits\ScopesByTenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;

/**
 * Base API controller for all PulseDesk authenticated endpoints.
 *
 * - Extends Illuminate\Routing\Controller (Laravel 11 slim base)
 * - Carries the ScopesByTenant trait for org-scoped queries
 * - Provides consistent JSON response helpers
 */
abstract class ApiController extends Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
    use ScopesByTenant;

    /**
     * Standard success envelope.
     */
    protected function ok(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $headers = [],
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($data instanceof JsonResource) {
            $payload['data'] = $data->resolve();
        } elseif ($data instanceof ResourceCollection) {
            $payload['data'] = $data->resolve();

            if (($resource = $data->resource) instanceof AbstractPaginator) {
                $payload['meta'] = [
                    'current_page' => $resource->currentPage(),
                    'from'         => $resource->firstItem(),
                    'last_page'    => $resource->lastPage(),
                    'per_page'     => $resource->perPage(),
                    'to'           => $resource->lastItem(),
                    'total'        => $resource->total(),
                ];
                $payload['links'] = [
                    'first' => $resource->url(1),
                    'last'  => $resource->url($resource->lastPage()),
                    'prev'  => $resource->previousPageUrl(),
                    'next'  => $resource->nextPageUrl(),
                ];
            }
        } elseif (is_iterable($data) && ! is_associative($data)) {
            $payload['data'] = array_values($data);
        } elseif (null !== $data) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status, $headers);
    }

    /**
     * Resource-created response.
     */
    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    /**
     * No-content success response.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Standard error envelope.
     */
    protected function error(
        string $message = 'An error occurred',
        int $status = 400,
        array|MessageBag $errors = [],
        array $headers = [],
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($errors instanceof MessageBag) {
            $errors = $errors->toArray();
        }

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, $headers);
    }

    /**
     * Validation-failure response.
     */
    protected function invalid(array|MessageBag $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }
}

/**
 * Tiny helper — is an array associative?
 */
function is_associative(mixed $arr): bool
{
    if (! is_array($arr) || [] === $arr) {
        return false;
    }

    return array_keys($arr) !== range(0, count($arr) - 1);
}
