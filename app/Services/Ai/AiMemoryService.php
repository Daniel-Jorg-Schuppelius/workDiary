<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiMemoryService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\AiMemoryEntryType;
use App\Models\Ai\AiMemoryEntry;
use App\Models\{Organization, User};
use App\Services\Ai\Dto\{ExamplePair, GlossaryEntry};
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;

/**
 * KI-Gedächtnis (Feature 025, MVP-401): stellt Capability-Consumern die
 * kuratierten Einträge budget-getrimmt bereit. Vorrang bei Knappheit:
 * Kunde vor Organisation vor Capability-Default, innerhalb der Ebene
 * Nutzungshäufigkeit vor Aktualität. „Lernen" passiert ausschließlich
 * über {@see rememberLearned()} nach expliziter Nutzer-Bestätigung —
 * es gibt keinen stillen Schreibpfad. Kein Fine-Tuning: die Wirkung
 * entsteht allein durch Prompt-Einspeisung.
 */
class AiMemoryService {
    /**
     * Budget-getrimmte, vorrangsortierte Einträge für einen Aufruf.
     * Capability-Defaults gelten nur für die angefragte Capability;
     * Kunden-Einträge nur für den übergebenen Kunden.
     *
     * @return Collection<int, AiMemoryEntry>
     */
    public function entriesFor(
        Organization $organization,
        string $capabilityKey,
        ?int $customerId = null,
        ?AiMemoryEntryType $type = null,
    ): Collection {
        $query = AiMemoryEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->where(function ($q) use ($customerId, $capabilityKey): void {
                $q->where(function ($org): void {
                    $org->whereNull('customer_id')->whereNull('capability');
                });
                if ($customerId !== null) {
                    $q->orWhere('customer_id', $customerId);
                }
                $q->orWhere('capability', $capabilityKey);
            });

        if ($type !== null) {
            $query->where('entry_type', $type->value);
        }

        $entries = $query->get()
            ->sort(function (AiMemoryEntry $a, AiMemoryEntry $b): int {
                return [$a->priorityRank(), -$a->usage_count, -$a->created_at?->getTimestamp()]
                    <=> [$b->priorityRank(), -$b->usage_count, -$b->created_at?->getTimestamp()];
            })
            ->values();

        return $this->trimToBudget($entries);
    }

    /** @return list<GlossaryEntry> */
    public function glossaryFor(
        Organization $organization,
        string $capabilityKey,
        ?int $customerId = null,
        ?string $targetLanguage = null,
    ): array {
        return array_values($this->entriesFor($organization, $capabilityKey, $customerId, AiMemoryEntryType::Glossary)
            ->map(static function (AiMemoryEntry $entry) use ($targetLanguage): GlossaryEntry {
                $translation = $targetLanguage !== null
                    ? (($entry->translations ?? [])[$targetLanguage] ?? null)
                    : null;

                return new GlossaryEntry(
                    term: (string) $entry->term,
                    meaning: $entry->content,
                    translation: $translation,
                );
            })
            ->all());
    }

