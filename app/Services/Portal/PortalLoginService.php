<?php

namespace App\Services\Portal;

use App\Models\Client;
use App\Models\ClientLoginInfo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Customer-portal authentication. Faithful port of
 * Web\LoginController::login, with the two insecure fallbacks gated behind
 * config('portal.legacy_login_fallbacks').
 */
class PortalLoginService
{
    /**
     * @throws ValidationException on bad credentials or a cancelled policy
     */
    public function attempt(string $email, string $password, ?string $ip = null): Client
    {
        $email = strtolower(trim($email));
        $client = Client::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        $client = $this->matchPassword($client, $password)
            ?? $this->matchLegacyPolicyNumber($email, $password);

        if (! $client) {
            throw ValidationException::withMessages(['email' => __('Invalid email or password.')]);
        }

        if ((int) $client->login_off === 1) {
            throw ValidationException::withMessages(['email' => __('Your policy has been canceled.')]);
        }

        $this->recordLogin($client, $ip);
        Auth::guard('user')->login($client);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        return $client;
    }

    public function logout(): void
    {
        Auth::guard('user')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }

    private function matchPassword(?Client $client, string $password): ?Client
    {
        if (! $client || ! $client->password) {
            return null;
        }

        // Only run bcrypt verification when the stored value is actually a
        // hash — Laravel throws on a non-hash, and legacy rows may hold a
        // plaintext password.
        if ($this->isHashed($client->password) && Hash::check($password, $client->password)) {
            return $client;
        }

        // Legacy self-healing: the stored value was never hashed.
        if (config('portal.legacy_login_fallbacks')
            && hash_equals((string) $client->password, $password)) {
            $client->forceFill(['password' => Hash::make($password)])->save();

            return $client;
        }

        return null;
    }

    private function isHashed(string $value): bool
    {
        return password_get_info($value)['algo'] !== null
            && password_get_info($value)['algo'] !== 0;
    }

    private function matchLegacyPolicyNumber(string $email, string $password): ?Client
    {
        if (! config('portal.legacy_login_fallbacks')) {
            return null;
        }

        $policyNumber = str_contains($password, '-')
            ? $password
            : rtrim(implode('-', str_split($password, 3)), '-');

        return Client::whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->where('policy_number', $policyNumber)
            ->first();
    }

    private function recordLogin(Client $client, ?string $ip): void
    {
        $ipInfo = json_encode([
            'userIP' => $ip,
            'username' => trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: null,
        ]);

        $client->forceFill(['ip_info' => $ipInfo, 'last_login_at' => Carbon::now()])->save();

        ClientLoginInfo::create([
            'client_id' => $client->id,
            'ip_info' => $ipInfo,
            'last_login_at' => Carbon::now(),
        ]);
    }
}
