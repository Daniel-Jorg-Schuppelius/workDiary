<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections\Import;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Models\{CommunicationNote, ContentCollection, ExternalReference, KnowledgeArticle, Organization, User};
use App\Services\Collections\{ContentCollectionService, ContentReferenceService};
use App\Services\Communication\CommunicationNoteService;
use App\Services\Knowledge\KnowledgeArticleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Einbahn-Übernahme aus Obsidian und OneNote (MVP-815, Feature 155).
 *
 * Ordner bzw. Abschnitte werden Sammlungen, jedes Dokument eine Notiz oder ein
 * Wissensartikel-Entwurf, Schlagwörter wandern mit, `[[Wikilinks]]` werden
 * Verweise. Jeder übernommene Inhalt trägt über {@see ExternalReference} seinen
 * Herkunftsvermerk; ein erneuter Lauf überspringt ihn — kein Rückschreiben,
 * kein laufender Abgleich.
 */
class KnowledgeImportService {
    public const TARGET_NOTE = 'note';

    public const TARGET_ARTICLE = 'knowledge_article';

    /** Obergrenze neuer Inhalte je Lauf; ein weiterer Lauf setzt fort. */
    public const MAX_DOCUMENTS = 300;

    private const NOTE_BODY_MAX = 8000;

    private const ARTICLE_PROBLEM_MAX = 10000;

    public function __construct(
        private readonly ContentCollectionService $collections,
        private readonly ContentReferenceService $references,
        private readonly CommunicationNoteService $notes,
        private readonly KnowledgeArticleService $articles,
    ) {}

    /**
     * @param  iterable<ImportedDocument>  $documents
     * @param  bool  $limited  der Leser hat vor der Obergrenze aufgehört
     */
    public function import(Organization $organization, User $actor, string $pluginId, string $externalType, string $rootTitle, string $target, iterable $documents, bool $limited = false): KnowledgeImportReport {
        $report = new KnowledgeImportReport;
        $report->limited = $limited;
        $root = $this->collection($organization, $actor, null, $rootTitle, $report);
        $report->root = $root;

        $byName = [];
        $imported = [];

        foreach ($documents as $document) {
            $existing = ExternalReference::query()->withoutGlobalScopes()
                ->forPlugin($organization->id, $pluginId, $externalType)
                ->forExternalId($document->externalId)
                ->first();
            $model = $existing?->referenceable;
            if ($model instanceof Model) {
                $report->skipped++;
                $this->remember($byName, $document, $model);

                continue;
            }
            if ($report->created >= self::MAX_DOCUMENTS) {
                $report->limited = true;

                continue;
            }

            $model = $target === self::TARGET_ARTICLE
                ? $this->article($actor, $document, $report)
                : $this->note($organization, $actor, $document, $report);
            ExternalReference::link($organization, $pluginId, $externalType, $model, $document->externalId, [
                'source' => Str::limit($document->sourceLabel, 500, '…'),
                'imported_by' => $actor->id,
            ]);

            $collection = $root;
            foreach (array_slice($document->folders, 0, ContentCollection::MAX_DEPTH - 1) as $folder) {
                $collection = $this->collection($organization, $actor, $collection, $folder, $report);
            }
            $this->collections->addItem($collection, $actor, $model);

            $report->created++;
            $this->remember($byName, $document, $model);
            $imported[] = ['model' => $model, 'document' => $document];
        }

        foreach ($imported as ['model' => $model, 'document' => $document]) {
            foreach (array_unique($document->linkNames) as $name) {
                $linked = $byName[self::key($name)] ?? null;
                if ($linked === null || $linked === $model) {
                    $report->linksUnresolved += $linked === null ? 1 : 0;

                    continue;
                }
                $this->references->add($model, $linked, $actor);
                $report->linksResolved++;
            }
        }

        $root->audit('collection.imported', ['plugin' => $pluginId, 'type' => $externalType, ...$report->counts()]);

        return $report;
    }

    /** Für die Leser: wurde dieser Inhalt schon übernommen? */
    public function knownChecker(Organization $organization, string $pluginId, string $externalType): \Closure {
        $known = ExternalReference::query()->withoutGlobalScopes()
            ->forPlugin($organization->id, $pluginId, $externalType)
            ->pluck('external_id')
            ->flip()
            ->all();

        return static fn (string $externalId): bool => isset($known[$externalId]);
    }

    private function note(Organization $organization, User $actor, ImportedDocument $document, KnowledgeImportReport $report): CommunicationNote {
        $body = $this->limit($document->text !== '' ? $document->text : $document->title, self::NOTE_BODY_MAX, $report);

        return $this->notes->create($organization, $actor, [
            'type' => CommunicationNoteType::General->value,
            'direction' => CommunicationDirection::Internal->value,
            'occurred_at' => ($document->modifiedAt !== null && $document->modifiedAt->isPast() ? $document->modifiedAt : now())->toDateTimeString(),
            'subject' => $this->title($document),
            'body' => $body,
            'tags' => implode(', ', $document->tags),
        ]);
    }

    private function article(User $actor, ImportedDocument $document, KnowledgeImportReport $report): KnowledgeArticle {
        return $this->articles->create($actor, [
            'title' => $this->title($document),
            'problem' => $this->limit($document->text !== '' ? $document->text : $document->title, self::ARTICLE_PROBLEM_MAX, $report),
            'solution' => '',
            'tags' => implode(', ', $document->tags),
        ]);
    }

    /** Vorhandene gleichnamige Sammlung an derselben Stelle mitbenutzen, sonst anlegen. */
    private function collection(Organization $organization, User $actor, ?ContentCollection $parent, string $title, KnowledgeImportReport $report): ContentCollection {
        $title = Str::limit(trim($title) !== '' ? trim($title) : (string) __('collections.import.untitled'), 180, '');
        $existing = ContentCollection::query()
            ->where('organization_id', $organization->id)
            ->where('parent_id', $parent?->id)
            ->whereNull('archived_at')
            ->where('title', $title)
            ->visibleTo($actor)
            ->first();
        if ($existing instanceof ContentCollection) {
            return $existing;
        }

        $report->collections++;

        return $this->collections->create($organization, $actor, ['title' => $title, 'parent_id' => $parent?->id]);
    }

    private function title(ImportedDocument $document): string {
        $title = trim($document->title);

        return Str::limit($title !== '' ? $title : (string) __('collections.import.untitled'), 180, '');
    }

    private function limit(string $text, int $max, KnowledgeImportReport $report): string {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $report->truncated++;

        return mb_substr($text, 0, $max - 1) . '…';
    }

    /** @param  array<string, Model>  $byName */
    private function remember(array &$byName, ImportedDocument $document, Model $model): void {
        foreach ([$document->title, ...$document->names] as $name) {
            $key = self::key($name);
            if ($key !== '' && ! isset($byName[$key])) {
                $byName[$key] = $model;
            }
        }
    }

    private static function key(string $name): string {
        return mb_strtolower(trim($name));
    }
}
