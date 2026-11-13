<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiConnectionPrivacy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\AiApprovalState;
use App\Models\Ai\AiProviderConnection;
use App\Models\Platform\{Organization, User};
use App\Settings\SettingsRegistry;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Datenschutz der KI-Verbindungen (Feature 025, MVP-1671/1672): Freigabe je
 * Verbindung, Freigabepflicht und Frist der Organisation, Maskierung lokaler
 * Verbindungen.
 *
 * Freigegeben ist eine Verbindung, wenn jemand mit `ai.approve` sie geprüft
 * hat und die Angaben vollständig sind: Verarbeitung in der EU, keine
 * Trainingsnutzung und — bei Cloud-Verbindungen — ein Auftragsverarbeitungs-
 * vertrag mit Dokument (das Dokument prüft die Freigabe beim Erteilen). Eine
 * lokale Verbindung hat keinen Auftragsverarbeiter, die Prüfung bestätigt dort
 * den Betrieb im eigenen Haus. Die Freigabe verfällt nach
 * `ai.privacy.approval_months` Monaten; {@see REMINDER_DAYS} Tage vorher
 * erinnert der Arbeitsvorrat.
 *
 * Die Einstellungen werden org-explizit gelesen (auch in Queue-Jobs ohne
 * gebundene Organisation). `ai.privacy.require_approval` ist für neue
 * Organisationen an (config), Bestandsorganisationen hat die Migration
 * `2027_04_18_500100` ausdrücklich auf aus gesetzt.
 */
final class AiConnectionPrivacy {
    public const string SETTING_REQUIRE_APPROVAL = 'ai.privacy.require_approval';

    public const string SETTING_APPROVAL_MONTHS = 'ai.privacy.approval_months';

    public const string SETTING_MASK_LOCAL = 'ai.privacy.mask_local';

    /** Tage vor dem Ablauf, ab denen der Arbeitsvorrat an die neue Prüfung erinnert. */
    public const int REMINDER_DAYS = 30;

    /**
     * Angaben, auf die sich eine Freigabe bezieht: wohin die Daten gehen
     * (Adresse, Anbieter, Familie, lokal oder Cloud), welches Modell sie
     * verarbeitet und mit welchem Zugang. Ändert sich eine davon, gilt die
     * Prüfung nicht mehr ({@see resetAfterChange()}); der Name gehört nicht dazu.
     *
     * @var list<string>
     */
    public const array REVIEWED_ATTRIBUTES = ['base_url', 'model', 'api_key', 'family', 'provider', 'is_local'];

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function requiresApproval(Organization $organization): bool {
        return (bool) $this->settings->effective(self::SETTING_REQUIRE_APPROVAL, $organization)->value;
    }

    public function approvalMonths(Organization $organization): int {
        return max(1, (int) $this->settings->effective(self::SETTING_APPROVAL_MONTHS, $organization)->value);
    }

    /** Maskiert die Organisation auch vor lokalen Verbindungen? */
    public function masksLocal(Organization $organization): bool {
        return (bool) $this->settings->effective(self::SETTING_MASK_LOCAL, $organization)->value;
    }

    /** Maskierung für diese Verbindung: Cloud immer, lokal nach Einstellung der Organisation. */
    public function masksFor(Organization $organization, AiProviderConnection $connection): bool {
        return $connection->isCloud() || $this->masksLocal($organization);
    }

    public function expiresAt(AiProviderConnection $connection, Organization $organization): ?CarbonImmutable {
        return $connection->privacy_reviewed_at === null
            ? null
            : CarbonImmutable::instance($connection->privacy_reviewed_at)->addMonthsNoOverflow($this->approvalMonths($organization));
    }

    public function state(AiProviderConnection $connection, Organization $organization, ?CarbonImmutable $now = null): AiApprovalState {
        $expiresAt = $this->expiresAt($connection, $organization);
        if ($expiresAt === null || $this->missingConfirmations($connection) !== []) {
            return AiApprovalState::Missing;
        }

        $now ??= CarbonImmutable::now();

        return match (true) {
            $expiresAt->lte($now) => AiApprovalState::Expired,
            $expiresAt->lte($now->addDays(self::REMINDER_DAYS)) => AiApprovalState::Expiring,
            default => AiApprovalState::Approved,
        };
    }

