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
use Illuminate\Support\Facades\{Cache, Hash};
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

    /**
     * Wie {@see verify()}, aber einmalig je Nutzer: derselbe Zeitschritt wird
     * pro Nutzer nur EINMAL akzeptiert. Verhindert das Replay eines
     * abgefangenen TOTP-Codes innerhalb seines Gültigkeitsfensters (~90 s) an
     * der Login-Challenge (Whitebox-Befund 2026-07). `Cache::add` ist atomar:
     * existiert der Schlüssel bereits, war der Code schon in Gebrauch.
     */
    public function verifyForUser(User $user, string $secret, string $code): bool {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($code === '') {
            return false;
        }

        $timestep = $this->engine->verifyKey($secret, $code, 1);
        if ($timestep === false) {
            return false;
        }

        $key = '2fa:totp-used:' . $user->getKey() . ':' . $timestep;

        return Cache::add($key, true, now()->addSeconds(120));
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
     * Acht frische, einmalig nutzbare Recovery-Codes im KLARTEXT.
     * Nur zur einmaligen Anzeige – gespeichert wird ausschließlich der Hash
     * (siehe regenerateRecoveryCodes()).
     *
     * @return list<string>
     */
    public function newRecoveryCodes(): array {
        return array_map(
            static fn (): string => Str::lower(Str::random(5)) . '-' . Str::lower(Str::random(5)),
            range(1, 8)
        );
    }

    /**
     * Erzeugt neue Recovery-Codes, speichert sie GEHASHT (nicht reversibel) und
     * gibt den Klartext zur einmaligen Anzeige zurück. Bei DB-Leak sind die
     * Codes damit – anders als bei reversibler Verschlüsselung – nicht nutzbar.
     *
     * @return list<string> Klartext-Codes
     */
    public function regenerateRecoveryCodes(User $user): array {
        $plain = $this->newRecoveryCodes();
        $hashed = array_map(static fn (string $c): string => Hash::make($c), $plain);
        $user->forceFill(['two_factor_recovery_codes' => $hashed])->save();

        return $plain;
    }

    /**
     * Stellt sicher, dass Recovery-Codes existieren. Existieren bereits welche,
     * werden sie NICHT erneut ausgegeben (sie liegen nur gehasht vor).
     *
     * @return list<string> neu erzeugte Klartext-Codes oder leeres Array
     */
    public function ensureRecoveryCodes(User $user): array {
        if ((array) ($user->two_factor_recovery_codes ?? []) !== []) {
            return [];
        }

        return $this->regenerateRecoveryCodes($user);
    }

    /** Prüft einen Recovery-Code gegen die gehashten Codes – ohne ihn zu verbrauchen. */
    public function matchesRecoveryCode(User $user, string $input): bool {
        $input = trim($input);
        if ($input === '') {
            return false;
        }
        foreach ((array) ($user->two_factor_recovery_codes ?? []) as $hash) {
            if (is_string($hash) && Hash::check($input, $hash)) {
                return true;
            }
        }

        return false;
    }

    /** Prüft und VERBRAUCHT einen Recovery-Code (einmalig). */
    public function consumeRecoveryCode(User $user, string $input): bool {
        $input = trim($input);
        if ($input === '') {
            return false;
        }
        $codes = (array) ($user->two_factor_recovery_codes ?? []);
        foreach ($codes as $i => $hash) {
            if (is_string($hash) && Hash::check($input, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
