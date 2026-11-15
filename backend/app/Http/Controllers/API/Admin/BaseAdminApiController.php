<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared plumbing for the token-authenticated admin API.
 *
 * Endpoints mirror the Blade dashboard (routes/web.php) and delegate all
 * business rules to the same App\Services classes the dashboard uses.
 */
abstract class BaseAdminApiController extends BaseController
{
    /** Default page size used by the dashboard lists. */
    protected const DEFAULT_PER_PAGE = 15;

    protected const MAX_PER_PAGE = 100;

    /**
     * Resolve "?per_page=" while keeping it inside a sane range.
     */
    protected function perPage(Request $request, int $default = self::DEFAULT_PER_PAGE): int
    {
        $value = (int) $request->query('per_page', $default);

        if ($value < 1) {
            return $default;
        }

        return min(self::MAX_PER_PAGE, $value);
    }

    /**
     * Build a LIKE pattern, escaping wildcards so a literal "%" or "_" typed
     * into a search box is matched as text instead of as a wildcard.
     */
    protected function likeTerm(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }

    /**
     * Turn the {success, status, body} arrays returned by the admin services
     * into an HTTP response.
     *
     * @param  array{success: bool, status: int, body: array<string, mixed>}  $result
     */
    protected function serviceResponse(array $result): JsonResponse
    {
        return response()->json($result['body'], $result['status']);
    }

    /**
     * Single-resource payload.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extra
     */
    protected function itemResponse(array $data, string $message = '', int $status = 200, array $extra = []): JsonResponse
    {
        $payload = ['data' => $data] + $extra;

        if ($message !== '') {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }
}
