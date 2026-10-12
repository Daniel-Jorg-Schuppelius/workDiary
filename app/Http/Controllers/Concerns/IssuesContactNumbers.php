<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssuesContactNumbers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\Numbering\ContactNumberOutcome;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Supplier\Supplier;
use App\Services\Numbering\NumberAuthority;
use App\Services\Stammdaten\ContactNumberAdopter;
use App\Services\Stammdaten\Contracts\ContactPushTarget;
use App\Support\ErrorText;
use Throwable;

/**
 * Anlegen im Formular: Vergibt ein Buchhaltungsprogramm die Nummer, wird der
 * neue Kunde bzw. Lieferant sofort übertragen (MVP-1131). Scheitert das,
 * bleibt die Entwurfsnummer und der stündliche Nachlauf holt es nach.
 */
trait IssuesContactNumbers {
    /** @return array<string, string> Flash-Meldungen */
    protected function issueContactNumber(Customer|Supplier $party): array {
        $program = app(NumberAuthority::class)->sourceLabel((string) $party->number_source);

        try {
            $outcome = app(ContactPushTarget::class)->issueNumber($party);
        } catch (Throwable $e) {
            return ['warning' => (string) __('numbering.flash.pending', ['program' => $program, 'reason' => ErrorText::for($e)])];
        }

        if ($outcome === ContactNumberOutcome::Taken) {
            $number = (string) ExternalReference::query()
                ->forPlugin($party->organization_id, (string) $party->number_source, ExternalReference::TYPE_CONTACT)
                ->forReferenceable($party)
                ->value('external_number');

            return ['warning' => (string) __('numbering.flash.conflict', [
                'number' => $number,
                'program' => $program,
                'holder' => (string) app(ContactNumberAdopter::class)->holder($party, $number)?->displayLabel(),
            ])];
        }

        return match ($outcome) {
            ContactNumberOutcome::Adopted => ['info' => (string) __('numbering.flash.issued', ['number' => (string) $party->number, 'program' => $program])],
            ContactNumberOutcome::Skipped => ['warning' => (string) __('numbering.flash.pending', ['program' => $program, 'reason' => __('numbering.error.no_number', ['program' => $program])])],
            default => [],
        };
    }
}
