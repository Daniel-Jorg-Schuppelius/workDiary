<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchingTimeGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\{Organization, User};
use App\Services\Integration\Concerns\ResolvesInboxTargets;
use App\Services\Integration\{InboxGroupBooker, ProjectKeywordMatcher};
use App\Support\Sqid;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Gemeinsamer Inbox-Adapter der Zeit-Migrations-Plugins: bindet die
 * gruppierten Import-Einträge einer {@see MatchingTimeImportService}-Pipeline
 * an die universelle Zuordnungs-Inbox — offene Gruppen samt Fuzzy-Vorschlägen,
 * Kunde + Projekt (existierend-oder-neu) auflösen, Buchung/Verwerfen an den
 * Service delegieren.
 */
abstract class MatchingTimeGroupBooker implements InboxGroupBooker {
    use ResolvesInboxTargets;

    public function __construct(protected readonly MatchingTimeImportService $service) {}

    /** Plugin-Id für die Gruppen-Zeilen der Inbox-Übersicht. */
    abstract protected function bookerPluginId(): string;

    public function groups(Organization $organization): Collection {
        /** @var Collection<int, array<string, mixed>> $groups */
        $groups = $this->service->openInboxGroups($organization)->map(function (array $group) use ($organization): array {
            // Benutzer-Zuordnungsfall (MVP-509): Projekt je Eintrag bekannt,
            // nur der Quell-Benutzer ist nicht auflösbar — eigenes Formular.
            if ($this->service->isUserGroupKey((string) $group['group_key'])) {
                $email = substr((string) $group['group_key'], strlen(MatchingTimeImportService::PENDING_USER_GROUP_PREFIX));

                return [
                    'plugin_id' => $this->bookerPluginId(),
                    'form' => 'user',
                    'group_key' => $group['group_key'],
                    'user_email' => $email !== MatchingTimeImportService::PENDING_USER_NO_SIGNAL ? $email : null,
                    'client_name' => $group['client_name'],
                    'project_name' => $group['project_name'],
                    'workspace_name' => $group['workspace_name'],
                    'count' => $group['count'],
                    'minutes' => $group['minutes'],
                    'first_seen' => $group['first_seen'],
                    'last_seen' => $group['last_seen'],
                    'entries' => $group['entries'],
                    'entries_more' => $group['entries_more'],
                ];
            }

            // Fremdkunden-Treffer (Endkunde) schlägt den Kunden-Vorschlag vor —
            // der Kunde ergibt sich dann aus der Firma des Fremdkunden.
            $foreign = $this->service->suggestForeignCustomer($organization, $group['client_name']);
            $customer = $foreign !== null ? $foreign->customer : $this->service->suggestCustomer($organization, $group['client_name']);
            $project = $this->service->suggestProject($organization, $customer, $group['project_name'], $foreign);
            // Kein Namenstreffer: Schlüsselwörter aus den Beschreibungen der
            // Gruppe (MVP-483) — reine Vorbelegung, gebucht wird per Hand.
            if ($project === null && ($foreign !== null || $customer !== null)) {
                $descriptions = array_map(
                    static fn (array $entry): string => (string) ($entry['description'] ?? ''),
                    $group['entries'],
                );
                $project = app(ProjectKeywordMatcher::class)
                    ->match($organization, $foreign ?? $customer, (string) $group['project_name'], ...$descriptions)
                    ?->project;
            }
            // Gehört das vorgeschlagene Projekt einem Endkunden, den Endkunden
            // mit vorschlagen — sonst kollidiert die Vorauswahl mit der
            // „Kein Fremdkunde = nur Firmen-Projekte"-Regel.
            if ($foreign === null && $project?->foreign_customer_id !== null) {
                $foreign = $project->foreignCustomer;
                $customer ??= $foreign?->customer;
            }

            return [
                'plugin_id' => $this->bookerPluginId(),
                'form' => 'customer_project',
                'group_key' => $group['group_key'],
                'client_name' => $group['client_name'],
                'project_name' => $group['project_name'],
                'workspace_name' => $group['workspace_name'],
                'count' => $group['count'],
                'minutes' => $group['minutes'],
                'first_seen' => $group['first_seen'],
                'last_seen' => $group['last_seen'],
                'entries' => $group['entries'],
                'entries_more' => $group['entries_more'],
                'suggested_customer_sqid' => $customer?->sqid,
                'suggested_foreign_sqid' => $foreign?->sqid,
                'suggested_project_sqid' => $project?->sqid,
            ];
        })->values();

        return $groups;
    }

    public function rules(): array {
        // Benutzer-Zuordnungsfälle (MVP-509) haben ein eigenes, schlankes
        // Formular — der Gruppen-Schlüssel des Requests entscheidet.
        if ($this->service->isUserGroupKey((string) request()->input('group_key'))) {
            return [
                'user' => ['required', 'string'],
            ];
        }

        return [
            'customer_mode' => ['required', 'in:existing,new,internal'],
            'customer' => ['nullable', 'string', 'required_if:customer_mode,existing'],
            'new_customer_name' => ['nullable', 'string', 'max:191', 'required_if:customer_mode,new'],
            'foreign_mode' => ['nullable', 'in:none,existing,new'],
            'foreign_customer' => ['nullable', 'string', 'required_if:foreign_mode,existing'],
            'new_foreign_customer_name' => ['nullable', 'string', 'max:191', 'required_if:foreign_mode,new'],
            'project_mode' => ['required', 'in:existing,new'],
            'project' => ['nullable', 'string', 'required_if:project_mode,existing'],
            'new_project_name' => ['nullable', 'string', 'max:191', 'required_if:project_mode,new'],
        ];
    }

    public function book(Organization $organization, string $groupKey, array $input): array {
        // Benutzer-Zuordnungsfall (MVP-509): gewählten Benutzer buchen und die
        // E-Mail-Zuordnung merken; das Projekt löst der Service je Eintrag auf.
        if ($this->service->isUserGroupKey($groupKey)) {
            $userId = Sqid::decode(User::class, (string) ($input['user'] ?? ''));
            if ($userId === null) {
                throw ValidationException::withMessages(['user' => (string) __('Bitte einen Benutzer wählen.')]);
            }

            return $this->service->bookInboxGroup($organization, $groupKey, null, null, $userId);
        }

        if (($input['customer_mode'] ?? null) === 'internal') {
            $project = $this->resolveStandaloneProject($organization, $input);

            return $this->service->bookInboxGroup($organization, $groupKey, null, $project);
        }

        $customer = $this->resolveCustomerTarget($organization, $input);
        $foreign = $this->resolveForeignCustomerTarget($organization, $customer, $input);
        $project = $this->resolveProjectUnderCustomer($organization, $customer, $input, $foreign);

        return $this->service->bookInboxGroup($organization, $groupKey, $customer, $project, foreignCustomer: $foreign);
    }

    public function dismiss(Organization $organization, string $groupKey): int {
        return $this->service->dismissInboxGroup($organization, $groupKey);
    }
}
