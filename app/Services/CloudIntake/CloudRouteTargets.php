<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudRouteTargets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CloudIntake;

use App\Models\Asset\Asset;
use App\Models\Contract\Contract;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Project\Project;
use App\Services\Navigation\NavGate;
use App\Support\{MorphMap, Sqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Feste Zielreferenz einer Ordnerregel (Feature 080, MVP-1613): dieselben
 * Objektarten wie die Pfadvariablen. Angeboten wird nur, was das Modul und
 * das Leserecht zulassen; gespeichert wird der Morph-Alias.
 */
final class CloudRouteTargets {
    /** @var array<string, array{class: class-string<Model>, route: string, label: list<string>}> */
    private const array TYPES = [
        'customer' => ['class' => Customer::class, 'route' => 'customers.index', 'label' => ['number', 'name']],
        'project' => ['class' => Project::class, 'route' => 'projects.index', 'label' => ['number', 'name']],
        'order' => ['class' => DiaryEntry::class, 'route' => 'diary.index', 'label' => ['title']],
        'asset' => ['class' => Asset::class, 'route' => 'assets.index', 'label' => ['asset_no', 'name']],
        'contract' => ['class' => Contract::class, 'route' => 'contracts.index', 'label' => ['number', 'title']],
    ];

    /** Je Art höchstens so viele jüngste Objekte in der Auswahl. */
    private const int OPTION_LIMIT = 200;

    public function __construct(private readonly NavGate $nav) {}

    public function available(string $type): bool {
        return isset(self::TYPES[$type]) && $this->nav->allows(self::TYPES[$type]['route']);
    }

    /** Auswahlwert `art:sqid` → Objekt der Organisation, sonst null. */
    public function parse(?string $value, int $organizationId): ?Model {
        [$type, $sqid] = array_pad(explode(':', (string) $value, 2), 2, null);
        if (! is_string($type) || ! is_string($sqid) || $sqid === '' || ! $this->available($type)) {
            return null;
        }
        $class = self::TYPES[$type]['class'];
        $id = Sqid::decodeOrNumeric($class, $sqid);
        $target = $id !== null ? $class::query()->whereKey($id)->first() : null;

        return $target !== null && (int) $target->getAttribute('organization_id') === $organizationId ? $target : null;
    }

    public function value(?Model $target): ?string {
        $type = $target !== null ? $this->typeOf($target::class) : null;
        if ($target === null || $type === null) {
            return null;
        }

        return $type . ':' . Sqid::encode($target::class, (int) $target->getKey());
    }

    /** Lesbare Bezeichnung mit Objektart, etwa für Vorschau und Regelliste. */
    public function describe(Model $target): string {
        $type = $this->typeOf($target::class);
        $parts = array_filter(array_map(
            static fn (string $column): string => trim((string) $target->getAttribute($column)),
            $type === null ? [] : self::TYPES[$type]['label'],
        ));

        return ($type !== null ? __('cloud_intake.target_ref.type.' . $type) . ' ' : '') . ($parts !== [] ? implode(' · ', $parts) : '#' . $target->getKey());
    }

    /**
     * Auswahl je Art für den Regeldialog; das gespeicherte Objekt bleibt wählbar.
     *
     * @return array<string, array{label: string, options: list<array{value: string, label: string}>}>
     */
    public function options(?Model $include = null): array {
        $groups = [];
        foreach (self::TYPES as $type => $config) {
            if (! $this->available($type)) {
                continue;
            }
            $targets = $config['class']::query()->latest('id')->limit(self::OPTION_LIMIT)->get()->all();
            if ($include instanceof $config['class'] && ! in_array(true, array_map(static fn (Model $t): bool => $t->is($include), $targets), true)) {
                array_unshift($targets, $include);
            }
            $options = array_map(fn (Model $target): array => ['value' => (string) $this->value($target), 'label' => $this->describe($target)], $targets);
            if ($options !== []) {
                $groups[$type] = ['label' => (string) __('cloud_intake.target_ref.type.' . $type), 'options' => array_values($options)];
            }
        }

        return $groups;
    }

    /** Gespeicherter Alias → Objekt, wenn die Art bekannt ist. */
    public function resolveStored(?string $morphType, ?int $id): ?Model {
        $class = $morphType !== null ? MorphMap::classFor($morphType) : null;
        if ($class === null || $id === null || $this->typeOf($class) === null) {
            return null;
        }

        return $class::query()->whereKey($id)->first();
    }

    /** @param class-string $class */
    private function typeOf(string $class): ?string {
        foreach (self::TYPES as $type => $config) {
            if ($config['class'] === $class) {
                return $type;
            }
        }

        return null;
    }
}
