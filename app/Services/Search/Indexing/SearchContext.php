<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing;

use App\Models\{Asset, DiaryEntry};
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Platform\User;
use App\Models\Project\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Kontext-Auflösung beim Indizieren: Projekt → Endkunde → Partner (Kunde),
 * dazu Assets, Aufträge und Personennamen. Scoped gebunden und memoisiert —
 * ein Neuaufbau über zehntausende Zeiteinträge fragt jedes Projekt einmal ab.
 * Jede Auflösung prüft die Organisation, ein fremder Bezug bleibt leer.
 */
final class SearchContext {
    private const MEMO_LIMIT = 5000;

    /** @var array<string, Model|false> */
    private array $memo = [];

    /** @var array<int, string|false> */
    private array $userNames = [];

    public function resolve(int $organizationId, ?int $projectId = null, ?int $foreignCustomerId = null, ?int $customerId = null): SearchContextRef {
        $project = $this->find(Project::class, $organizationId, $projectId, ['id', 'organization_id', 'name', 'number', 'description', 'keywords', 'customer_id', 'foreign_customer_id']);
        $foreign = $this->find(ForeignCustomer::class, $organizationId, $project->foreign_customer_id ?? $foreignCustomerId, ['id', 'organization_id', 'customer_id', 'name', 'number', 'company', 'matchcode', 'contact_name']);
        $customer = $this->find(Customer::class, $organizationId, $customerId ?? $project->customer_id ?? $foreign?->customer_id, ['id', 'organization_id', 'name', 'number', 'company']);

        $texts = [];
        if ($project !== null) {
            // `keywords` ist ein Array-Cast (Schlüsselwort-Routing) — einzeln aufnehmen.
            array_push($texts, $project->name, $project->number, $project->description, ...($project->keywords ?? []));
        }
        if ($foreign !== null) {
            array_push($texts, $foreign->name, $foreign->number, $foreign->company, $foreign->matchcode, $foreign->contact_name);
        }
        if ($customer !== null) {
            array_push($texts, $customer->name, $customer->number, $customer->company);
        }

        return new SearchContextRef(
            $customer !== null ? (int) $customer->id : null,
            $foreign !== null ? (int) $foreign->id : null,
            $project !== null ? (int) $project->id : null,
            $texts,
        );
    }

    /** Kontext eines polymorphen Bezugs (Protokoll-Subjekt, Notiz-Bezug, offener Punkt). */
    public function forSubject(int $organizationId, ?string $type, ?int $id): SearchContextRef {
        if ($type === null || $id === null) {
            return new SearchContextRef;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if ($class === DiaryEntry::class) {
            $entry = $this->find(DiaryEntry::class, $organizationId, $id, ['id', 'organization_id', 'title', 'project_id', 'customer_id']);
            $ref = $this->resolve($organizationId, $entry?->project_id, null, $entry?->customer_id);

            return new SearchContextRef($ref->customerId, $ref->foreignCustomerId, $ref->projectId, [$entry?->title, ...$ref->texts]);
        }
        if ($class === Asset::class) {
            $asset = $this->asset($organizationId, $id);
            $ref = $this->resolve($organizationId, null, $asset?->foreign_customer_id, $asset?->customer_id);

            return new SearchContextRef($ref->customerId, $ref->foreignCustomerId, $ref->projectId, [$asset?->name, $asset?->asset_no, ...$ref->texts]);
        }

        return match ($class) {
            Project::class => $this->resolve($organizationId, $id),
            ForeignCustomer::class => $this->resolve($organizationId, null, $id),
            Customer::class => $this->resolve($organizationId, null, null, $id),
            default => new SearchContextRef,
        };
    }

    public function asset(int $organizationId, ?int $id): ?Asset {
        return $this->find(Asset::class, $organizationId, $id, ['id', 'organization_id', 'name', 'asset_no', 'customer_id', 'foreign_customer_id']);
    }

    public function userName(?int $id): ?string {
        if ($id === null) {
            return null;
        }
        if (! array_key_exists($id, $this->userNames)) {
            $this->userNames[$id] = (string) (User::query()->withoutGlobalScopes()->whereKey($id)->value('name') ?? '') ?: false;
        }

        return $this->userNames[$id] === false ? null : $this->userNames[$id];
    }

    public function flush(): void {
        $this->memo = [];
        $this->userNames = [];
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @param  list<string>  $columns
     * @return T|null
     */
    private function find(string $class, int $organizationId, ?int $id, array $columns): ?Model {
        if ($id === null) {
            return null;
        }

        $key = $class . ':' . $id;
        if (! array_key_exists($key, $this->memo)) {
            if (count($this->memo) >= self::MEMO_LIMIT) {
                $this->memo = [];
            }
            $this->memo[$key] = $class::query()->withoutGlobalScopes()->whereKey($id)->first($columns) ?? false;
        }

        $model = $this->memo[$key];

        return $model instanceof $class && (int) $model->getAttribute('organization_id') === $organizationId ? $model : null;
    }
}
