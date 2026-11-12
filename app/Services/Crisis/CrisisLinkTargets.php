<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisLinkTargets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Crisis;

use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Isms\IsmsSecurityIncident;
use App\Models\Privacy\Incident;
use App\Models\Procedure\ProcedureRun;
use App\Models\Safety\SafetyEvent;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Navigation\NavGate;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Vorgänge, die sich mit einer Krisenakte verknüpfen lassen (MVP-218), und
 * Vorfälle, aus denen eine Krise eröffnet wird (MVP-1605). Das Fachmodul
 * bleibt führend; angeboten wird nur, was sein Modul und sein Leserecht zulassen.
 */
final class CrisisLinkTargets {
    /**
     * `route` prüft Modul und Listenrecht, `label` nennt die Anzeigespalten,
     * `category` die Krisenkategorie beim Eröffnen aus dem Vorfall.
     *
     * @var array<string, array{class: class-string<Model>, route: string, label: list<string>, category?: string}>
     */
    private const array TYPES = [
        'service_ticket' => ['class' => ServiceTicket::class, 'route' => 'service-tickets.index', 'label' => ['ticket_no', 'title'], 'category' => 'it_outage'],
        'isms_incident' => ['class' => IsmsSecurityIncident::class, 'route' => 'isms.incidents.index', 'label' => ['incident_no', 'title'], 'category' => 'security'],
        'privacy_incident' => ['class' => Incident::class, 'route' => 'dataprotection.incidents.index', 'label' => ['incident_number'], 'category' => 'privacy'],
        'safety_event' => ['class' => SafetyEvent::class, 'route' => 'safety-events.index', 'label' => ['event_no', 'location'], 'category' => 'safety'],
        'procedure_run' => ['class' => ProcedureRun::class, 'route' => 'procedures.index', 'label' => []],
        'document' => ['class' => Document::class, 'route' => 'documents.index', 'label' => ['title']],
        'asset' => ['class' => Asset::class, 'route' => 'assets.index', 'label' => ['asset_no', 'name']],
        'customer' => ['class' => Customer::class, 'route' => 'customers.index', 'label' => ['name']],
    ];

    /** Je Art höchstens so viele jüngste Vorgänge in der Auswahl. */
    private const int OPTION_LIMIT = 100;

    public function __construct(private readonly NavGate $nav) {}

    /** @return list<string> */
    public static function keys(): array {
        return array_keys(self::TYPES);
    }

    /** Arten, aus denen eine Krise eröffnet wird. */
    public static function isIncident(string $type): bool {
        return isset(self::TYPES[$type]['category']);
    }

    public function category(string $type): string {
        return self::TYPES[$type]['category'] ?? 'other';
    }

    public function typeLabel(string $type): string {
        return (string) __('crisis.link.type.' . $type);
    }

    public function available(string $type): bool {
        $config = self::TYPES[$type] ?? null;

        return $config !== null
            && $this->nav->allows($config['route'])
            && (Gate::getPolicyFor($config['class']) === null || Gate::allows('viewAny', $config['class']));
    }

    /** Vorgang der eigenen Organisation, den der Nutzer lesen darf — sonst null. */
    public function resolve(string $type, ?string $sqid): ?Model {
        if (! $this->available($type) || $sqid === null || $sqid === '') {
            return null;
        }
        $class = self::TYPES[$type]['class'];
        $id = Sqid::decodeOrNumeric($class, $sqid);
        $target = $id !== null ? $class::query()->whereKey($id)->first() : null;

        if ($target === null || (Gate::getPolicyFor($target) !== null && ! Gate::allows('view', $target))) {
            return null;
        }

        return $target;
    }

    public function label(string $type, Model $target): string {
        $parts = array_filter(array_map(
            static fn (string $column): string => trim((string) $target->getAttribute($column)),
            self::TYPES[$type]['label'] ?? [],
        ));

        return $parts !== [] ? implode(' · ', $parts) : '#' . $target->getKey();
    }

    /**
     * Auswahl je Art für den Verknüpfungsdialog.
     *
     * @return array<string, array{label: string, options: list<array{value: string, label: string}>}>
     */
    public function options(): array {
        $groups = [];
        foreach (self::TYPES as $type => $config) {
            if (! $this->available($type)) {
                continue;
            }
            $options = array_values($config['class']::query()->latest('id')->limit(self::OPTION_LIMIT)->get()
                ->map(fn (Model $target): array => [
                    'value' => $type . ':' . Sqid::encode($config['class'], (int) $target->getKey()),
                    'label' => $this->label($type, $target),
                ])
                ->all());
            if ($options !== []) {
                $groups[$type] = ['label' => $this->typeLabel($type), 'options' => $options];
            }
        }

        return $groups;
    }
}