    /**
     * Ausgelieferte Default-Regeln (Feature 084 MVP-404, Vollaudit 2026-07
     * M35): editierbare StyleRule-Einträge je Fakturierungs-Capability
     * (Kundennamen-Verbot + Nominalstil). Idempotent — vorhandene
     * Default-Einträge gleichen Inhalts werden nicht dupliziert; gesät beim
     * Anlegen einer KI-Verbindung.
     */
    public function seedDefaults(Organization $organization, ?int $userId = null): int {
        $rules = [
            __('Nenne niemals den Namen des Kunden oder Empfängers — neutral formulieren (z. B. „der Kunde").'),
            __('Nominalstil verwenden: knappe, sachliche Leistungsbeschreibungen ohne Ich-/Wir-Form.'),
        ];
        $capabilities = [
            \App\Services\Ai\Suggestions\ItemTextSuggestionService::CAPABILITY_ITEM,
            \App\Services\Ai\Suggestions\ItemTextSuggestionService::CAPABILITY_BLOCK,
            \App\Services\Ai\Suggestions\ItemTextSuggestionService::CAPABILITY_QUOTE_ITEM,
        ];

        $created = 0;
        foreach ($capabilities as $capability) {
            foreach ($rules as $content) {
                $exists = AiMemoryEntry::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereNull('customer_id')
                    ->where('capability', $capability)
                    ->where('entry_type', AiMemoryEntryType::StyleRule->value)
                    ->where('content', $content)
                    ->exists();
                if ($exists) {
                    continue;
                }

                AiMemoryEntry::query()->create([
                    'organization_id' => $organization->id,
                    'customer_id' => null,
                    'capability' => $capability,
                    'entry_type' => AiMemoryEntryType::StyleRule->value,
                    'content' => $content,
                    'origin' => AiMemoryEntry::ORIGIN_DEFAULT,
                    'active' => true,
                    'created_by_user_id' => $userId,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** @return list<string> */
    public function styleRulesFor(
        Organization $organization,
        string $capabilityKey,
        ?int $customerId = null,
    ): array {
        return array_values($this->entriesFor($organization, $capabilityKey, $customerId, AiMemoryEntryType::StyleRule)
            ->map(static fn (AiMemoryEntry $entry): string => $entry->content)
            ->all());
    }

    /** @return list<ExamplePair> */
    public function examplesFor(
        Organization $organization,
        string $capabilityKey,
        ?int $customerId = null,
    ): array {
        return array_values($this->entriesFor($organization, $capabilityKey, $customerId, AiMemoryEntryType::Example)
            ->map(static fn (AiMemoryEntry $entry): ExamplePair => new ExamplePair(
                source: (string) $entry->source_text,
                target: $entry->content,
            ))
            ->all());
    }

    /**
     * Nutzungsstatistik für die Vorrangs-Priorisierung nachführen —
     * bewusst leichtgewichtig (keine Model-Events, kein Audit-Spam).
     *
     * @param Collection<int, AiMemoryEntry> $entries
     */
    public function markUsed(Collection $entries): void {
        $ids = $entries->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        AiMemoryEntry::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->update([
                'usage_count' => DB::raw('usage_count + 1'),
                'last_used_at' => Carbon::now(),
            ]);
    }

    /**
     * Bestätigter Lernvorschlag aus dem „Merken?"-Dialog (Feature 025:
     * Lernen NIE still). Der Aufrufer übergibt die vom Nutzer bestätigten
     * Felder; der Eintrag wird als `learned` gekennzeichnet und ist wie
     * jeder andere editier-/deaktivier-/löschbar.
     *
     * @param array{customer_id?: int|null, capability?: string|null, entry_type: AiMemoryEntryType, term?: string|null, content: string, source_text?: string|null, translations?: array<string, string>|null} $attributes
     */
    public function rememberLearned(Organization $organization, ?User $user, array $attributes): AiMemoryEntry {
        return AiMemoryEntry::query()->create([
            'organization_id' => $organization->id,
            'customer_id' => $attributes['customer_id'] ?? null,
            'capability' => $attributes['capability'] ?? null,
            'entry_type' => $attributes['entry_type'],
            'term' => $attributes['term'] ?? null,
            'content' => $attributes['content'],
            'source_text' => $attributes['source_text'] ?? null,
            'translations' => $attributes['translations'] ?? null,
            'origin' => AiMemoryEntry::ORIGIN_LEARNED,
            'active' => true,
            'created_by_user_id' => $user?->getKey(),
        ]);
    }

    /**
     * DSGVO-Export (Feature 016/043): alle Einträge einer Organisation,
     * optional auf einen Kunden gefiltert — inklusive inaktiver Einträge.
     *
     * @return list<array<string, mixed>>
     */
    public function exportFor(Organization $organization, ?int $customerId = null): array {
        return array_values(AiMemoryEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->when($customerId !== null, static fn ($q) => $q->where('customer_id', $customerId))
            ->orderBy('id')
            ->get()
            ->map(static fn (AiMemoryEntry $entry): array => [
                'ebene' => $entry->customer_id !== null ? 'kunde' : ($entry->capability !== null ? 'capability' : 'organisation'),
                'customer_id' => $entry->customer_id,
                'capability' => $entry->capability,
                'typ' => $entry->entry_type->value,
                'begriff' => $entry->term,
                'inhalt' => $entry->content,
                'rohtext' => $entry->source_text,
                'uebersetzungen' => $entry->translations,
                'herkunft' => $entry->origin,
                'aktiv' => $entry->active,
                'angelegt_am' => $entry->created_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Löschkaskade zum Kundenlebenszyklus (zusätzlich zur DB-Kaskade
     * explizit aufrufbar, z. B. bei Anonymisierung statt Löschung).
     * Abgeleitete Provider-Glossare (DeepL-Sync, MVP-409) hängen sich
     * hier ein, sobald sie existieren.
     */
    public function deleteForCustomer(Organization $organization, int $customerId): int {
        return AiMemoryEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $customerId)
            ->get()
            ->each(static fn (AiMemoryEntry $entry) => $entry->delete()) // einzeln → Audit je Eintrag
            ->count();
    }

    /**
     * @param Collection<int, AiMemoryEntry> $entries
     * @return Collection<int, AiMemoryEntry>
     */
    private function trimToBudget(Collection $entries): Collection {
        $budget = max(500, (int) config('ai.memory_budget_characters', 4000));
        $used = 0;

        return $entries
            ->filter(function (AiMemoryEntry $entry) use (&$used, $budget): bool {
                $weight = $entry->promptWeight();
                if ($used + $weight > $budget) {
                    return false;
                }
                $used += $weight;

                return true;
            })
            ->values();
    }
}
