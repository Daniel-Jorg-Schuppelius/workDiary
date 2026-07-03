<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureApplicabilityResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procedure;

use App\Models\{DiaryEntry, ProcedureTemplate, ProcedureTemplateVersion};
use Illuminate\Support\{Carbon, Collection};

/**
 * Filtert veroeffentlichte Prozedurvorlagen anhand des
 * `applicability`-JSONs ihrer aktuellen Version gegen einen
 * Tagebucheintrag (MVP-025 §3.2 & §8.4 / ../WorkDiary-Architecture/prozedurvorlagen.md).
 *
 * Unterstuetzte Kriterien:
 *  - `diary_entry_type`: Liste von EntryType-Slugs (OR).
 *  - `customer_ids`: Liste von Kunden-Ids (OR).
 *  - `tags_any`: Liste von Tag-Namen; min. einer muss am Eintrag haengen.
 *
 * Fehlt ein Kriterium oder die gesamte `applicability`, gilt die
 * Vorlage als "universell anwendbar" und wird vorgeschlagen.
 */
class ProcedureApplicabilityResolver {
    public function __construct(private readonly ProcedureTemplateService $templates) {}

    /**
     * @return Collection<int, ProcedureTemplate>
     */
    public function suggestFor(DiaryEntry $entry): Collection {
        $candidates = ProcedureTemplate::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('active', true)
            ->get();

        $entryTypeSlug = $entry->entryType?->slug;
        $entryCustomerId = $entry->customer_id;
        /** @var array<int, string> $entryTags */
        $entryTags = $entry->relationLoaded('tags')
            ? $entry->tags->pluck('name')->all()
            : $entry->tags()->pluck('name')->all();

        return $candidates
            ->map(fn(ProcedureTemplate $tpl): ?array => ($current = $this->templates->currentVersionFor($tpl)) === null
                ? null
                : ['template' => $tpl, 'version' => $current])
            ->filter()
            ->filter(fn(array $row): bool => $this->matches(
                $row['version'],
                $entryTypeSlug,
                $entryCustomerId,
                $entryTags,
            ))
            ->map(fn(array $row): ProcedureTemplate => $row['template'])
            ->values();
    }

    /**
     * @param  array<int, string>  $entryTags
     */
    private function matches(ProcedureTemplateVersion $version, ?string $entryTypeSlug, ?int $entryCustomerId, array $entryTags): bool {
        $rules = $version->applicability ?? [];
        if ($rules === []) {
            return true;
        }

        if (isset($rules['diary_entry_type']) && is_array($rules['diary_entry_type']) && $rules['diary_entry_type'] !== []) {
            if ($entryTypeSlug === null || ! in_array($entryTypeSlug, $rules['diary_entry_type'], true)) {
                return false;
            }
        }

        if (isset($rules['customer_ids']) && is_array($rules['customer_ids']) && $rules['customer_ids'] !== []) {
            if ($entryCustomerId === null || ! in_array($entryCustomerId, $rules['customer_ids'], true)) {
                return false;
            }
        }

        if (isset($rules['tags_any']) && is_array($rules['tags_any']) && $rules['tags_any'] !== []) {
            if (array_intersect($rules['tags_any'], $entryTags) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hilfsmethode fuer ad-hoc-Pruefungen ausserhalb des
     * Tagebucheintrag-Kontextes.
     *
     * @param  array<string, mixed>  $context
     */
    public function templateAppliesAt(ProcedureTemplate $template, array $context, ?Carbon $at = null): bool {
        $version = $this->templates->currentVersionFor($template, $at);
        if ($version === null) {
            return false;
        }
        return $this->matches(
            $version,
            $context['diary_entry_type'] ?? null,
            $context['customer_id'] ?? null,
            $context['tags'] ?? [],
        );
    }
}
