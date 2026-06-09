<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * Kapselt TOTP (RFC 6238) via google2fa: Secret-Erzeugung, Verifikation,
 * QR-Code (otpauth) und einmalige Recovery-Codes.
 */
class TwoFactorService {
    public function __construct(private readonly Google2FA $engine) {}

    public function generateSecret(): string {
        return $this->engine->generateSecretKey();
    }

    /** Prüft einen 6-stelligen TOTP-Code (mit ±1 Zeitfenster Toleranz). */
    public function verify(string $secret, string $code): bool {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($code === '') {
            return false;
        }

        // verifyKey() liefert bei Treffer den Zeitschritt (int), sonst false.
        return $this->engine->verifyKey($secret, $code, 1) !== false;
    }

    /** otpauth://-URI für Authenticator-Apps (TOTP, RFC 6238). */
    public function otpauthUri(User $user, string $secret): string {
        $issuer = (string) config('app.name', 'WorkDiary');
        $label = $issuer . ':' . ($user->email ?: ('user-' . $user->getKey()));

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($label),
            $secret,
            rawurlencode($issuer),
        );
    }

    /**
     * ROHES Inline-SVG (kein data:-URI) des QR-Codes – direkt per {!! !!}
     * einbettbar, CSP-sicher und skalierbar.
     */
    public function qrSvg(User $user, string $secret, int $size = 220): string {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString($this->otpauthUri($user, $secret));

        // writeString stellt eine XML-Deklaration voran; fuer sauberes
        // Inline-Einbetten entfernen wir sie.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }

    /**
     * Acht frische, einmalig nutzbare Recovery-Codes (Klartext zur Anzeige;
     * at-rest über den encrypted-Cast der Spalte verschlüsselt).
     *
     * @return list<string>
     */
    public function newRecoveryCodes(): array {
        return array_map(
            static fn (): string => Str::lower(Str::random(5)) . '-' . Str::lower(Str::random(5)),
            range(1, 8)
        );
    }
}
