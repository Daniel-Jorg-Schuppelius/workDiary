<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentSubjectResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Support\Content;

use App\Models\Asset\Asset;
use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Disposal\DisposalJob;
use App\Models\Document\Document;
use App\Models\Form\FormSubmission;
use App\Models\Ideas\IdeaMap;
use App\Models\Platform\User;
use App\Models\Project\Project;
use Closure;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Die eine Stelle, die „zu wem gehört das?" beantwortet (MVP-818, Feature 155).
 *
 * Fünf Modelle drückten dieselbe Zugehörigkeit auf fünf Arten aus — Dokument
 * über `documentable`, Formular über `subject`, Notiz über `notable`,
 * Ideenlandkarte über drei Fremdschlüssel, Wissensartikel gar nicht. Deshalb
 * konnte keine typübergreifende Liste den Bezug zeigen. Hier wird er einmal
 * aufgelöst; die Listen zeigen ihn über `<x-subject-link>`.
 *
 * Der Träger ist die unmittelbare Kante, Kunde und Projekt werden daraus
 * abgeleitet — ein Dokument am Auftrag gehört damit sichtbar zum Kunden des
 * Auftrags, was dem Portal-Scope {@see Document::scopeVisibleToCustomer()}
 * entspricht.
 */
class ContentSubjectResolver {
    /**
     * Träger, über die ein Inhalt einem Kunden zugeordnet sein kann. Der Kunde
     * selbst steht daneben; diese hier tragen `customer_id`.
     *
     * @var list<class-string<Model>>
     */
    public const CUSTOMER_CARRIERS = [Project::class, DiaryEntry::class, Asset::class, DisposalJob::class];

    public function resolve(Model $model): ContentSubject {
        $carrier = match (true) {
            $model instanceof Document => $model->documentable,
            $model instanceof FormSubmission => $model->subject,
            $model instanceof CommunicationNote => $model->notable,
            // Die Ideenlandkarte trägt drei Schlüssel; der engste gewinnt.
            $model instanceof IdeaMap => $model->diaryEntry ?? $model->project ?? $model->customer,
            default => $model,
        };

        return $this->fromCarrier($carrier);
    }

    /** Der Bezug eines Trägers selbst — Grundlage von {@see resolve()}. */
    public function fromCarrier(?Model $carrier): ContentSubject {
        return match (true) {
            $carrier instanceof Customer => new ContentSubject(customer: $carrier),
            $carrier instanceof Project => new ContentSubject(
                customer: $carrier->customer,
                foreignCustomer: $carrier->foreignCustomer,
                project: $carrier,
            ),
            $carrier instanceof DiaryEntry => new ContentSubject(
                customer: $carrier->customer,
                project: $carrier->project,
                carrier: $carrier,
            ),
            $carrier instanceof Asset => new ContentSubject(
                customer: $carrier->customer,
                foreignCustomer: $carrier->foreignCustomer,
                carrier: $carrier,
            ),
            $carrier instanceof DisposalJob => new ContentSubject(
                customer: $carrier->customer,
                carrier: $carrier,
            ),
            // Personalakte: die Person ist der Träger, ein Kunde steht nicht dahinter.
            $carrier instanceof User => new ContentSubject(carrier: $carrier),
            default => new ContentSubject(),
        };
    }

    /**
     * Bedingung „der polymorphe Träger gehört zu diesem Kunden": der Kunde
     * selbst, seine Projekte, Aufträge, Anlagen und Entsorgungsaufträge.
     * Grundlage sowohl des Portal-Scopes {@see Document::scopeVisibleToCustomer()}
     * als auch des Kundenfilters der internen Listen — die Kette steht nur hier.
     *
     * @param  Builder<covariant Model>  $query
     */
    public function whereCarrierOfCustomer(Builder $query, string $typeColumn, string $idColumn, int $organizationId, int $customerId): void {
        $query->where(function (Builder $outer) use ($typeColumn, $idColumn, $organizationId, $customerId): void {
            $outer->where(static fn (Builder $q) => $q
                ->where($typeColumn, (new Customer)->getMorphClass())
                ->where($idColumn, $customerId));

            foreach (self::CUSTOMER_CARRIERS as $carrier) {
                $outer->orWhere(static fn (Builder $q) => $q
                    ->where($typeColumn, (new $carrier)->getMorphClass())
                    ->whereIn($idColumn, $carrier::query()
                        ->where('organization_id', $organizationId)
                        ->where('customer_id', $customerId)
                        ->select('id')));
            }
        });
    }

