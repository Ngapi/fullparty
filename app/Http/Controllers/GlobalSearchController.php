<?php

namespace App\Http\Controllers;

use App\Services\Search\GlobalSearchService;
use App\Support\Input\RequestTextInputSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $globalSearchService,
        private readonly RequestTextInputSanitizer $requestTextInputSanitizer,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->requestTextInputSanitizer->sanitize($request, ['query']);

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(
            $this->globalSearchService->searchForUser(
                $request->user(),
                (string) ($validated['query'] ?? ''),
            )
        );
    }
}
