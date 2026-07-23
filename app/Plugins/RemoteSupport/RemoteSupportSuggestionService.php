<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport;

use App\Models\{Asset, Customer, ExternalReference, ExternalReferenceAlias, Organization, Project, RemotePendingSession, TimeEntry};
use Illuminate\Support\Collection;

/**
 * Zuweisungsvorschläge für die Fernwartungs-Inbox. Vier Signale, absteigend
 * gewichtet:
 *
 *  1. Zeitüberlappung: offene Sitzungen gegen bereits erfasste Zeiten des
 *     Buchungs-Users (Projekt → Kunde). Ein dominanter Kunde ⇒ Kundengerät,
 *     mehrere Kunden mit substanzieller Überlappung ⇒ Mehrkundengerät.
 *  2. Gelernte Alias-Tokens: Tokens aus Aliassen bereits zugeordneter
 *     Sitzungen und aus Namen von Geräten mit Fernwartungs-ID. Zeigt ein
 *     Token bisher auf genau einen Kunden, ist es ein belastbarer Treffer
 *     (lernt beim Abarbeiten der Inbox mit).
 *  3. Matchcode: manuell gepflegtes Kunden-Kürzel, exakter Token-Vergleich.
 *  4. Namensmuster: markante Namenswörter im Alias/Notizen, Akronym aus den
 *     Namensinitialen sowie Buchstaben-Subsequenz (fängt „GSL" für
 *     „Gebr. Schwabenland Großküchen") — nur bei eindeutigem Treffer.
 *
 * Vorschläge befüllen ausschließlich die Formulare vor — gebucht wird immer
 * erst durch den Admin.
 */
class RemoteSupportSuggestionService {
    /** Eine Sitzung zählt für einen Kunden ab dieser Überlappung. */
    private const MIN_OVERLAP_SECONDS = 60;

    /** Ein Kunde gilt als dominant ab diesem Anteil der gematchten Sitzungen. */
    private const DOMINANT_SHARE = 0.7;

    /** Mehrkundengerät: je Kunde mindestens so viele Sitzungen … */
    private const SHARED_MIN_SESSIONS = 2;

    /** … und mindestens dieser Anteil der gematchten Sitzungen. */
    private const SHARED_MIN_SHARE = 0.25;

    /**
     * Generische Geräte-/Standortwörter, die nie als Kundenkürzel gewertet
     * werden (die Eindeutigkeitsprüfung fängt den Rest).
     *
     * @var list<string>
     */
    private const TOKEN_STOPWORDS = [
        'PC', 'NB', 'SRV', 'SERVER', 'DC', 'TS', 'RDP', 'VM', 'HOST', 'WIN',
        'LAPTOP', 'NOTEBOOK', 'DESKTOP', 'APP', 'SQL', 'NAS', 'BUERO', 'BÜRO',
        'KASSE', 'EMPFANG', 'LAGER', 'WERKSTATT', 'CHEF', 'INFO', 'ADMIN',
    ];

    /**
     * Rechtsform-/Füllwörter, die bei Akronym & markanten Namenswörtern
     * ignoriert werden (für die Subsequenz zählen sie mit).
     *
     * @var list<string>
     */
    private const NAME_STOPWORDS = [
        'GMBH', 'MBH', 'AG', 'KG', 'UG', 'EK', 'OHG', 'GBR', 'CO', 'GEBR',
        'GEBRUEDER', 'GEBRÜDER', 'HAFTUNGSBESCHRAENKT', 'HAFTUNGSBESCHRÄNKT',
        'UND', 'DER', 'DIE', 'DAS', 'VON', 'ZUR', 'ZUM', 'INH', 'DR', 'ING',
    ];

    public function __construct(private readonly RemoteSupportService $service) {}

    /**
     * Vorschläge je (provider, remote_id)-Gruppe des Unbekannt-Reiters.
     *
     * @param  iterable<int, object{provider: string, remote_id: string, alias: string|null, notes: array<int, string>}>  $groups  aktuell sichtbare Inbox-Gruppen
     * @return array<string, object{kind: string, customerSqid: string|null, customerName: string|null, assetSqid: string|null, assetLabel: string|null, matchcode: string|null, matched: int, total: int, reasons: array<int, string>}> Schlüssel "provider|remote_id"
     */
    public function suggestForGroups(Organization $organization, iterable $groups): array {
        $groupList = collect($groups)->values();
        if ($groupList->isEmpty()) {
            return [];
        }

        $wanted = $groupList
            ->map(fn (object $g): string => $g->provider . '|' . $g->remote_id)
            ->flip();

        /** @var Collection<string, Collection<int, RemotePendingSession>> $sessionsByKey */
        $sessionsByKey = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_OPEN)
            ->whereNull('asset_id')
            ->get()
            ->groupBy(fn (RemotePendingSession $s): string => $s->provider . '|' . $s->remote_id)
            ->filter(fn ($rows, string $key): bool => $wanted->has($key));