    /**
     * Kundenfilter für die Liste eines Inhaltstyps. Typen ohne Kundenbezug
     * (Wissensartikel, Lerninhalte) liefern nichts, statt alles.
     *
     * @param  Builder<covariant Model>  $query
     */
    public function filterByCustomer(Builder $query, int $organizationId, int $customerId): void {
        $model = $query->getModel();

        match (true) {
            $model instanceof Document => $this->whereCarrierOfCustomer($query, 'documentable_type', 'documentable_id', $organizationId, $customerId),
            $model instanceof FormSubmission => $this->whereCarrierOfCustomer($query, 'subject_type', 'subject_id', $organizationId, $customerId),
            $model instanceof CommunicationNote => $this->whereCarrierOfCustomer($query, 'notable_type', 'notable_id', $organizationId, $customerId),
            $model instanceof IdeaMap => $this->filterIdeaMapsByCustomer($query, $organizationId, $customerId),
            $model instanceof DiaryEntry, $model instanceof Project, $model instanceof Asset => $query->where('customer_id', $customerId),
            // Wissensartikel und Lerninhalte gehören keinem Kunden.
            default => $query->whereIn($model->getQualifiedKeyName(), []),
        };
    }

    /**
     * Die Ideenlandkarte trägt den Bezug in drei Spalten statt polymorph.
     *
     * @param  Builder<covariant Model>  $query
     */
    private function filterIdeaMapsByCustomer(Builder $query, int $organizationId, int $customerId): void {
        $query->where(static function (Builder $outer) use ($organizationId, $customerId): void {
            $outer->where('customer_id', $customerId)
                ->orWhereIn('project_id', Project::query()
                    ->where('organization_id', $organizationId)
                    ->where('customer_id', $customerId)
                    ->select('id'))
                ->orWhereIn('diary_entry_id', DiaryEntry::query()
                    ->where('organization_id', $organizationId)
                    ->where('customer_id', $customerId)
                    ->select('id'));
        });
    }

    /** Anzeigename eines Kettenglieds; leer, wenn der Träger keinen führt. */
    public function label(Model $model): string {
        $value = $model instanceof DiaryEntry
            ? $model->title
            : ($model->getAttribute('name') ?? $model->getAttribute('title'));

        return trim((string) $value);
    }

    /**
     * Was eine Liste mitladen muss, damit die Bezugsspalte nicht in N+1 läuft.
     * Rückgabe passt direkt in `with()`.
     *
     * @param  class-string<Model>  $class
     * @return array<string, Closure>|list<string>
     */
    public function eagerLoad(string $class): array {
        $morph = static fn (string $relation): array => [$relation => static function (MorphTo $morphTo): void {
            $morphTo->morphWith([
                Project::class => ['customer', 'foreignCustomer'],
                DiaryEntry::class => ['customer', 'project'],
                Asset::class => ['customer', 'foreignCustomer'],
                DisposalJob::class => ['customer'],
            ]);
        }];

        return match ($class) {
            Document::class => $morph('documentable'),
            FormSubmission::class => $morph('subject'),
            CommunicationNote::class => $morph('notable'),
            IdeaMap::class => ['customer', 'project', 'diaryEntry.customer', 'diaryEntry.project'],
            DiaryEntry::class => ['customer', 'project'],
            Project::class => ['customer', 'foreignCustomer'],
            Asset::class => ['customer', 'foreignCustomer'],
            default => [],
        };
    }
}
