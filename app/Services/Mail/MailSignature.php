<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailSignature.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Platform\{Organization, User};
use App\Settings\SettingsRegistry;

/**
 * Signatur und Fußzeile unter Mails, die eine Person aus WorkDiary schreibt
 * (Antwort aus der Notiz, Ticket-Antwort): die Signatur kommt aus der
 * Benutzerpräferenz `mail_signature` (ohne eigene: Name und Organisation),
 * die Fußzeile aus der Einstellung `communication.mail_footer` (Organisation
 * vor System). Beleg- und Systemmails haben eigene Vorlagen und bleiben außen vor.
 */
final class MailSignature {
    public const FOOTER_KEY = 'communication.mail_footer';

    public const PREFERENCE = 'mail_signature';

    public function __construct(private readonly SettingsRegistry $registry) {}

    public function signatureFor(User $user, ?Organization $organization = null): string {
        $own = trim((string) $user->getPreference(self::PREFERENCE, ''));
        if ($own !== '') {
            return $own;
        }
        $organization ??= $user->organization;

        return trim($user->name . "\n" . ($organization !== null ? trim((string) $organization->name) : ''));
    }

    public function footerFor(?Organization $organization): string {
        $value = $this->registry->effective(self::FOOTER_KEY, $organization)->value;

        return is_string($value) ? trim($value) : '';
    }

    /** Signatur, darunter die Fußzeile — als Textblock unter dem eigentlichen Text. */
    public function blockFor(User $user, ?Organization $organization = null): string {
        $organization ??= $user->organization;
        $footer = $this->footerFor($organization);

        return $this->signatureFor($user, $organization) . ($footer !== '' ? "\n\n" . $footer : '');
    }
}