        $customers = $this->customers($organization);
        $overlaps = $this->overlapsBySession($organization, $sessionsByKey->flatten(1));
        $learned = $this->learnedTokenMap($organization);

        $suggestions = [];
        foreach ($groupList as $group) {
            $key = $group->provider . '|' . $group->remote_id;
            $sessions = $sessionsByKey->get($key) ?? collect();
            $suggestion = $this->buildGroupSuggestion($group, $sessions, $overlaps, $customers, $learned, $organization);
            if ($suggestion !== null) {
                $suggestions[$key] = $suggestion;
            }
        }

        return $suggestions;
    }

    /**
     * Kundenvorschlag je Einzelsitzung der Mehrkundengeräte (Shared-Reiter),
     * ausschließlich über die Zeitüberlappung.
     *
     * @param  iterable<int, object{asset: Asset, sessions: Collection<int, RemotePendingSession>}>  $devices  sichtbare Geräte samt sichtbarer Sitzungen
     * @return array<int, object{customerSqid: string, customerName: string, minutes: int}> Schlüssel: RemotePendingSession-ID
     */
    public function suggestForSharedSessions(Organization $organization, iterable $devices): array {
        $sessions = collect($devices)->flatMap(fn (object $d) => $d->sessions);
        if ($sessions->isEmpty()) {
            return [];
        }

        $customers = $this->customers($organization);
        $overlaps = $this->overlapsBySession($organization, $sessions);

        $suggestions = [];
        foreach ($sessions as $session) {
            $byCustomer = $overlaps[$session->id] ?? [];
            if ($byCustomer === []) {
                continue;
            }

            arsort($byCustomer);
            $customerId = array_key_first($byCustomer);
            $seconds = $byCustomer[$customerId];
            $customer = $customers->get($customerId);
            if ($customer === null || $seconds < self::MIN_OVERLAP_SECONDS) {
                continue;
            }

            $suggestions[(int) $session->id] = (object) [
                'customerSqid' => (string) $customer->sqid,
                'customerName' => (string) ($customer->company ?: $customer->name),
                'minutes' => max(1, (int) round($seconds / 60)),
            ];
        }

        return $suggestions;
    }

    /**
     * Baut den Vorschlag für eine Inbox-Gruppe aus Überlappungs- und
     * Textsignalen zusammen.
     *
     * @param  object{provider: string, remote_id: string, alias: string|null, notes: array<int, string>}  $group
     * @param  Collection<int, RemotePendingSession>  $sessions
     * @param  array<int, array<int, int>>  $overlaps  Sitzung → (Kunde → Sekunden)
     * @param  Collection<int, Customer>  $customers
     * @param  array<string, int>  $learned  Token → Kunden-ID
     * @return object{kind: string, customerSqid: string|null, customerName: string|null, assetSqid: string|null, assetLabel: string|null, matchcode: string|null, matched: int, total: int, reasons: array<int, string>}|null
     */
    private function buildGroupSuggestion(object $group, Collection $sessions, array $overlaps, Collection $customers, array $learned, Organization $organization): ?object {
        // Überlappungsstatistik: Kunde → [Sitzungen, Sekunden].
        $stats = [];
        $matchedSessions = 0;
        foreach ($sessions as $session) {
            $byCustomer = array_filter(
                $overlaps[$session->id] ?? [],
                fn (int $secs): bool => $secs >= self::MIN_OVERLAP_SECONDS,
            );
            if ($byCustomer === []) {
                continue;
            }
            $matchedSessions++;
            foreach ($byCustomer as $customerId => $secs) {
                $stats[$customerId]['sessions'] = ($stats[$customerId]['sessions'] ?? 0) + 1;
                $stats[$customerId]['seconds'] = ($stats[$customerId]['seconds'] ?? 0) + $secs;
            }
        }

        uasort($stats, fn (array $a, array $b): int => [$b['sessions'], $b['seconds']] <=> [$a['sessions'], $a['seconds']]);

        $textHit = $this->textSignal($group, $customers, $learned);

        // Mehrkundengerät: mehrere Kunden mit substanzieller Überlappung.
        $sharedCandidates = array_filter(
            $stats,
            fn (array $s): bool => $s['sessions'] >= self::SHARED_MIN_SESSIONS
                && $matchedSessions > 0
                && $s['sessions'] / $matchedSessions >= self::SHARED_MIN_SHARE,
        );
        if (count($sharedCandidates) >= 2) {
            $names = [];
            foreach ($sharedCandidates as $customerId => $s) {
                $customer = $customers->get($customerId);
                if ($customer !== null) {
                    $names[] = ($customer->company ?: $customer->name) . ' (' . $s['sessions'] . ')';
                }
            }

            return (object) [
                'kind' => 'shared',
                'customerSqid' => null,
                'customerName' => null,
                'assetSqid' => null,
                'assetLabel' => null,
                'matchcode' => null,
                'matched' => $matchedSessions,
                'total' => $sessions->count(),
                'reasons' => [
                    __('Sitzungen überlappen mit erfassten Zeiten mehrerer Kunden: :list', ['list' => implode(', ', $names)]),
                ],
            ];
        }

        // Dominanter Kunde über die Überlappung.
        $customerId = null;
        $reasons = [];
        if ($stats !== []) {
            $topId = (int) array_key_first($stats);
            $top = $stats[$topId];
            $share = $matchedSessions > 0 ? $top['sessions'] / $matchedSessions : 0.0;

            if ($share >= self::DOMINANT_SHARE) {
                $customerId = $topId;
            } elseif ($textHit !== null && isset($stats[$textHit->customerId])) {
                // Mehrdeutige Überlappung: das Textsignal entscheidet den Gleichstand.
                $customerId = $textHit->customerId;
            }

            if ($customerId !== null) {
                $customer = $customers->get($customerId);
                $reasons[] = __(':matched von :total Sitzungen überlappen mit erfassten Zeiten für :name', [
                    'matched' => $stats[$customerId]['sessions'],
                    'total' => $sessions->count(),
                    'name' => $customer !== null ? ($customer->company ?: $customer->name) : (string) $customerId,
                ]);
            }
        }

        // Nur Textsignal (kein Überlappungstreffer).
        if ($customerId === null && $textHit !== null) {
            $customerId = $textHit->customerId;
        }

        if ($customerId === null) {
            return null;
        }

        $customer = $customers->get($customerId);
        if ($customer === null) {
            return null;
        }

        if ($textHit !== null) {
            if ($textHit->customerId === $customerId) {
                $reasons[] = $textHit->reason;
            } else {
                $other = $customers->get($textHit->customerId);
                if ($other !== null) {
                    $reasons[] = __('Hinweis: Der Alias deutet auf :name.', ['name' => $other->company ?: $other->name]);
                }
            }
        }

        // Kürzel zum Hinterlegen anbieten, wenn es über ein Alias-Token kam
        // und der Kunde noch keinen Matchcode hat.
        $matchcode = null;
        if ($customer->matchcode === null
            && $textHit !== null
            && $textHit->customerId === $customerId
            && $textHit->token !== null) {
            $matchcode = $textHit->token;
        }

        [$assetSqid, $assetLabel] = $this->singleFreeAsset($organization, $customerId, (string) $group->provider);

        return (object) [
            'kind' => 'customer',
            'customerSqid' => (string) $customer->sqid,
            'customerName' => (string) ($customer->company ?: $customer->name),
            'assetSqid' => $assetSqid,
            'assetLabel' => $assetLabel,
            'matchcode' => $matchcode,
            'matched' => $matchedSessions,
            'total' => $sessions->count(),
            'reasons' => $reasons,
        ];
    }

    /**
     * Bestes Textsignal für eine Gruppe: Matchcode > gelerntes Token >
     * markantes Namenswort > Akronym > eindeutige Subsequenz.
     *
     * @param  object{provider: string, remote_id: string, alias: string|null, notes: array<int, string>}  $group
     * @param  Collection<int, Customer>  $customers
     * @param  array<string, int>  $learned
     * @return object{customerId: int, reason: string, token: string|null}|null
     */
    private function textSignal(object $group, Collection $customers, array $learned): ?object {
        $tokens = $this->tokenize((string) ($group->alias ?? ''));

        // 1) Matchcode (manuell gepflegtes Kürzel).
        foreach ($tokens as $token) {
            foreach ($customers as $customer) {
                if ($customer->matchcode !== null && mb_strtoupper($customer->matchcode) === $token) {
                    return (object) [
                        'customerId' => (int) $customer->id,
                        'reason' => __('Kürzel „:token" ist als Matchcode von :name hinterlegt.', ['token' => $token, 'name' => $customer->company ?: $customer->name]),
                        'token' => null,
                    ];
                }
            }
        }

        // 2) Gelerntes Token aus früheren Zuordnungen.
        foreach ($tokens as $token) {
            $customer = isset($learned[$token]) ? $customers->get($learned[$token]) : null;
            if ($customer !== null) {
                return (object) [
                    'customerId' => (int) $customer->id,
                    'reason' => __('Kürzel „:token" wurde bisher immer :name zugeordnet.', ['token' => $token, 'name' => $customer->company ?: $customer->name]),
                    'token' => $token,
                ];
            }
        }

        // 3) Markantes Namenswort in Alias oder Notizen (eindeutig über alle Kunden).
        $haystack = mb_strtoupper(implode(' ', array_merge([(string) ($group->alias ?? '')], (array) ($group->notes ?? []))));
        if (trim($haystack) !== '') {
            $wordHits = [];
            foreach ($customers as $customer) {
                foreach ($this->significantWords($customer) as $word) {
                    if (mb_strlen($word) >= 5 && str_contains($haystack, $word)) {
                        $wordHits[(int) $customer->id] = $word;
                        break;
                    }
                }
            }
            if (count($wordHits) === 1) {
                $customerId = (int) array_key_first($wordHits);
                $customer = $customers->get($customerId);
                if ($customer !== null) {
                    return (object) [
                        'customerId' => $customerId,
                        'reason' => __('Alias/Notiz enthält „:word" aus dem Namen von :name.', ['word' => mb_convert_case($wordHits[$customerId], MB_CASE_TITLE, 'UTF-8'), 'name' => $customer->company ?: $customer->name]),
                        'token' => null,
                    ];
                }
            }
        }

        // 4) Akronym aus den Initialen der markanten Namenswörter.
        foreach ($tokens as $token) {
            $acronymHits = [];
            foreach ($customers as $customer) {
                if ($this->acronym($customer) === $token) {
                    $acronymHits[] = (int) $customer->id;
                }
            }
            if (count($acronymHits) === 1) {
                $customer = $customers->get($acronymHits[0]);
                if ($customer !== null) {
                    return (object) [
                        'customerId' => $acronymHits[0],
                        'reason' => __('Kürzel „:token" passt zu den Initialen von :name.', ['token' => $token, 'name' => $customer->company ?: $customer->name]),
                        'token' => $token,
                    ];
                }
            }
        }

        // 5) Buchstaben-Subsequenz — nur bei eindeutigem Treffer über alle Kunden.
        foreach ($tokens as $token) {
            $subHits = [];
            foreach ($customers as $customer) {
                if ($this->matchesSubsequence($token, $customer)) {
                    $subHits[] = (int) $customer->id;
                }
            }
            if (count($subHits) === 1) {
                $customer = $customers->get($subHits[0]);
                if ($customer !== null) {
                    return (object) [
                        'customerId' => $subHits[0],
                        'reason' => __('Kürzel „:token" passt zum Namensmuster von :name.', ['token' => $token, 'name' => $customer->company ?: $customer->name]),
                        'token' => $token,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Überlappungssekunden je Sitzung und Kunde, auf Basis der Zeiteinträge
     * des Buchungs-Users (konsistent zur Verknüpfungslogik beim Buchen).
     *
     * @param  Collection<int, RemotePendingSession>  $sessions
     * @return array<int, array<int, int>> Sitzung → (Kunde → Sekunden)
     */
    private function overlapsBySession(Organization $organization, Collection $sessions): array {
        if ($sessions->isEmpty()) {
            return [];
        }

        $userId = $this->service->resolveBookingUserId(
            $organization,
            RemoteSupportConfig::resolve((int) $organization->id)['default_user_id'] ?? null,
        );
        if ($userId === null) {
            return [];
        }

        // Nur Einträge laden, die mindestens eine Sitzung zeitlich schneiden
        // (OR-Paar je Sitzung; die Inbox rechnet nur für die sichtbare Seite).
        $entries = TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->where(function ($query) use ($sessions): void {
                foreach ($sessions as $session) {
                    $query->orWhere(function ($q) use ($session): void {
                        $q->where('started_at', '<', $session->ended_at)
                            ->where('ended_at', '>', $session->started_at);
                    });
                }
            })
            ->get(['id', 'project_id', 'started_at', 'ended_at']);

        if ($entries->isEmpty()) {
            return [];
        }

        $customerByProject = Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $entries->pluck('project_id')->unique()->all())
            ->whereNotNull('customer_id')
            ->pluck('customer_id', 'id');

        $overlaps = [];
        foreach ($sessions as $session) {
            $sessionStart = $session->started_at->getTimestamp();
            $sessionEnd = $session->ended_at->getTimestamp();
            foreach ($entries as $entry) {
                $customerId = $customerByProject[$entry->project_id] ?? null;
                if ($customerId === null || $entry->started_at === null || $entry->ended_at === null) {
                    continue;
                }
                $seconds = min($entry->ended_at->getTimestamp(), $sessionEnd)
                    - max($entry->started_at->getTimestamp(), $sessionStart);
                if ($seconds > 0) {
                    $overlaps[(int) $session->id][(int) $customerId] = ($overlaps[(int) $session->id][(int) $customerId] ?? 0) + $seconds;
                }
            }
        }

        return $overlaps;
    }

    /**
     * Gelerntes Token-Wörterbuch: Aliasse bereits importierter Sitzungen und
     * Namen von Geräten mit Fernwartungs-ID, jeweils auf den Kunden des
     * Geräts gemappt. Nur Tokens, die auf genau EINEN Kunden zeigen, sind
     * verwertbar; Mehrkundengeräte bleiben außen vor.
     *
     * @return array<string, int> Token → Kunden-ID
     */
    private function learnedTokenMap(Organization $organization): array {
        /** @var array<string, array<int, true>> $tokenCustomers */
        $tokenCustomers = [];

        $record = function (?string $text, ?int $customerId) use (&$tokenCustomers): void {
            if ($customerId === null) {
                return;
            }
            foreach ($this->tokenize((string) $text) as $token) {
                $tokenCustomers[$token][$customerId] = true;
            }
        };

        // Aliasse importierter Sitzungen → Kunde des zugeordneten Geräts.
        RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_IMPORTED)
            ->whereNotNull('asset_id')
            ->whereNotNull('alias')
            ->with('asset:id,customer_id,shared_remote')
            ->get(['id', 'asset_id', 'alias'])
            ->each(function (RemotePendingSession $row) use ($record): void {
                $asset = $row->asset;
                if ($asset instanceof Asset && ! $asset->shared_remote) {
                    $record($row->alias, $asset->customer_id !== null ? (int) $asset->customer_id : null);
                }
            });

        // Namen der Geräte, die bereits eine Fernwartungs-ID tragen.
        $refs = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->whereIn('external_type', array_values(RemoteSupportService::DEVICE_TYPES))
            ->where('referenceable_type', (new Asset)->getMorphClass())
            ->pluck('referenceable_id');

        Asset::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $refs->unique()->all())
            ->where('shared_remote', false)
            ->whereNotNull('customer_id')
            ->get(['id', 'name', 'customer_id'])
            ->each(fn (Asset $asset) => $record($asset->name, (int) $asset->customer_id));

        $map = [];
        foreach ($tokenCustomers as $token => $customerIds) {
            if (count($customerIds) === 1) {
                $map[$token] = (int) array_key_first($customerIds);
            }
        }

        return $map;
    }

    /**
     * Genau EIN fernwartbares Gerät des Kunden ohne ID dieses Providers ⇒
     * konkreter Gerätevorschlag fürs „Bestehendes Gerät"-Formular.
     *
     * @return array{0: string|null, 1: string|null} [Sqid, Anzeigename]
     */
    private function singleFreeAsset(Organization $organization, int $customerId, string $provider): array {
        $assets = Asset::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $customerId)
            ->whereIn('category_code', RemoteSupportService::REMOTE_CATEGORY_CODES)
            ->get(['id', 'name', 'asset_no']);

        if ($assets->isEmpty()) {
            return [null, null];
        }

        $deviceType = RemoteSupportService::DEVICE_TYPES[$provider] ?? null;
        if ($deviceType === null) {
            return [null, null];
        }

        $taken = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', $deviceType)
            ->where('referenceable_type', (new Asset)->getMorphClass())
            ->whereIn('referenceable_id', $assets->pluck('id')->all())
            ->pluck('referenceable_id')
            ->merge(
                ExternalReferenceAlias::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('plugin_id', RemoteSupportPlugin::ID)
                    ->where('external_type', $deviceType)
                    ->where('referenceable_type', (new Asset)->getMorphClass())
                    ->whereIn('referenceable_id', $assets->pluck('id')->all())
                    ->pluck('referenceable_id'),
            )
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $free = $assets->filter(fn (Asset $a): bool => ! $taken->has((int) $a->id))->values();
        $asset = $free->count() === 1 ? $free->first() : null;
        if (! $asset instanceof Asset) {
            return [null, null];
        }

        return [(string) $asset->sqid, (string) ($asset->name ?: $asset->asset_no)];
    }

    /**
     * Kundenstamm der Organisation, nach ID indiziert.
     *
     * @return Collection<int, Customer>
     */
    private function customers(Organization $organization): Collection {
        return Customer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->get(['id', 'name', 'company', 'matchcode'])
            ->keyBy('id');
    }

    /**
     * Zerlegt Alias/Gerätenamen in Kürzel-Kandidaten: Buchstabenfolgen,
     * großgeschrieben, 2–8 Zeichen, ohne generische Gerätewörter.
     *
     * @return array<int, string>
     */
    private function tokenize(string $text): array {
        $parts = preg_split('/[^\p{L}]+/u', mb_strtoupper($text)) ?: [];

        return array_values(array_unique(array_filter(
            $parts,
            fn (string $t): bool => mb_strlen($t) >= 2
                && mb_strlen($t) <= 8
                && ! in_array($t, self::TOKEN_STOPWORDS, true),
        )));
    }

    /**
     * Markante Namenswörter (ohne Rechtsform/Füllwörter), großgeschrieben.
     *
     * @return array<int, string>
     */
    private function significantWords(Customer $customer): array {
        $name = (string) ($customer->company ?: $customer->name);
        $parts = preg_split('/[^\p{L}]+/u', mb_strtoupper($name)) ?: [];

        return array_values(array_filter(
            $parts,
            fn (string $w): bool => $w !== '' && ! in_array($w, self::NAME_STOPWORDS, true),
        ));
    }

    /** Akronym aus den Initialen der markanten Namenswörter (z. B. „SG"). */
    private function acronym(Customer $customer): string {
        return implode('', array_map(
            fn (string $w): string => mb_substr($w, 0, 1),
            $this->significantWords($customer),
        ));
    }

    /**
     * Prüft, ob das Token als Buchstaben-Subsequenz im Kundennamen steckt —
     * beginnend an einem Wortanfang (fängt „GSL" in „Gebr. Schwabenland
     * Großküchen", vermeidet aber Treffer mitten im Wort).
     */
    private function matchesSubsequence(string $token, Customer $customer): bool {
        if (mb_strlen($token) < 2 || mb_strlen($token) > 6) {
            return false;
        }

        $name = (string) ($customer->company ?: $customer->name);
        $words = preg_split('/[^\p{L}]+/u', mb_strtoupper($name)) ?: [];
        $words = array_values(array_filter($words, fn (string $w): bool => $w !== ''));

        foreach ($words as $i => $word) {
            if (mb_substr($word, 0, 1) !== mb_substr($token, 0, 1)) {
                continue;
            }
            $rest = implode('', array_slice($words, $i));
            if ($this->isSubsequence($token, $rest)) {
                return true;
            }
        }

        return false;
    }

    /** Buchstaben von $needle kommen in $haystack in gleicher Reihenfolge vor. */
    private function isSubsequence(string $needle, string $haystack): bool {
        $pos = 0;
        $len = mb_strlen($haystack);
        for ($i = 0; $i < mb_strlen($needle); $i++) {
            $char = mb_substr($needle, $i, 1);
            while ($pos < $len && mb_substr($haystack, $pos, 1) !== $char) {
                $pos++;
            }
            if ($pos >= $len) {
                return false;
            }
            $pos++;
        }

        return true;
    }
}
