<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityEventType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Security;

/**
 * Sicherheitsereignisse des zentralen Security-Logs (Feature 096, MVP-443).
 * Der Enum-Wert ist Teil des Log-Format-Vertrags mit den ausgelieferten
 * fail2ban-Filtern (deploy/fail2ban) — Werte nie umbenennen, nur ergänzen.
 */
enum SecurityEventType: string {
    case AuthFailed = 'auth.failed';
    case AuthLockout = 'auth.lockout';
    case TwoFactorFailed = 'auth.2fa_failed';
    case PasswordResetRequested = 'auth.password_reset_requested';
    case WbLoginFailed = 'wb.login_failed';
    case ApiTokenInvalid = 'api.token_invalid';
    case WebhookSignatureInvalid = 'webhook.signature_invalid';
    case SsoFailed = 'sso.failed';
    case TerminalBadgeUnknown = 'terminal.badge_unknown';
    case PlatformAdminIpBlocked = 'admin.ip_blocked';

    /** PSR-3-Level der Log-Zeile. */
    public function level(): string {
        return match ($this) {
            self::PasswordResetRequested => 'info',
            default => 'warning',
        };
    }

    /**
     * In `security_events` persistieren (MVP-445)? Hinweisgeber-Fehlversuche
     * bleiben BEWUSST nur in der rotierten Datei — Anonymitätsschutz HinSchG:
     * keine IP in Datenbank/Dashboard.
     */
    public function persist(): bool {
        return $this !== self::WbLoginFailed;
    }
}
