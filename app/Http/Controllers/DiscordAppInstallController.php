<?php

namespace App\Http\Controllers;

use App\Events\DiscordUserAppInstalled;
use App\Models\DiscordGuildIntegration;
use App\Models\DiscordUserIntegration;
use App\Models\UserOnboardingState;
use App\Services\AuditLogger;
use App\Support\Audit\AuditScope;
use App\Support\Audit\AuditSeverity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiscordAppInstallController extends Controller
{
    private const DISCORD_AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';

    private const DISCORD_TOKEN_URL = 'https://discord.com/api/oauth2/token';

    private const DISCORD_USER_URL = 'https://discord.com/api/users/@me';

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function redirectUserInstall(Request $request): RedirectResponse
    {
        $state = Str::random(40);

        $request->session()->put('discord_app_user_install_state', $state);
        $request->session()->put(
            'discord_app_user_install_return_to',
            $this->safeReturnUrl($request) ?? route('dashboard')
        );

        return redirect()->away($this->authorizeUrl([
            'redirect_uri' => config('services.discord_app.user_redirect'),
            'scope' => 'identify applications.commands',
            'state' => $state,
            'integration_type' => 1,
        ]));
    }

    public function callbackUserInstall(Request $request): RedirectResponse
    {
        $returnTo = $request->session()->pull('discord_app_user_install_return_to', route('dashboard'));

        if (! $this->stateMatches($request, 'discord_app_user_install_state')) {
            return redirect()
                ->to($returnTo)
                ->withErrors(['error' => 'discord_app_oauth_invalid_state']);
        }

        $discordUser = $this->fetchDiscordUser(
            code: (string) $request->query('code'),
            redirectUri: config('services.discord_app.user_redirect')
        );

        if (! $discordUser) {
            return redirect()
                ->to($returnTo)
                ->withErrors(['error' => 'discord_app_oauth_failed']);
        }

        $user = $request->user();
        $discordUserId = (string) $discordUser['id'];
        $conflictingIntegration = DiscordUserIntegration::query()
            ->where('discord_user_id', $discordUserId)
            ->where('user_id', '!=', $user->id)
            ->first();

        if ($conflictingIntegration) {
            return redirect()
                ->to($returnTo)
                ->withErrors(['error' => 'discord_app_already_linked']);
        }

        $integration = DiscordUserIntegration::query()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'discord_user_id' => $discordUserId,
            'username' => $discordUser['username'] ?? null,
            'global_name' => $discordUser['global_name'] ?? null,
            'avatar_url' => $this->avatarUrl($discordUser),
            'user_app_installed_at' => now(),
            'revoked_at' => null,
        ]);

        DiscordUserAppInstalled::dispatch($integration->id);

        $this->auditLogger->log(
            action: 'user.discord_app.user_installed',
            severity: AuditSeverity::INFO,
            scopeType: AuditScope::USER,
            scopeId: $user->id,
            message: 'audit_log.activity.user.discord_app.user_installed',
            actor: $user,
            subject: $user,
            metadata: [
                'discord_user_id' => $discordUserId,
            ],
        );

        $user->onboardingState()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'current_step' => UserOnboardingState::STEP_WELCOME,
        ])->update([
            'current_step' => UserOnboardingState::STEP_NOTIFICATIONS,
        ]);

        return redirect()
            ->to($returnTo)
            ->with('success', ['discord_app_user_installed']);
    }

    public function redirectGuildInstall(Request $request): RedirectResponse
    {
        $state = Str::random(40);

        $request->session()->put('discord_app_guild_install_state', $state);
        $request->session()->put(
            'discord_app_guild_install_return_to',
            $this->safeReturnUrl($request) ?? route('dashboard')
        );

        return redirect()->away($this->authorizeUrl([
            'redirect_uri' => config('services.discord_app.guild_redirect'),
            'scope' => 'identify bot applications.commands',
            'state' => $state,
            'integration_type' => 0,
            'permissions' => config('services.discord_app.guild_install_permissions'),
        ]));
    }

    public function callbackGuildInstall(Request $request): RedirectResponse
    {
        $returnTo = $request->session()->pull('discord_app_guild_install_return_to', route('dashboard'));

        if (! $this->stateMatches($request, 'discord_app_guild_install_state')) {
            return redirect()
                ->to($returnTo)
                ->withErrors(['error' => 'discord_app_oauth_invalid_state']);
        }

        $discordUser = $this->fetchDiscordUser(
            code: (string) $request->query('code'),
            redirectUri: config('services.discord_app.guild_redirect')
        );

        $guildId = $request->query('guild_id');

        if (! $discordUser || ! is_string($guildId) || blank($guildId)) {
            return redirect()
                ->to($returnTo)
                ->withErrors(['error' => 'discord_app_oauth_failed']);
        }

        DiscordGuildIntegration::query()->updateOrCreate([
            'discord_guild_id' => $guildId,
        ], [
            'installed_by_user_id' => $request->user()->id,
            'installed_by_discord_user_id' => (string) $discordUser['id'],
            'permissions' => is_scalar($request->query('permissions')) ? (string) $request->query('permissions') : null,
            'guild_installed_at' => now(),
            'removed_at' => null,
        ]);

        $this->auditLogger->log(
            action: 'user.discord_app.guild_installed',
            severity: AuditSeverity::INFO,
            scopeType: AuditScope::USER,
            scopeId: $request->user()->id,
            message: 'audit_log.activity.user.discord_app.guild_installed',
            actor: $request->user(),
            subject: $request->user(),
            metadata: [
                'discord_guild_id' => $guildId,
                'installed_by_discord_user_id' => (string) $discordUser['id'],
            ],
        );

        return redirect()
            ->to($returnTo)
            ->with('success', ['discord_app_guild_installed']);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function authorizeUrl(array $parameters): string
    {
        return self::DISCORD_AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.discord_app.client_id'),
            'response_type' => 'code',
            ...$parameters,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchDiscordUser(string $code, ?string $redirectUri): ?array
    {
        if (blank($code) || blank($redirectUri)) {
            return null;
        }

        $tokenResponse = Http::asForm()->post(self::DISCORD_TOKEN_URL, [
            'client_id' => config('services.discord_app.client_id'),
            'client_secret' => config('services.discord_app.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        if (! $tokenResponse->successful()) {
            return null;
        }

        $accessToken = $tokenResponse->json('access_token');

        if (! is_string($accessToken) || blank($accessToken)) {
            return null;
        }

        $userResponse = Http::withToken($accessToken)->get(self::DISCORD_USER_URL);

        if (! $userResponse->successful()) {
            return null;
        }

        $payload = $userResponse->json();

        return is_array($payload) && filled($payload['id'] ?? null)
            ? $payload
            : null;
    }

    private function stateMatches(Request $request, string $sessionKey): bool
    {
        $expectedState = $request->session()->pull($sessionKey);
        $actualState = $request->query('state');

        return is_string($expectedState)
            && is_string($actualState)
            && hash_equals($expectedState, $actualState);
    }

    private function safeReturnUrl(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || blank($referer)) {
            return null;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $refererHost = parse_url($referer, PHP_URL_HOST);

        return $appHost !== null && $refererHost === $appHost
            ? $referer
            : null;
    }

    /**
     * @param  array<string, mixed>  $discordUser
     */
    private function avatarUrl(array $discordUser): ?string
    {
        $userId = $discordUser['id'] ?? null;
        $avatar = $discordUser['avatar'] ?? null;

        if (! is_string($userId) || ! is_string($avatar) || blank($avatar)) {
            return null;
        }

        $extension = str_starts_with($avatar, 'a_') ? 'gif' : 'png';

        return "https://cdn.discordapp.com/avatars/{$userId}/{$avatar}.{$extension}";
    }
}
