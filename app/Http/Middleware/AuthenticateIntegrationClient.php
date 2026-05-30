<?php

namespace App\Http\Middleware;

use App\Models\IntegrationClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegrationClient
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || blank($token)) {
            abort(401);
        }

        $client = IntegrationClient::query()
            ->where('api_token_hash', IntegrationClient::hashApiToken($token))
            ->first();

        if (! $client?->isActive()) {
            abort(401);
        }

        foreach ($scopes as $scope) {
            if (! $client->hasScope($scope)) {
                abort(403);
            }
        }

        $client->forceFill(['last_api_used_at' => now()])->save();
        $request->attributes->set('integration_client', $client);

        return $next($request);
    }
}
