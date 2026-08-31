<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserAnonymizationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\{Attachment, User};
use App\Services\Attachments\ImageMetaUploader;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Anonymisierung ausgeschiedener Mitarbeiter (Feature 130, MVP-694 — H21,
 * Folge-Punkt aus Feature 126/MVP-689): Der User-Datensatz bleibt bestehen
 * (Arbeitszeit-/Lohn-Nachweise der RETENTION_FK_TABLES hängen per RESTRICT-FK
 * daran), sein Personenbezug wird auf das Minimum reduziert. Aufgerufen NUR
 * aus der bestätigten Review-Entscheidung des Retention-Flows
 * (employee_records-Policy, approve → purge) — nie vollautomatisch.
 *
 * Encrypted-Casts werden auf NULL gesetzt, nie auf "" (Memory „Leere
 * encrypted-Strings"). Bewusst erhalten: personnel_number (pseudonymer
 * Anker für Lohn-Nachweise), Beschäftigungs-/Vergütungsmerkmale
 * (Interpretation der Nachweise) und left_at/deactivated_at.
 */
class UserAnonymizationService {
    public function __construct(private readonly ImageMetaUploader $imageUploader) {}

    /** Idempotent; wirft bei aktiven Konten oder fehlendem Austrittsdatum. */
    public function anonymize(User $member, User $actor): void {
        if ($member->anonymized_at !== null) {
            return; // bereits anonymisiert — kein Doppel-Audit
        }
        if (! $member->isDeactivated()) {
            throw new RuntimeException('Konto ist noch aktiv — Anonymisierung setzt den vollzogenen Austritt (Deaktivierung) voraus.');
        }
        $leftAt = $member->left_at;
        if ($leftAt === null) {
            throw new RuntimeException('Kein Austrittsdatum (left_at) — Anonymisierung nur für ausgeschiedene Mitarbeiter.');
        }
        // Personalakte (Feature 141) blockt: Die Akte trägt eigene, teils
        // längere Fristen und braucht den Personenbezug — erst über den
        // Bereich personnel_files vernichten, dann anonymisieren (Muster
        // „Strukturdaten zuerst bereinigen" der customer_master-Policy).
        $openFiles = app(\App\Services\Hr\PersonnelFileService::class)->openDocumentCount($member);
        if ($openFiles > 0) {
            throw new RuntimeException("Personalakte mit {$openFiles} Dokument(en) vorhanden — zuerst vernichten (Bereich Personalakten), dann anonymisieren.");
        }

        // Kontakt-Morphs (Adressen/Bankverbindungen) vollständig entfernen.
        $member->addresses()->delete();
        $member->bankAccounts()->delete();

        // Avatar/Foto inkl. abgelegter Datei.
        $this->imageUploader->delete($member, Attachment::META_AVATAR);

        // 2FA-Methoden (WebAuthn/TOTP/Mail-OTP) des toten Kontos entfernen.
        $member->twoFactorCredentials()->delete();

        // SMS-Opt-in (Feature 147): die Einwilligung samt Rufnummern-Hash geht
        // mit dem Konto — sonst bliebe ein Personenbezug in der Präferenz-Bag.
        $preferences = (array) ($member->preferences ?? []);
        if (isset($preferences['notifications']) && is_array($preferences['notifications'])) {
            $preferences['notifications'] = array_diff_key(
                $preferences['notifications'],
                array_flip(['sms_opt_in', 'sms_number_hash', 'sms_verified_at']),
            );
        }

        $member->forceFill([
            'preferences' => $preferences === [] ? null : $preferences,
            'name' => sprintf('Ausgeschiedene:r Mitarbeiter:in #%d', $member->id),
            'first_name' => null,
            'middle_names' => null,
            'last_name' => null,
            // users.email ist unique — deterministische, unzustellbare Adresse.
            'email' => sprintf('anonymisiert-%d@example.invalid', $member->id),
            'email_verified_at' => null,
            'phone' => null,
            'mobile' => null,
            'fax' => null,
            'tax_identification_number' => null,
            'social_security_number' => null,
            'date_of_birth' => null,
            'health_insurance' => null,
            'home_address' => null,
            'home_lat' => null,
            'home_lng' => null,
            'cti_extension' => null,
            'cti_extension_hash' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'calendar_feed_token_hash' => null,
            'portal_invite_token_hash' => null,
            'portal_invite_expires_at' => null,
            'portal_invited_at' => null,
            // Zugangsreste rotieren ('hashed'-Cast hasht das Zufallspasswort).
            'password' => Str::random(48),
            'remember_token' => Str::random(60),
            'anonymized_at' => now(),
        ])->save();

        $member->audit('user.anonymized', [
            'reason' => 'retention',
            'by_user_id' => $actor->id,
            'left_at' => $leftAt->toDateString(),
        ]);
    }
}
