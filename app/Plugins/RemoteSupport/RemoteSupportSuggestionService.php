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

use App\Models\{Asset, Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, Organization, Project, RemotePendingSession, TimeEntry};
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

    public function __construct(private readonly RemoteSessionImporter $service) {}

    /**
     * Vorschläge je (provider, remote_id)-Gruppe des Unbekannt-Reiters.
     *
     * @param  iterable<int, object{provider: string, remote_id: string, alias: string|null, notes: array<int, string>}>  $groups  aktuell sichtbare Inbox-Gruppen
     * @return array<string, object{kind: string, customerSqid: string|null, customerName: string|null, foreignSqid: string|null, foreignName: string|null, assetSqid: string|null, assetLabel: string|null, matchcode: string|null, matchcodeScope: string|null, matched: int, total: int, reasons: array<int, string>}> Schlüssel "provider|remote_id"
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
        $foreignCustomers = $this->foreignCustomers($organization);
        $overlaps = $this->overlapsBySession($organization, $sessionsByKey->flatten(1));
        $learned = $this->learnedTokenMap($organization);

        $suggestions = [];
        foreach ($groupList as $group) {
            $key = $group->provider . '|' . $group->remote_id;
            $sessions = $sessionsByKey->get($key) ?? collect();
            $suggestion = $this->buildGroupSuggestion($group, $sessions, $overlaps, $customers, $foreignCustomers, $learned, $organization);
            if ($suggestion !== null) {
                $suggestions[$key] = $suggestion;
            }
        }

        return $suggestions;
    }

    /**
     * Kundenvorschlag je Einzelsitzung der Mehrkundengeräte (Shared-Reiter),
     * ausschließlich über die Zeitüberlappung — inklusive Endkunde, wenn er
     * die überlappenden Zeiten des Kunden dominiert.
     *
     * @param  iterable<int, object{asset: Asset, sessions: Collection<int, RemotePendingSession>}>  $devices  sichtbare Geräte samt sichtbarer Sitzungen
     * @return array<int, object{customerSqid: string, customerName: string, foreignSqid: string|null, foreignName: string|null, minutes: int}> Schlüssel: RemotePendingSession-ID
     */
    public function suggestForSharedSessions(Organization $organization, iterable $devices): array {
        $sessions = collect($devices)->flatMap(fn (object $d) => $d->sessions);
        if ($sessions->isEmpty()) {
            return [];
        }

        $customers = $this->customers($organization);
        $foreignCustomers = $this->foreignCustomers($organization);
        $overlaps = $this->overlapsBySession($organization, $sessions);

        $suggestions = [];
        foreach ($sessions as $session) {
            // Composite-Schlüssel je Kunde aufsummieren; Endkunden-Verteilung merken.
            $byCustomer = [];
            $fcSeconds = [];
            foreach ($overlaps[$session->id] ?? [] as $target => $secs) {
                [$customerPart, $foreignPart] = explode('|', (string) $target);
                $byCustomer[(int) $customerPart] = ($byCustomer[(int) $customerPart] ?? 0) + $secs;
                $fcSeconds[(int) $customerPart][(int) $foreignPart] = ($fcSeconds[(int) $customerPart][(int) $foreignPart] ?? 0) + $secs;
            }
            if ($byCustomer === []) {
                continue;
            }

            arsort($byCustomer);
            $customerId = (int) array_key_first($byCustomer);
            $seconds = $byCustomer[$customerId];
            $customer = $customers->get($customerId);
            if ($customer === null || $seconds < self::MIN_OVERLAP_SECONDS) {
                continue;
            }

            $foreign = null;
            $perFc = $fcSeconds[$customerId] ?? [];
            if ($perFc !== []) {
                arsort($perFc);
                $topFc = (int) array_key_first($perFc);
                if ($topFc !== 0 && $perFc[$topFc] / $seconds >= self::DOMINANT_SHARE) {
                    $foreign = $foreignCustomers->get($topFc);
                    if ($foreign !== null && (int) $foreign->customer_id !== $customerId) {
                        $foreign = null;
                    }
                }
            }

            $suggestions[(int) $session->id] = (object) [
                'customerSqid' => (string) $customer->sqid,
                'customerName' => (string) ($customer->displayLabel()),
                'foreignSqid' => $foreign !== null ? (string) $foreign->sqid : null,
                'foreignName' => $foreign !== null ? (string) ($foreign->displayLabel()) : null,
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
     * @param  array<int, array<string, int>>  $overlaps  Sitzung → ("Kunde|Endkunde" → Sekunden)
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, ForeignCustomer>  $foreignCustomers
     * @param  array<string, array{customer: int, foreign: int|null}>  $learned  Token → Buchungsziel
     * @return object{kind: string, customerSqid: string|null, customerName: string|null, foreignSqid: string|null, foreignName: string|null, assetSqid: string|null, assetLabel: string|null, matchcode: string|null, matchcodeScope: string|null, matched: int, total: int, reasons: array<int, string>}|null
     */
    private function buildGroupSuggestion(object $group, Collection $sessions, array $overlaps, Collection $customers, Collection $foreignCustomers, array $learned, Organization $organization): ?object {
        // Überlappungsstatistik je Kunde (Sitzungen/Sekunden); parallel die
        // Endkunden-Verteilung je Kunde (Sekunden je foreign_customer_id,
        // 0 = direkt beim Kunden) für die Endkunden-Vorauswahl.
        $stats = [];
        $fcSeconds = [];
        $matchedSessions = 0;
        foreach ($sessions as $session) {
            $byCustomer = [];
            foreach ($overlaps[$session->id] ?? [] as $target => $secs) {
                [$customerPart, $foreignPart] = explode('|', (string) $target);
                $byCustomer[(int) $customerPart] = ($byCustomer[(int) $customerPart] ?? 0) + $secs;
                $fcSeconds[(int) $customerPart][(int) $foreignPart] = ($fcSeconds[(int) $customerPart][(int) $foreignPart] ?? 0) + $secs;
            }
            $byCustomer = array_filter($byCustomer, fn (int $secs): bool => $secs >= self::MIN_OVERLAP_SECONDS);
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

        $textHit = $this->textSignal($group, $customers, $foreignCustomers, $learned);

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
                    $names[] = ($customer->displayLabel()) . ' (' . $s['sessions'] . ')';
                }
            }

            return (object) [
                'kind' => 'shared',
                'customerSqid' => null,
                'customerName' => null,
                'foreignSqid' => null,
                'foreignName' => null,
                'assetSqid' => null,
                'assetLabel' => null,
                'matchcode' => null,
                'matchcodeScope' => null,
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
                    'name' => $customer !== null ? ($customer->displayLabel()) : (string) $customerId,
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
                    $reasons[] = __('Hinweis: Der Alias deutet auf :name.', ['name' => $other->displayLabel()]);
                }
            }
        }

        // Endkunden-Ebene: dominiert EIN Endkunde die überlappenden Zeiten des
        // Kunden, wird er mit vorgeschlagen; sonst zählt das Textsignal.
        $foreign = null;
        $perFc = $fcSeconds[$customerId] ?? [];
        $totalFcSeconds = array_sum($perFc);
        if ($totalFcSeconds > 0) {
            arsort($perFc);
            $topFc = (int) array_key_first($perFc);
            if ($topFc !== 0 && $perFc[$topFc] / $totalFcSeconds >= self::DOMINANT_SHARE) {
                $foreign = $foreignCustomers->get($topFc);
                if ($foreign !== null) {
                    $reasons[] = __('Die überlappenden Zeiten liegen beim Endkunden :name.', ['name' => $foreign->displayLabel()]);
                }
            }
        }
        if ($foreign === null && $textHit !== null && $textHit->customerId === $customerId && $textHit->foreignId !== null) {
            $foreign = $foreignCustomers->get($textHit->foreignId);
        }
        if ($foreign !== null && (int) $foreign->customer_id !== $customerId) {
            $foreign = null;
        }

        // Kürzel zum Hinterlegen anbieten, wenn es über ein Alias-Token kam —
        // beim Endkunden, wenn das Token den Endkunden identifiziert hat.
        $matchcode = null;
        $matchcodeScope = null;
        if ($textHit !== null && $textHit->customerId === $customerId && $textHit->token !== null) {
            if ($textHit->foreignId !== null) {
                $tokenForeign = $foreignCustomers->get($textHit->foreignId);
                if ($tokenForeign !== null && $tokenForeign->matchcode === null && (int) $tokenForeign->customer_id === $customerId) {
                    $matchcode = $textHit->token;
                    $matchcodeScope = 'foreign';
                }
            } elseif ($customer->matchcode === null) {
                $matchcode = $textHit->token;
                $matchcodeScope = 'customer';
            }
        }

        [$assetSqid, $assetLabel] = $this->singleFreeAsset($organization, $customerId, (string) $group->provider, $foreign !== null ? (int) $foreign->id : null);

        return (object) [
            'kind' => 'customer',
            'customerSqid' => (string) $customer->sqid,
            'customerName' => (string) ($customer->displayLabel()),
            'foreignSqid' => $foreign !== null ? (string) $foreign->sqid : null,
            'foreignName' => $foreign !== null ? (string) ($foreign->displayLabel()) : null,
            'assetSqid' => $assetSqid,
            'assetLabel' => $assetLabel,
            'matchcode' => $matchcode,
            'matchcodeScope' => $matchcodeScope,
            'matched' => $matchedSessions,
            'total' => $sessions->count(),
            'reasons' => $reasons,
        ];
    }

    /**
     * Bestes Textsignal für eine Gruppe: Matchcode > gelerntes Token >
     * markantes Namenswort > Akronym > eindeutige Subsequenz — jeweils über
     * Kunden UND Fremdkunden (Endkunden); die Eindeutigkeit gilt über die
     * Gesamtmenge beider Ebenen.
     *
     * @param  object{provider: string, remote_id: string, alias: string|null, notes: array<int, string>}  $group
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, ForeignCustomer>  $foreignCustomers
     * @param  array<string, array{customer: int, foreign: int|null}>  $learned
     * @return object{customerId: int, foreignId: int|null, reason: string, token: string|null}|null
     */
    private function textSignal(object $group, Collection $customers, Collection $foreignCustomers, array $learned): ?object {
        $tokens = $this->tokenize((string) ($group->alias ?? ''));

        // 1) Matchcode (manuell gepflegtes Kürzel) — Kunde vor Endkunde.
        foreach ($tokens as $token) {
            foreach ($customers as $customer) {
                if ($customer->matchcode !== null && mb_strtoupper($customer->matchcode) === $token) {
                    return (object) [
                        'customerId' => (int) $customer->id,
                        'foreignId' => null,
                        'reason' => __('Kürzel „:token" ist als Matchcode von :name hinterlegt.', ['token' => $token, 'name' => $customer->displayLabel()]),
                        'token' => null,
                    ];
                }
            }
            foreach ($foreignCustomers as $fc) {
                if ($fc->matchcode !== null && mb_strtoupper($fc->matchcode) === $token && $customers->has((int) $fc->customer_id)) {
                    return (object) [
                        'customerId' => (int) $fc->customer_id,
                        'foreignId' => (int) $fc->id,
                        'reason' => __('Kürzel „:token" ist als Matchcode des Endkunden :name hinterlegt.', ['token' => $token, 'name' => $fc->displayLabel()]),
                        'token' => null,
                    ];
                }
            }
        }

        // 2) Gelerntes Token aus früheren Zuordnungen.
        foreach ($tokens as $token) {
            $target = $learned[$token] ?? null;
            $customer = $target !== null ? $customers->get($target['customer']) : null;
            if ($customer !== null) {
                $fc = $target['foreign'] !== null ? $foreignCustomers->get($target['foreign']) : null;
                if ($fc !== null && (int) $fc->customer_id !== (int) $customer->id) {
                    $fc = null;
                }
                $display = (string) ($customer->displayLabel());
                if ($fc !== null) {
                    $display .= ' → ' . ($fc->displayLabel());
                }

                return (object) [
                    'customerId' => (int) $customer->id,
                    'foreignId' => $fc !== null ? (int) $fc->id : null,
                    'reason' => __('Kürzel „:token" wurde bisher immer :name zugeordnet.', ['token' => $token, 'name' => $display]),
                    'token' => $token,
                ];
            }
        }

        // Namens-Kandidaten beider Ebenen: 'c<id>' = Kunde, 'f<id>' = Endkunde.
        $names = [];
        foreach ($customers as $customer) {
            $names['c' . $customer->id] = (string) ($customer->displayLabel());
        }
        foreach ($foreignCustomers as $fc) {
            if ($customers->has((int) $fc->customer_id)) {
                $names['f' . $fc->id] = (string) ($fc->displayLabel());
            }
        }

        // 3) Markantes Namenswort in Alias oder Notizen (eindeutig über beide Ebenen).
        $haystack = mb_strtoupper(implode(' ', array_merge([(string) ($group->alias ?? '')], (array) ($group->notes ?? []))));
        if (trim($haystack) !== '') {
            $wordHits = [];
            foreach ($names as $key => $name) {
                foreach ($this->significantWordsOf($name) as $word) {
                    if (mb_strlen($word) >= 5 && str_contains($haystack, $word)) {
                        $wordHits[$key] = $word;
                        break;
                    }
                }
            }
            if (count($wordHits) === 1) {
                $key = (string) array_key_first($wordHits);
                $target = $this->resolveTextTarget($key, $customers, $foreignCustomers);
                if ($target !== null) {
                    [$customerId, $foreignId, $name] = $target;
                    $word = mb_convert_case($wordHits[$key], MB_CASE_TITLE, 'UTF-8');

                    return (object) [
                        'customerId' => $customerId,
                        'foreignId' => $foreignId,
                        'reason' => $foreignId !== null
                            ? __('Alias/Notiz enthält „:word" aus dem Namen des Endkunden :name.', ['word' => $word, 'name' => $name])
                            : __('Alias/Notiz enthält „:word" aus dem Namen von :name.', ['word' => $word, 'name' => $name]),
                        'token' => null,
                    ];
                }
            }
        }

        // 4) Akronym aus den Initialen der markanten Namenswörter.
        foreach ($tokens as $token) {
            $acronymHits = [];
            foreach ($names as $key => $name) {
                if ($this->acronymOf($name) === $token) {
                    $acronymHits[] = $key;
                }
            }
            if (count($acronymHits) === 1) {
                $target = $this->resolveTextTarget($acronymHits[0], $customers, $foreignCustomers);
                if ($target !== null) {
                    [$customerId, $foreignId, $name] = $target;

                    return (object) [
                        'customerId' => $customerId,
                        'foreignId' => $foreignId,
                        'reason' => $foreignId !== null
                            ? __('Kürzel „:token" passt zu den Initialen des Endkunden :name.', ['token' => $token, 'name' => $name])
                            : __('Kürzel „:token" passt zu den Initialen von :name.', ['token' => $token, 'name' => $name]),
                        'token' => $token,
                    ];
                }
            }
        }

        // 5) Buchstaben-Subsequenz — nur bei eindeutigem Treffer über beide Ebenen.
        foreach ($tokens as $token) {
            $subHits = [];
            foreach ($names as $key => $name) {
                if ($this->matchesSubsequenceName($token, $name)) {
                    $subHits[] = $key;
                }
            }
            if (count($subHits) === 1) {
                $target = $this->resolveTextTarget($subHits[0], $customers, $foreignCustomers);
                if ($target !== null) {
                    [$customerId, $foreignId, $name] = $target;

                    return (object) [
                        'customerId' => $customerId,
                        'foreignId' => $foreignId,
                        'reason' => $foreignId !== null
                            ? __('Kürzel „:token" passt zum Namensmuster des Endkunden :name.', ['token' => $token, 'name' => $name])
                            : __('Kürzel „:token" passt zum Namensmuster von :name.', ['token' => $token, 'name' => $name]),
                        'token' => $token,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Löst einen Namens-Kandidaten ('c<id>' = Kunde, 'f<id>' = Endkunde) zum
     * Buchungsziel auf.
     *
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, ForeignCustomer>  $foreignCustomers
     * @return array{0: int, 1: int|null, 2: string}|null [Kunde, Endkunde, Anzeigename]
     */
    private function resolveTextTarget(string $key, Collection $customers, Collection $foreignCustomers): ?array {
        if (str_starts_with($key, 'f')) {
            $fc = $foreignCustomers->get((int) mb_substr($key, 1));
            if ($fc === null || ! $customers->has((int) $fc->customer_id)) {
                return null;
            }

            return [(int) $fc->customer_id, (int) $fc->id, (string) ($fc->displayLabel())];
        }

        $customer = $customers->get((int) mb_substr($key, 1));

        return $customer !== null
            ? [(int) $customer->id, null, (string) ($customer->displayLabel())]
            : null;
    }

    /**
     * Überlappungssekunden je Sitzung und Buchungsziel, auf Basis der
     * Zeiteinträge des Buchungs-Users (konsistent zur Verknüpfungslogik beim
     * Buchen). Schlüssel "Kunde|Endkunde" (Endkunde 0 = direkt beim Kunden).
     *
     * @param  Collection<int, RemotePendingSession>  $sessions
     * @return array<int, array<string, int>> Sitzung → ("Kunde|Endkunde" → Sekunden)
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

        $targetByProject = Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $entries->pluck('project_id')->unique()->all())
            ->whereNotNull('customer_id')
            ->get(['id', 'customer_id', 'foreign_customer_id'])
            ->mapWithKeys(fn (Project $p): array => [
                (int) $p->id => ((int) $p->customer_id) . '|' . (int) ($p->foreign_customer_id ?? 0),
            ]);

        $overlaps = [];
        foreach ($sessions as $session) {
            $sessionStart = $session->started_at->getTimestamp();
            $sessionEnd = $session->ended_at->getTimestamp();
            foreach ($entries as $entry) {
                $target = $targetByProject[$entry->project_id] ?? null;
                if ($target === null || $entry->started_at === null || $entry->ended_at === null) {
                    continue;
                }
                $seconds = min($entry->ended_at->getTimestamp(), $sessionEnd)
                    - max($entry->started_at->getTimestamp(), $sessionStart);
                if ($seconds > 0) {
                    $overlaps[(int) $session->id][$target] = ($overlaps[(int) $session->id][$target] ?? 0) + $seconds;
                }
            }
        }

        return $overlaps;
    }

    /**
     * Gelerntes Token-Wörterbuch: Aliasse bereits importierter Sitzungen und
     * Namen von Geräten mit Fernwartungs-ID, jeweils auf Kunde + Endkunde des
     * Geräts gemappt. Nur Tokens, die auf genau EINEN Kunden zeigen, sind
     * verwertbar; der Endkunde wird nur übernommen, wenn ihn alle Quellen
     * einhellig belegen. Mehrkundengeräte bleiben außen vor.
     *
     * @return array<string, array{customer: int, foreign: int|null}> Token → Buchungsziel
     */
    private function learnedTokenMap(Organization $organization): array {
        /** @var array<string, array<string, true>> $tokenTargets Token → ("Kunde|Endkunde" → true) */
        $tokenTargets = [];

        $record = function (?string $text, ?int $customerId, ?int $foreignId) use (&$tokenTargets): void {
            if ($customerId === null) {
                return;
            }
            foreach ($this->tokenize((string) $text) as $token) {
                $tokenTargets[$token][$customerId . '|' . (int) $foreignId] = true;
            }
        };

        // Aliasse importierter Sitzungen → Kunde/Endkunde des zugeordneten Geräts.
        RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_IMPORTED)
            ->whereNotNull('asset_id')
            ->whereNotNull('alias')
            ->with('asset:id,customer_id,foreign_customer_id,shared_remote')
            ->get(['id', 'asset_id', 'alias'])
            ->each(function (RemotePendingSession $row) use ($record): void {
                $asset = $row->asset;
                if ($asset instanceof Asset && ! $asset->shared_remote && $asset->customer_id !== null) {
                    $record($row->alias, (int) $asset->customer_id, $asset->foreign_customer_id !== null ? (int) $asset->foreign_customer_id : null);
                }
            });

        // Namen der Geräte, die bereits eine Fernwartungs-ID tragen.
        $refs = ExternalReference::query()
            ->forPlugin($organization, RemoteSupportPlugin::ID)
            ->whereIn('external_type', array_values(RemoteDeviceRegistry::DEVICE_TYPES))
            ->where('referenceable_type', (new Asset)->getMorphClass())
            ->pluck('referenceable_id');

        Asset::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $refs->unique()->all())
            ->where('shared_remote', false)
            ->whereNotNull('customer_id')
            ->get(['id', 'name', 'customer_id', 'foreign_customer_id'])
            ->each(fn (Asset $asset) => $record($asset->name, (int) $asset->customer_id, $asset->foreign_customer_id !== null ? (int) $asset->foreign_customer_id : null));

        $map = [];
        foreach ($tokenTargets as $token => $targets) {
            $customerIds = [];
            $foreignIds = [];
            foreach (array_keys($targets) as $target) {
                [$customerPart, $foreignPart] = explode('|', (string) $target);
                $customerIds[(int) $customerPart] = true;
                $foreignIds[(int) $foreignPart] = true;
            }
            if (count($customerIds) !== 1) {
                continue;
            }
            $foreignId = count($foreignIds) === 1 ? (int) array_key_first($foreignIds) : 0;
            $map[$token] = [
                'customer' => (int) array_key_first($customerIds),
                'foreign' => $foreignId !== 0 ? $foreignId : null,
            ];
        }

        return $map;
    }

    /**
     * Genau EIN fernwartbares Gerät des Kunden ohne ID dieses Providers ⇒
     * konkreter Gerätevorschlag fürs „Bestehendes Gerät"-Formular. Mit
     * Endkunden-Vorschlag werden dessen Geräte bevorzugt.
     *
     * @return array{0: string|null, 1: string|null} [Sqid, Anzeigename]
     */
    private function singleFreeAsset(Organization $organization, int $customerId, string $provider, ?int $foreignId = null): array {
        $assets = Asset::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $customerId)
            ->whereIn('category_code', RemoteDeviceRegistry::REMOTE_CATEGORY_CODES)
            ->get(['id', 'name', 'asset_no', 'foreign_customer_id']);

        if ($assets->isEmpty()) {
            return [null, null];
        }

        $deviceType = RemoteDeviceRegistry::DEVICE_TYPES[$provider] ?? null;
        if ($deviceType === null) {
            return [null, null];
        }

        $taken = ExternalReference::query()
            ->forPlugin($organization, RemoteSupportPlugin::ID, $deviceType)
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

        // Endkunden-Geräte zuerst: eindeutiges freies Gerät des Endkunden
        // gewinnt, sonst Rückfall auf den gesamten Kundenbestand.
        $asset = null;
        if ($foreignId !== null) {
            $ofForeign = $free->filter(fn (Asset $a): bool => (int) ($a->foreign_customer_id ?? 0) === $foreignId)->values();
            $asset = $ofForeign->count() === 1 ? $ofForeign->first() : null;
        }
        $asset ??= $free->count() === 1 ? $free->first() : null;
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
     * Fremdkunden (Endkunden) der Organisation, nach ID indiziert.
     *
     * @return Collection<int, ForeignCustomer>
     */
    private function foreignCustomers(Organization $organization): Collection {
        return ForeignCustomer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->get(['id', 'customer_id', 'name', 'company', 'matchcode'])
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
    private function significantWordsOf(string $name): array {
        $parts = preg_split('/[^\p{L}]+/u', mb_strtoupper($name)) ?: [];

        return array_values(array_filter(
            $parts,
            fn (string $w): bool => $w !== '' && ! in_array($w, self::NAME_STOPWORDS, true),
        ));
    }

    /** Akronym aus den Initialen der markanten Namenswörter (z. B. „SG"). */
    private function acronymOf(string $name): string {
        return implode('', array_map(
            fn (string $w): string => mb_substr($w, 0, 1),
            $this->significantWordsOf($name),
        ));
    }

    /**
     * Prüft, ob das Token als Buchstaben-Subsequenz im Namen steckt —
     * beginnend an einem Wortanfang (fängt „GSL" in „Gebr. Schwabenland
     * Großküchen", vermeidet aber Treffer mitten im Wort).
     */
    private function matchesSubsequenceName(string $token, string $name): bool {
        if (mb_strlen($token) < 2 || mb_strlen($token) > 6) {
            return false;
        }

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
