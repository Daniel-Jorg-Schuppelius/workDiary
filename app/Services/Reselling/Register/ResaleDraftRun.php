<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleDraftRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\BillingTransfer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Models\Reselling\ResalePeriod;
use App\Services\Billing\BillingModeResolver;
use App\Settings\SettingsRegistry;
use App\Support\OrganizationContext;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Cache, Log};
use RuntimeException;
use Throwable;

/**
 * Serienrechnung des Registers (Feature 152, Restpunkt zu MVP-764; Feature
 * 185, MVP-1438): je Rechnungsempfänger mit fälligen, noch nicht entworfenen
 * Perioden ein Entwurf über den vorhandenen Entwurfspfad — bei lokaler
 * Rechnungshoheit der lokale Rechnungsentwurf (täglich), bei externer Hoheit
 * die Übergabe an das Buchhaltungsprogramm des Empfängers (monatlich, mit
 * Vorlauf; Entscheidung des Inhabers 2026-10-30: Serienvorlagen in Lexoffice
 * bleiben führend, das Register entwirft nur für Abos ohne Vorlage). Kein
 * zweites Serienrechnungs-Modul: der Entwurf ist derselbe wie per Klick, nur
 * ohne Nutzer und mit Stichtag. Je Hoheit ein eigener Schalter und Vorlauf;
 * ein Lauf bedient eine Hoheit und überspringt die andere. Idempotent über
 * den Perioden-Stempel (`draft_reference`).
 */
final class ResaleDraftRun {
    public const SETTING_ENABLED = 'resale.auto_local_drafts';

    public const SETTING_LEAD_DAYS = 'resale.auto_local_drafts_lead_days';

    public const SETTING_EXTERNAL_ENABLED = 'resale.auto_external_drafts';

    public const SETTING_EXTERNAL_LEAD_DAYS = 'resale.auto_external_drafts_lead_days';

    public const AUDIT_EVENT = 'invoice.resale_auto_drafted';

    private const LOCK_SECONDS = 300;

    public function __construct(
        private readonly ResaleInvoiceDraftService $drafts,
        private readonly BillingModeResolver $billingModes,
        private readonly SettingsRegistry $settings,
    ) {}

    /** Org-Schalter je Hoheit (Default aus). */
    public function enabledFor(Organization $organization, bool $external = false): bool {
        return (bool) $this->settings->effective($external ? self::SETTING_EXTERNAL_ENABLED : self::SETTING_ENABLED, $organization)->value;
    }

    /** Vorlauf in Tagen: Perioden, die bis dahin beginnen, werden schon entworfen (0 = nur fällige). */
    public function leadDaysFor(Organization $organization, bool $external = false): int {
        return max(0, (int) $this->settings->effective($external ? self::SETTING_EXTERNAL_LEAD_DAYS : self::SETTING_LEAD_DAYS, $organization)->value);
    }

