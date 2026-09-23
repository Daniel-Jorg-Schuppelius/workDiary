<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Enums\Search\SearchSourceType;
use App\Models\{Document, User};
use App\Services\Content\ContentSubjectResolver;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Verwaltete Dokumente im Tätigkeitsindex (MVP-819). Bis dahin fand man ein
 * Dokument nur über ein Stück seines Titels — der Dateiinhalt war nirgends
 * durchsuchbar, obwohl die Extraktion für die KI-Vorschläge längst lief.
 *
 * Zwei Grenzen sind Absicht:
 *
 * - **Personalakten bleiben draußen.** Sie haben einen eigenen Zugriffskreis
 *   (`hrFile.viewAny`), den die Indexsichtbarkeit mit ihrem Raster aus
 *   „unbeschränkt oder eigenes" nicht abbilden kann. Lieber nicht auffindbar
 *   als falsch auffindbar.
 * - **Vertrauliche Dokumente tragen `restricted`.** Damit gilt im Index
 *   dieselbe Regel wie in der Liste: Erfasser oder `document.confidential.manage`.
 *
 * Der Dateitext kommt aus {@see \App\Models\DocumentVersionText}, den der
 * {@see \App\Jobs\ExtractDocumentTextJob} einmal je Version füllt; fehlt er
 * noch, wird das Dokument ohne ihn indiziert und nach der Extraktion erneut.
 */
final class DocumentSource extends AbstractSearchSource {
    private readonly ContentSubjectResolver $subjects;

    public function __construct(?ContentSubjectResolver $subjects = null) {
        $this->subjects = $subjects ?? app(ContentSubjectResolver::class);
    }

    public function type(): SearchSourceType {
        return SearchSourceType::Document;
    }

    protected function scope(Builder $query): Builder {
        return $query
            ->where(static fn (Builder $q) => $q
                ->whereNull('documentable_type')
                ->orWhere('documentable_type', '!=', MorphMap::alias(User::class)))
            ->with(['tags:id,name', 'currentVersion.extractedText'])
            ->with($this->subjects->eagerLoad(Document::class));
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var Document $model */
        $organizationId = self::organizationOf($model);
        if ($organizationId === null || $model->trashed() || $model->isPersonnelFile()) {
            return null;
        }

        $subject = $this->subjects->resolve($model);
        $fileText = $model->currentVersion?->extractedText?->text;

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: trim((string) $model->title),
            excerpt: self::join("\n\n", $model->description, self::firstLine($fileText)) ?: null,
            primaryTexts: [$model->title],
            texts: [
                $model->description,
                $model->document_type->label(),
                $fileText,
                ...self::strings($model->tags, 'name'),
                $context->userName(self::intOrNull($model->created_by_user_id)),
            ],
            occurredAt: $model->created_at,
            userId: self::intOrNull($model->created_by_user_id),
            customerId: $subject->customer !== null ? (int) $subject->customer->id : null,
            foreignCustomerId: $subject->foreignCustomer !== null ? (int) $subject->foreignCustomer->id : null,
            projectId: $subject->project !== null ? (int) $subject->project->id : null,
            restricted: (bool) $model->confidential,
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
