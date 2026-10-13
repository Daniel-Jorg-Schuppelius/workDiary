<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormatOverview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Numbering;

use App\Enums\Numbering\NumberScope;
use App\Models\Customer\Customer;
use App\Models\Numbering\NumberFormat;
use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use App\Plugins\Contracts\ContactNumberPreview;
use App\Plugins\PluginManager;
use App\Services\Numbering\Contracts\BillingRouteNumbers;

/**
 * Zeilen der Seite „Nummernkreise“ (MVP-1149): wer vergibt, ob ein Programm
 * das Format festlegt (dann gesperrt) und was statt der Vorschau erscheint.
 * Rechnung, Gutschrift und Storno vergibt bei externem Fakturierungsweg das
 * Programm des Wegs (Feature 045), solange kein Kunde lokal abrechnet.
 */
class NumberFormatOverview {
    /** Kreise, deren Nummer der Fakturierungsweg vergibt. */
    public const BILLING_SCOPES = [NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation];

    /** Kreise, deren vergebende Stelle auf der Seite gewählt wird (MVP-1130, MVP-1160, MVP-1164, MVP-1165). */
    public const SELECTABLE_SOURCE_SCOPES = [
        NumberScope::Customer, NumberScope::Supplier, NumberScope::Quote, NumberScope::OrderConfirmation, NumberScope::DeliveryNote,
        NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation,
    ];

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly NumberAuthority $authority,
        private readonly BillingRouteNumbers $billingRoute,
    ) {}

    /**
     * @return list<array{scope: NumberScope, format: NumberFormat, persisted: bool, source: string, sourceOptions: array<string, string>|null, issuer: string, issuerNotes: list<string>, lockedBy: string|null, preview: string|null, details: list<string>}>
     */
    public function rows(Organization $organization): array {
        $program = $this->billingRoute->externalProgram($organization);
        $deviations = $this->billingRoute->deviations($organization);
        $locks = $this->locks($organization, $program, $deviations);
        $notes = array_map(
            static fn (array $deviation): string => trans_choice('numbering.source.deviation', $deviation['count'], ['program' => $deviation['program'], 'count' => $deviation['count']]),
            $deviations,
        );

        $rows = [];
        foreach (NumberScope::cases() as $scope) {
            $format = $this->numbers->resolveFormat((int) $organization->id, $scope);
            $source = $this->authority->sourceFor($organization, $scope);
            $lockedBy = $locks[$scope->value] ?? null;
            $billing = in_array($scope, self::BILLING_SCOPES, true);

            $rows[] = [
                'scope' => $scope,
                'format' => $format,
                'persisted' => $format->exists,
                'source' => $this->authority->configuredSource($organization, $scope),
                'sourceOptions' => in_array($scope, self::SELECTABLE_SOURCE_SCOPES, true)
                    ? $this->authority->sourceOptions($organization, $scope)
                    : null,
                'issuer' => $billing && $source === NumberAuthority::SOURCE_LOCAL && $program !== null
                    ? (string) __('numbering.source.billing', ['program' => $program])
                    : $this->authority->sourceLabel($source),
                'issuerNotes' => $billing ? $notes : [],
                'lockedBy' => $lockedBy,
                'preview' => $lockedBy === null ? $this->numbers->peekNext($organization, $scope) : null,
                'details' => $this->details($organization, $scope, $source, $lockedBy),
            ];
        }

        return $rows;
    }

    /**
     * Kreise, deren Format ein Programm festlegt (E6): Formatfelder gesperrt,
     * Sammeländerungen lassen sie aus.
     *
     * @return array<string, string> Kreis => Programmname
     */
    public function lockedScopes(Organization $organization): array {
        return $this->locks($organization, $this->billingRoute->externalProgram($organization), $this->billingRoute->deviations($organization));
    }

    /**
     * @param list<array{program: string, count: int, local: bool}> $deviations
     * @return array<string, string>
     */
    private function locks(Organization $organization, ?string $billingProgram, array $deviations): array {
        // Rechnet ein Kunde in workDiary ab, bekommen seine Rechnungen lokale Nummern.
        $localBilling = in_array(true, array_column($deviations, 'local'), true);

        $locks = [];
        foreach (NumberScope::cases() as $scope) {
            $source = $this->authority->sourceFor($organization, $scope);
            if ($source !== NumberAuthority::SOURCE_LOCAL) {
                $locks[$scope->value] = $this->authority->sourceLabel($source);
            } elseif (in_array($scope, self::BILLING_SCOPES, true) && $billingProgram !== null && ! $localBilling) {
                $locks[$scope->value] = $billingProgram;
            }
        }

        return $locks;
    }

    /** @return list<string> */
    private function details(Organization $organization, NumberScope $scope, string $source, ?string $lockedBy): array {
        $party = match ($scope) {
            NumberScope::Customer => Customer::class,
            NumberScope::Supplier => Supplier::class,
            default => null,
        };
        if ($party === null) {
            return $lockedBy !== null ? [(string) __('numbering.preview.program', ['program' => $lockedBy])] : [];
        }

        $details = [];
        if ($lockedBy !== null) {
            $plugin = app(PluginManager::class)->find($source);
            $next = $plugin instanceof ContactNumberPreview ? $plugin->nextContactNumber((int) $organization->id, $scope) : null;
            if ($next !== null) {
                $details[] = (string) __('numbering.preview.next_remote', ['program' => $lockedBy, 'number' => $next]);
            }

            $last = $party::query()
                ->where('organization_id', $organization->id)
                ->where('number_source', $source)
                ->where('number', 'not like', NumberSequenceService::DRAFT_PREFIX . '%')
                ->latest('id')
                ->value('number');
            if (is_string($last) && $last !== '') {
                $details[] = (string) __('numbering.preview.last_adopted', ['program' => $lockedBy, 'number' => $last]);
            }
        }

        // Entwürfe warten auch nach dem Zurückschalten weiter auf ihr Programm (MVP-1131).
        $pending = $party::query()
            ->withoutCollective()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->where('number_source', '!=', NumberAuthority::SOURCE_LOCAL)
            ->where('number', 'like', NumberSequenceService::DRAFT_PREFIX . '%')
            ->count();
        if ($pending > 0) {
            $details[] = trans_choice('numbering.preview.pending', $pending, ['count' => $pending]);
        }

        if ($details === [] && $lockedBy !== null) {
            $details[] = (string) __('numbering.preview.program', ['program' => $lockedBy]);
        }

        return $details;
    }
}
