<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\{KnowledgePage, KnowledgeSection, KnowledgeSpace};
use App\Services\Collections\Import\{ImportedDocument, ObsidianVaultReader};
use App\Services\Knowledge\Pages\{KnowledgePageAccess, KnowledgePageImporter};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;

/**
 * Import aus Markdown-Dateien (Feature 191, MVP-1664): jede Datei wird ein
 * Entwurf im gewählten Bereich, YAML-Kopf (`title`, `tags`) und
 * `#schlagwörter` werden übernommen wie beim Obsidian-Import.
 */
class KnowledgePageImportController extends Controller {
    public const int MAX_FILES = 50;

    public function __construct(private readonly KnowledgePageAccess $access) {}

    public function create(): View {
        Gate::authorize('create', KnowledgePage::class);

        return view('knowledge-pages._import_dialog', [
            'spaces' => KnowledgeSpace::query()->whereIn('id', $this->access->readableSpaceIds($this->authUser()) ?: [0])->with('sections')->orderBy('position')->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request, ObsidianVaultReader $reader, KnowledgePageImporter $importer): RedirectResponse {
        Gate::authorize('create', KnowledgePage::class);
        $user = $this->authUser();
        $data = $request->validate([
            'space' => ['required', 'string', 'max:64'],
            'section' => ['nullable', 'string', 'max:64'],
            'files' => ['required', 'array', 'min:1', 'max:' . self::MAX_FILES],
            'files.*' => ['file', 'max:' . (int) (ObsidianVaultReader::MAX_FILE_BYTES / 1024), 'mimetypes:text/plain,text/markdown,text/x-markdown'],
        ]);
        $spaceId = Sqid::decode(KnowledgeSpace::class, (string) $data['space']);
        $space = $spaceId !== null ? KnowledgeSpace::query()->find($spaceId) : null;
        abort_if(! $space instanceof KnowledgeSpace || ! $this->access->canReadSpace($user, (int) $space->id), 404);
        $sectionId = Sqid::decode(KnowledgeSection::class, (string) ($data['section'] ?? ''));
        $section = $sectionId !== null ? KnowledgeSection::query()->where('knowledge_space_id', $space->id)->find($sectionId) : null;

        $created = DB::transaction(function () use ($request, $reader, $importer, $space, $section, $user): int {
            $count = 0;
            foreach ((array) $request->file('files') as $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }
                $name = $file->getClientOriginalName();
                $parsed = $reader->parse('upload:' . $name, $name, (string) $file->getContent());
                $importer->importDocument($space, $section, $user, new ImportedDocument(
                    externalId: $parsed->externalId,
                    title: $parsed->title,
                    text: $parsed->text,
                    tags: $parsed->tags,
                    sourceLabel: (string) __('knowledge_pages.import.upload_source', ['file' => $name]),
                ));
                $count++;
            }

            return $count;
        });

        return redirect()->route('knowledge.pages.index', array_filter(['space' => $space->sqid, 'section' => $section?->sqid]))
            ->with('success', __('knowledge_pages.flash.imported', ['count' => $created]));
    }
}
