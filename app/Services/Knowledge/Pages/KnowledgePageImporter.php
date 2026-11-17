<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use App\Models\Knowledge\{KnowledgePage, KnowledgeSection, KnowledgeSpace};
use App\Models\Platform\User;
use App\Services\Collections\Import\ImportedDocument;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Str;

/**
 * Übernahme von Markdown als Wissensseiten (Feature 191, MVP-1664): einzelne
 * Dateien und die Einbahn-Übernahme aus Obsidian und OneNote (MVP-815), die
 * damit Wissensseiten als Ziel bekommt. Der erste Ordner bzw. Abschnitt wird
 * Rubrik, tiefere Ordner werden Seiten mit Unterseiten (bis zur Baumtiefe);
 * jede Datei wird ein Entwurf, den die verantwortliche Person freigibt.
 */
final class KnowledgePageImporter {
    public function __construct(private readonly KnowledgePageService $pages) {}

    /** Bereich für eine Übernahme: gleichnamigen mitbenutzen, sonst anlegen. */
    public function space(int $organizationId, User $actor, string $title): KnowledgeSpace {
        $title = Str::limit(trim($title) !== '' ? trim($title) : (string) __('knowledge_pages.import.untitled'), 180, '');

        return KnowledgeSpace::query()->firstOrCreate(
            ['organization_id' => $organizationId, 'title' => $title],
            ['position' => (int) KnowledgeSpace::query()->where('organization_id', $organizationId)->max('position') + 1, 'created_by' => $actor->id, 'updated_by' => $actor->id],
        );
    }

    public function importDocument(KnowledgeSpace $space, ?KnowledgeSection $section, User $actor, ImportedDocument $document): KnowledgePage {
        $folders = array_values(array_filter(array_map('trim', $document->folders), static fn (string $folder): bool => $folder !== ''));
        if ($section === null && $folders !== []) {
            $section = $this->section($space, $actor, (string) array_shift($folders));
        }
        $parent = null;
        foreach (array_slice($folders, 0, KnowledgePage::MAX_DEPTH - 1) as $folder) {
            $parent = $this->container($space, $section, $parent, $actor, $folder);
        }
        $body = $document->text;
        if (mb_strlen($body) > KnowledgePage::MAX_BODY_LENGTH) {
            $body = StringHelper::truncate($body, KnowledgePage::MAX_BODY_LENGTH, '…');
        }

        return $this->pages->create($space, $section, $parent, $actor, [
            'title' => $this->title($document->title),
            'body' => $body,
            'tags' => $document->tags !== [] ? implode(', ', $document->tags) : null,
            'note' => (string) __('knowledge_pages.import.note', ['source' => Str::limit($document->sourceLabel !== '' ? $document->sourceLabel : $document->title, 400, '…')]),
        ]);
    }

    private function section(KnowledgeSpace $space, User $actor, string $title): KnowledgeSection {
        return KnowledgeSection::query()->firstOrCreate(
            ['knowledge_space_id' => $space->id, 'title' => $this->title($title)],
            [
                'organization_id' => $space->organization_id,
                'position' => (int) KnowledgeSection::query()->where('knowledge_space_id', $space->id)->max('position') + 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
    }

    /** Ordner als Seite, unter der die Dateien als Unterseiten hängen. */
    private function container(KnowledgeSpace $space, ?KnowledgeSection $section, ?KnowledgePage $parent, User $actor, string $title): KnowledgePage {
        $existing = KnowledgePage::query()
            ->where('knowledge_space_id', $space->id)
            ->where('knowledge_section_id', $parent !== null ? $parent->knowledge_section_id : $section?->id)
            ->where('parent_id', $parent?->id)
            ->where('title', $this->title($title))
            ->first();

        return $existing ?? $this->pages->create($space, $section, $parent, $actor, ['title' => $this->title($title), 'body' => '']);
    }

    private function title(string $title): string {
        $title = trim($title);

        return Str::limit($title !== '' ? $title : (string) __('knowledge_pages.import.untitled'), 180, '');
    }
}