    /**
     * Fehlende Angaben für eine Freigabe — Schlüssel unter `ai.privacy.confirm.*`.
     *
     * @return list<'dpa'|'eu'|'training'>
     */
    public function missingConfirmations(AiProviderConnection $connection): array {
        $missing = [];
        if ($connection->isCloud() && ! $connection->has_dpa) {
            $missing[] = 'dpa';
        }
        if (! $connection->is_eu_processing) {
            $missing[] = 'eu';
        }
        if (! $connection->is_training_excluded) {
            $missing[] = 'training';
        }

        return $missing;
    }

    /** Darf die Verbindung nach der Freigabepflicht der Organisation genutzt werden? */
    public function permits(AiProviderConnection $connection, Organization $organization): bool {
        return ! $this->requiresApproval($organization) || $this->state($connection, $organization)->isApproved();
    }

    /**
     * Warum die Freigabepflicht die Verbindung sperrt — null, wenn sie genutzt
     * werden darf. Steht im Vortest und in der Meldung des Routings.
     */
    public function blockingReason(AiProviderConnection $connection, Organization $organization): ?string {
        if ($this->permits($connection, $organization)) {
            return null;
        }

        $expiresAt = $this->expiresAt($connection, $organization);

        return $this->state($connection, $organization) === AiApprovalState::Expired && $expiresAt !== null
            ? (string) __('ai.privacy.reason.expired', ['date' => $expiresAt->isoFormat('L')])
            : (string) __('ai.privacy.reason.missing');
    }

    /**
     * Erteilt die Freigabe. Die Angaben müssen vollständig sein; den AVV als
     * Dokument verlangt der Aufrufer, bevor er freigibt. Auditiert über das
     * Modell (Ereignis `ai.connection_approved`).
     */
    public function approve(AiProviderConnection $connection, User $reviewer, bool $hasDpa, bool $isEuProcessing, bool $isTrainingExcluded): void {
        $connection->forceFill([
            'has_dpa' => $hasDpa,
            'is_eu_processing' => $isEuProcessing,
            'is_training_excluded' => $isTrainingExcluded,
        ]);
        $missing = $this->missingConfirmations($connection);
        if ($missing !== []) {
            throw new InvalidArgumentException('Freigabe ohne vollständige Angaben: ' . implode(', ', $missing));
        }

        $connection->forceFill([
            'privacy_reviewed_at' => CarbonImmutable::now(),
            'privacy_reviewed_by' => $reviewer->id,
        ])->save();
    }

    /**
     * Setzt die Freigabe zurück, wenn das zuletzt gespeicherte Ändern der
     * Verbindung eine geprüfte Angabe getroffen hat ({@see REVIEWED_ATTRIBUTES})
     * — eine neue Adresse oder ein anderer Schlüssel ist ein anderer Empfänger
     * der Daten. Direkt nach dem `save()` aufrufen, bevor ein weiteres
     * Speichern (etwa der Vortest) die Änderungsliste ersetzt. Auditiert wie
     * der Widerruf; das vorausgehende `updated` nennt die geänderten Angaben.
     *
     * @return bool ob die Freigabe zurückgesetzt wurde
     */
    public function resetAfterChange(AiProviderConnection $connection): bool {
        if ($connection->privacy_reviewed_at === null || ! $connection->wasChanged(self::REVIEWED_ATTRIBUTES)) {
            return false;
        }

        $this->revoke($connection);

        return true;
    }

    /** Widerruft die Freigabe; die Angaben bleiben als Stand der letzten Prüfung stehen (Ereignis `ai.connection_approval_revoked`). */
    public function revoke(AiProviderConnection $connection): void {
        $connection->forceFill([
            'privacy_reviewed_at' => null,
            'privacy_reviewed_by' => null,
        ])->save();
    }
}
