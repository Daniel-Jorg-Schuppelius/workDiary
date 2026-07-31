<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Fritzbox;

use App\Models\{Customer, ForeignCustomer, Organization};
use App\Services\Integration\InboxGroupBooker;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Inbox-Adapter des FritzBox-Plugins (`form => 'phone_number'`): unbekannte
 * Rufnummern-Gruppen einem Kunden/Endkunden zuordnen (optional dauerhaft
 * merken), als geteilte Nummer markieren (künftig Einzelzuordnung je Anruf —
 * Dienstleister-Hotlines im Kundenauftrag) oder dauerhaft ignorieren
 * (privat/Spam). Verwerfen dagegen ist temporär: die Nummer taucht beim
 * nächsten Import wieder auf.
 */
class FritzboxGroupBooker implements InboxGroupBooker {
    public function __construct(
        private readonly FritzboxImportService $service,
        private readonly FritzboxSuggestionService $suggester,
    ) {}

    public function groups(Organization $organization): Collection {
        $groups = $this->service->openInboxGroups($organization);
        $suggestions = $this->suggester->suggestForGroups($organization, $groups);

        return $groups->map(function (array $group) use ($suggestions): array {
            $suggestion = $suggestions[(string) $group['group_key']] ?? null;

            /** @var array<string, mixed> $out */
            $out = $group + [
                'form' => 'phone_number',
                'entries_more' => max(0, (int) $group['count'] - count($group['entries'])),
                'suggested_customer_sqid' => $suggestion['customer_sqid'] ?? null,
                'suggested_foreign_sqid' => $suggestion['foreign_sqid'] ?? null,
            ];

            return $out;
        })->values();
    }

    public function rules(): array {
        return [
            'action' => ['required', 'in:assign,shared,ignore'],
            'customer' => ['nullable', 'string'],
            'foreign_customer' => ['nullable', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function book(Organization $organization, string $groupKey, array $input): array {
        $action = (string) ($input['action'] ?? 'assign');
        $e164 = str_contains($groupKey, '|') ? explode('|', $groupKey, 2)[0] : $groupKey;

        if ($action === 'shared') {
            $this->service->markShared($organization, $e164);

            return ['created' => 0, 'skipped' => 0];
        }

        if ($action === 'ignore') {
            $dismissed = $this->service->markIgnored($organization, $e164);

            return ['created' => 0, 'skipped' => $dismissed];
        }

        $target = $this->resolveTarget($organization, $input);
        $remember = filter_var($input['remember'] ?? false, FILTER_VALIDATE_BOOL);
        $config = FritzboxConfig::resolve($organization->id);

        $result = $this->service->assignGroup($organization, $groupKey, $target, $remember, $config);

        // Flash-Vertrag des Inbox-Controllers: created/skipped. Verschmolzene
        // Anrufe sind gebucht; gesperrte Monate zählen als übersprungen.
        return [
            'created' => $result['created'] + $result['linked'],
            'skipped' => $result['skipped'] + $result['locked'],
        ];
    }

    public function dismiss(Organization $organization, string $groupKey): int {
        return $this->service->dismissGroup($organization, $groupKey);
    }

    /**
     * Endkunde gewinnt vor Kunde (präziseres Buchungsziel); org-gescopte Sqid-Auflösung.
     *
     * @param  array<string, mixed>  $input
     */
    private function resolveTarget(Organization $organization, array $input): Customer|ForeignCustomer {
        $foreignSqid = trim((string) ($input['foreign_customer'] ?? ''));
        if ($foreignSqid !== '') {
            $foreign = (new ForeignCustomer)->resolveRouteBinding($foreignSqid);
            abort_unless($foreign instanceof ForeignCustomer, 404);
            abort_unless((int) $foreign->organization_id === (int) $organization->id, 404);

            return $foreign;
        }

        $customerSqid = trim((string) ($input['customer'] ?? ''));
        if ($customerSqid !== '') {
            $customer = (new Customer)->resolveRouteBinding($customerSqid);
            abort_unless($customer instanceof Customer, 404);
            abort_unless((int) $customer->organization_id === (int) $organization->id, 404);

            return $customer;
        }

        throw ValidationException::withMessages([
            'customer' => (string) __('Zum Zuordnen einen Kunden oder Endkunden auswählen.'),
        ]);
    }
}
