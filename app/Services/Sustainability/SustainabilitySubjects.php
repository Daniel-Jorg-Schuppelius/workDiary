<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilitySubjects.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability;

use App\Models\Article\Article;
use App\Models\Asset\Asset;
use App\Models\Contract\Contract;
use App\Models\Diary\DiaryEntry;
use App\Models\Facility\{Room, Site};
use App\Models\Fleet\Vehicle;
use App\Models\Investments\InvestmentCase;
use App\Models\Project\Project;
use App\Models\Supplier\Supplier;
use App\Services\Navigation\NavGate;
use App\Support\{EntityUrl, MorphMap, Sqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Bewertbare Objekte (Feature 071, MVP-1607): Art und Objekt einer
 * Nachhaltigkeitsbewertung. Angeboten wird nur, was sein Modul und sein
 * Leserecht zulassen; gespeichert wird der Morph-Alias.
 */
final class SustainabilitySubjects {
    /**
     * `route` prüft Modul und Listenrecht, `show` ist die Detailseite, wenn
     * {@see EntityUrl} die Art nicht kennt.
     *
     * @var array<string, array{class: class-string<Model>, route: string, label: list<string>, show?: string}>
     */
    private const array TYPES = [
        'asset' => ['class' => Asset::class, 'route' => 'assets.index', 'label' => ['asset_no', 'name']],
        'vehicle' => ['class' => Vehicle::class, 'route' => 'vehicles.index', 'label' => ['license_plate', 'name']],
        'site' => ['class' => Site::class, 'route' => 'sites.index', 'label' => ['name'], 'show' => 'sites.show'],
        'room' => ['class' => Room::class, 'route' => 'rooms.index', 'label' => ['name']],
        'project' => ['class' => Project::class, 'route' => 'projects.index', 'label' => ['name']],
        'order' => ['class' => DiaryEntry::class, 'route' => 'diary.index', 'label' => ['title']],
        'supplier' => ['class' => Supplier::class, 'route' => 'suppliers.index', 'label' => ['name'], 'show' => 'suppliers.show'],
        'article' => ['class' => Article::class, 'route' => 'articles.index', 'label' => ['number', 'name'], 'show' => 'articles.show'],
        'investment' => ['class' => InvestmentCase::class, 'route' => 'investments.index', 'label' => ['title'], 'show' => 'investments.show'],
        'contract' => ['class' => Contract::class, 'route' => 'contracts.index', 'label' => ['number', 'title'], 'show' => 'contracts.show'],
    ];

    /** Je Art höchstens so viele jüngste Objekte in der Auswahl. */
    private const int OPTION_LIMIT = 100;

    public function __construct(private readonly NavGate $nav) {}

    /** @return list<string> */
    public static function keys(): array {
        return array_keys(self::TYPES);
    }

    public function typeLabel(string $type): string {
        return (string) __('sustainability.subject.type.' . $type);
    }

    public function available(string $type): bool {
        $config = self::TYPES[$type] ?? null;

        return $config !== null
            && $this->nav->allows($config['route'])
            && (Gate::getPolicyFor($config['class']) === null || Gate::allows('viewAny', $config['class']));
    }

    /** Wert der Auswahl (`art:sqid`) → Objekt der eigenen Organisation, das der Nutzer lesen darf. */
    public function parse(?string $value): ?Model {
        [$type, $sqid] = array_pad(explode(':', (string) $value, 2), 2, null);
        if (! is_string($type) || ! is_string($sqid) || $sqid === '' || ! $this->available($type)) {
            return null;
        }
        $class = self::TYPES[$type]['class'];
        $id = Sqid::decodeOrNumeric($class, $sqid);
        $subject = $id !== null ? $class::query()->whereKey($id)->first() : null;

        return $subject !== null && $this->mayView($subject) ? $subject : null;
    }

    /** Auswahlwert eines Objekts, etwa für den Einstieg von seiner Akte. */
    public function value(Model $subject): ?string {
        $type = $this->typeOf($subject::class);

        return $type === null ? null : $type . ':' . Sqid::encode($subject::class, (int) $subject->getKey());
    }

    public function label(Model $subject): string {
        $type = $this->typeOf($subject::class);
        $parts = array_filter(array_map(
            static fn (string $column): string => trim((string) $subject->getAttribute($column)),
            $type === null ? [] : self::TYPES[$type]['label'],
        ));

        return $parts !== [] ? implode(' · ', $parts) : '#' . $subject->getKey();
    }

    /** Art eines gespeicherten Morph-Typs, null für Fremdes (etwa Altbestand). */
    public function typeOfStored(?string $morphType): ?string {
        $class = $morphType !== null ? MorphMap::classFor($morphType) : null;

        return $class !== null ? $this->typeOf($class) : null;
    }

    /** Detailseite des Objekts, wenn der Nutzer es lesen darf. */
    public function url(?Model $subject): ?string {
        if ($subject === null || ! $this->mayView($subject)) {
            return null;
        }
        $type = $this->typeOf($subject::class);
        if ($type === null || ! $this->nav->allows(self::TYPES[$type]['route'])) {
            return null;
        }
        $show = self::TYPES[$type]['show'] ?? null;

        try {
            return EntityUrl::for($subject) ?? ($show !== null ? route($show, $subject) : null);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Auswahl je Art für den Bewertungsdialog.
     *
     * @return array<string, array{label: string, options: list<array{value: string, label: string}>}>
     */
    public function options(?Model $include = null): array {
        $groups = [];
        foreach (self::TYPES as $type => $config) {
            if (! $this->available($type)) {
                continue;
            }
            $subjects = $config['class']::query()->latest('id')->limit(self::OPTION_LIMIT)->get()->all();
            // Das vorbelegte Objekt bleibt wählbar, auch wenn es nicht unter den jüngsten ist.
            if ($include instanceof $config['class'] && ! in_array(true, array_map(static fn (Model $s): bool => $s->is($include), $subjects), true)) {
                array_unshift($subjects, $include);
            }
            $options = array_values(array_map(fn (Model $subject): array => [
                'value' => (string) $this->value($subject),
                'label' => $this->label($subject),
            ], $subjects));
            if ($options !== []) {
                $groups[$type] = ['label' => $this->typeLabel($type), 'options' => $options];
            }
        }

        return $groups;
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

    private function mayView(Model $subject): bool {
        return Gate::getPolicyFor($subject) === null || Gate::allows('view', $subject);
    }
}
