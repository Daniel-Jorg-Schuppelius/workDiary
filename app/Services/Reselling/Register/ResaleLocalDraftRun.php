<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleLocalDraftRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\{Customer, Invoice, Organization};
use App\Models\Reselling\ResalePeriod;
use App\Services\Finance\BillingModeResolver;
use App\Settings\SettingsRegistry;
use App\Support\OrganizationContext;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Cache, Log};
use RuntimeException;
use Throwable;

/**
 * Serienrechnung bei lokaler Rechnungshoheit (Feature 152, Restpunkt zu
 * MVP-764): je Rechnungsempfänger mit fälligen, noch nicht entworfenen
 * Perioden ein lokaler Rechnungsentwurf über den vorhandenen Entwurfspfad
 * ({@see ResaleInvoiceDraftService::draftLocal()}). Kein zweites
 * Serienrechnungs-Modul: der Entwurf ist derselbe wie per Klick, nur ohne
 * Nutzer und mit Stichtag inkl. Vorlauf. Empfänger mit externer Hoheit
 * (Lexoffice/DATEV/…) werden übersprungen — dort schiebt niemand automatisch.
 * Idempotent über den Perioden-Stempel (`draft_reference`).
 */
final class ResaleLocalDraftRun {
    public const SETTING_ENABLED = 'resale.auto_local_drafts';

    public const SETTING_LEAD_DAYS = 'resale.auto_local_drafts_lead_days';

    public const AUDIT_EVENT = 'invoice.resale_auto_drafted';

    private const LOCK_SECONDS = 300;

    public function __construct(
        private readonly ResaleInvoiceDraftService $drafts,
        private readonly BillingModeResolver $billingModes,
        private readonly SettingsRegistry $settings,
    ) {}

    /** Org-Schalter (Default aus). */
    public function enabledFor(Organization $organization): bool {
        return (bool) $this->settings->effective(self::SETTING_ENABLED, $organization)->value;
    }

    /** Vorlauf in Tagen: Perioden, die bis dahin beginnen, werden schon entworfen (0 = nur fällige). */
    public function leadDaysFor(Organization $organization): int {
        return max(0, (int) $this->settings->effective(self::SETTING_LEAD_DAYS, $organization)->value);
    }

    /**
     * Ein Lauf je Organisation. `$reference` = heute des Laufs (Tests),
     * `$dryRun` zählt nur. Fehler je Empfänger werden gefangen und gezählt;
     * „nichts offen" (nur preislose Perioden) ist übersprungen, kein Fehler.
     *
     * @return array{recipients: int, drafts: int, skipped: int, errors: list<string>}
     *
     * @throws RuntimeException wenn für die Organisation schon ein Lauf läuft
     */
    public function run(Organization $organization, ?CarbonImmutable $reference = null, bool $dryRun = false): array {
        $reference ??= ResalePeriod::today();
        $dueBy = $reference->addDays($this->leadDaysFor($organization));
        $result = ['recipients' => 0, 'drafts' => 0, 'skipped' => 0, 'errors' => []];

        $lock = Cache::lock('resale:draft-local:' . $organization->id, self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw new RuntimeException((string) __('resale.auto_draft.locked'));
        }

        try {
            OrganizationContext::run($organization, function () use ($organization, $dueBy, $dryRun, &$result): void {
                foreach ($this->recipients($dueBy) as $recipient) {
                    $result['recipients']++;
                    if ($this->billingModes->effectiveFor($recipient)->isExternal()) {
                        $result['skipped']++;

                        continue;
                    }

                    try {
                        if ($dryRun) {
                            $this->drafts->previewLocal($recipient, $dueBy);
                        } else {
                            $draft = $this->drafts->draftLocal($organization, $recipient, null, $dueBy);
                            $this->audit($organization, $recipient, $draft, $dueBy);
                        }
                        $result['drafts']++;
                    } catch (Throwable $e) {
                        // Genau RuntimeException = fachlich nichts zu entwerfen (Service-Sprache); alles andere ist ein Fehler.
                        if ($e::class === RuntimeException::class) {
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
     * Audit am Entwurf (Invoice ist Auditable; `created` schreibt das Modell
     * selbst) — der Serienlauf hinterlässt zusätzlich Quelle und Stichtag.
     *
     * @param  array{draft_id: string, lines: int, net: float, periods: int}  $draft
     */
    private function audit(Organization $organization, Customer $recipient, array $draft, CarbonImmutable $dueBy): void {
        $invoice = Invoice::query()->where('organization_id', $organization->id)->where('number', $draft['draft_id'])->first();
        $invoice?->audit('invoice.resale_auto_drafted', [  // Literal: Audit-Label-Sweep liest das Event hier
            'customer_id' => $recipient->id,
            'periods' => $draft['periods'],
            'net' => $draft['net'],
            'due_by' => $dueBy->toDateString(),
        ]);
    }
}