    /** Entwirft der Serienlauf die Rechnungen dieses Empfängers — lokal oder als Übergabe an sein Programm (MVP-1439)? */
    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        return $this->enabledFor($organization, $this->billingModes->effectiveFor($recipient)->isExternal());
    }

    /**
     * Ein Lauf je Organisation und Hoheit. `$reference` = heute des Laufs
     * (Tests), `$dryRun` zählt nur, `$external` = Empfänger mit externer
     * Hoheit über die Übergabe statt der lokalen. Fehler je Empfänger werden
     * gefangen und gezählt; „nichts offen" (nur preislose Perioden) und
     * Empfänger der anderen Hoheit sind übersprungen, kein Fehler.
     *
     * @return array{recipients: int, drafts: int, skipped: int, errors: list<string>}
     *
     * @throws RuntimeException wenn für die Organisation schon ein Lauf dieser Hoheit läuft
     */
    public function run(Organization $organization, ?CarbonImmutable $reference = null, bool $dryRun = false, bool $external = false): array {
        $reference ??= ResalePeriod::today();
        $dueBy = $reference->addDays($this->leadDaysFor($organization, $external));
        $result = ['recipients' => 0, 'drafts' => 0, 'skipped' => 0, 'errors' => []];

        $lock = Cache::lock('resale:draft-' . ($external ? 'external' : 'local') . ':' . $organization->id, self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw new RuntimeException((string) __('resale.auto_draft.locked'));
        }

        try {
            OrganizationContext::run($organization, function () use ($organization, $dueBy, $dryRun, $external, &$result): void {
                foreach ($this->recipients($dueBy) as $recipient) {
                    $result['recipients']++;
                    if ($this->billingModes->effectiveFor($recipient)->isExternal() !== $external) {
                        $result['skipped']++;

                        continue;
                    }

                    try {
                        if ($dryRun) {
                            $this->drafts->preview($recipient, $dueBy);
                        } else {
                            $draft = $external
                                ? $this->drafts->draft($organization, $recipient, null, $dueBy)
                                : $this->drafts->draftLocal($organization, $recipient, null, $dueBy);
                            $this->audit($organization, $recipient, $draft, $dueBy);
                        }
                        $result['drafts']++;
                    } catch (Throwable $e) {
                        // Genau RuntimeException ohne Ursache = fachlich nichts zu entwerfen (Service-Sprache);
                        // mit Ursache (Übergabe gescheitert, Kontakt fehlt) und alles andere ist ein Fehler.
                        if ($e::class === RuntimeException::class && $e->getPrevious() === null) {
                            $result['skipped']++;

                            continue;
                        }
                        $result['errors'][] = $recipient->name . ': ' . $e->getMessage();
                        Log::warning('resale: Serienrechnung fehlgeschlagen', ['organization_id' => $organization->id, 'customer_id' => $recipient->id, 'exception' => $e::class, 'message' => $e->getMessage()]);
                    }
                }
            });
        } finally {
            $lock->release();
        }

        return $result;
    }

    /**
     * Rechnungsempfänger mit fälligen, ungestempelten Perioden fremder Halter
     * — dieselbe Auswahl wie {@see ResaleInvoiceDraftService::openPeriodsFor()},
     * hier einmal über alle Empfänger (Kunde direkt oder Partner des Fremdkunden).
     *
     * @return list<Customer>
     */
    private function recipients(CarbonImmutable $dueBy): array {
        $periods = ResalePeriod::query()
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->whereNull('draft_reference')
            ->where('starts_on', '<', DateRange::dayAfter($dueBy))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false))
            ->with(['subscription.customer', 'subscription.foreignCustomer.customer'])
            ->get();

        /** @var array<int, Customer> $recipients */
        $recipients = [];
        foreach ($periods as $period) {
            $recipient = $period->subscription->billedTo();
            if ($recipient !== null) {
                $recipients[$recipient->id] ??= $recipient;
            }
        }
        ksort($recipients);

        return array_values($recipients);
    }

    /**
     * Audit am Entwurf (Invoice und Übergabe sind Auditable; `created`
     * schreibt das Modell selbst) — der Serienlauf hinterlässt zusätzlich
     * Quelle und Stichtag.
     *
     * @param  array{draft_id: string, lines: int, net: float, periods: int, transfer_id?: int}  $draft
     */
    private function audit(Organization $organization, Customer $recipient, array $draft, CarbonImmutable $dueBy): void {
        $subject = isset($draft['transfer_id'])
            ? BillingTransfer::query()->where('organization_id', $organization->id)->whereKey($draft['transfer_id'])->first()
            : Invoice::query()->where('organization_id', $organization->id)->where('number', $draft['draft_id'])->first();
        $subject?->audit('invoice.resale_auto_drafted', [  // Literal: Audit-Label-Sweep liest das Event hier
            'customer_id' => $recipient->id,
            'periods' => $draft['periods'],
            'net' => $draft['net'],
            'due_by' => $dueBy->toDateString(),
        ]);
    }
}
