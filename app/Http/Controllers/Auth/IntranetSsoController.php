<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Inicio de sesión único (SSO) proveniente de la intranet.
 *
 * La intranet firma un token HMAC-SHA256 con un secreto compartido
 * (INTRANET_SSO_SECRET / SEGUIMIENTO_SSO_SECRET) y redirige aquí. Se valida
 * la firma y la vigencia, se impide la reutilización (nonce en caché) y se
 * autentica al usuario en la sesión de seguimiento.
 */
class IntranetSsoController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $secret = (string) config('seguimiento.intranet_sso_secret');
        $token = (string) $request->query('token', '');

        [$payloadB64, $signature] = array_pad(explode('.', $token, 2), 2, null);

        if ($secret === '' || ! $payloadB64 || ! $signature) {
            abort(403, 'SSO no configurado.');
        }

        $expected = $this->base64UrlEncode(hash_hmac('sha256', $payloadB64, $secret, true));

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Firma SSO inválida.');
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);

        if (! is_array($payload) || empty($payload['uid']) || empty($payload['exp']) || (int) $payload['exp'] < time()) {
            abort(403, 'Token SSO inválido o expirado.');
        }

        $nonce = (string) ($payload['nonce'] ?? '');

        if ($nonce === '' || ! Cache::add('sso_nonce:'.$nonce, true, 600)) {
            abort(403, 'Token SSO ya utilizado.');
        }

        $user = User::find($payload['uid']);

        if (! $user || ! $user->isActive() || (isset($payload['email']) && $user->email !== $payload['email'])) {
            abort(403, 'Usuario SSO no válido.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->hasRole(RoleName::Admin->value)) {
            return redirect()->to('/admin');
        }

        return redirect()->to('/reunion');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
