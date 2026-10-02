<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Takeoff;

use App\Enums\Takeoff\{TakeoffFormula, TakeoffStatus};
use App\Models\Article\Article;
use App\Models\Gaeb\BoqItem;
use App\Models\Platform\User;
use App\Models\Takeoff\{Takeoff, TakeoffLine};
use App\Services\Concerns\AssertsStatusTransition;
use CommonToolkit\Helper\Data\NumberHelper;
use ERechnungToolkit\Entities\Gaeb\GaebTakeoffLine;
use ERechnungToolkit\Helper\Gaeb\GaebTakeoffCalculator;
use RuntimeException;

/**
 * Aufmaßblatt (MVP-1058). Die einzige Rechenstelle ist der
 * {@see GaebTakeoffCalculator} des erechnung-toolkit: Eingaben werden in die
 * REB-Darstellung übersetzt (Werte und Faktor in Tausendsteln, freie Formel als
 * Ausdruck) und dort ausgewertet — so stimmen Blatt und X31/DA11-Export überein.
 */
final class TakeoffService {
    use AssertsStatusTransition;

    private const SCALE = 1000;

    public function __construct(private readonly GaebTakeoffCalculator $calculator) {}

    /**
     * Menge einer Zeile inklusive Faktor; `null`, wenn die Werte nicht reichen
     * oder der Ausdruck nicht auswertbar ist.
     *
     * @param  list<string>  $values  Dezimaltexte (Komma oder Punkt), bei der freien Formel der Ausdruck
     * @return numeric-string|null
     */
    public function quantityOf(TakeoffFormula $formula, array $values, string $factor = '1'): ?string {
        $line = $this->gaebLine($formula, $values, $factor, null);
        if ($line === null) {
            return null;
        }
        $result = $this->calculator->line($line);
        $quantity = $result === null ? null : NumberHelper::toUSFormat($result, 4);

        return is_numeric($quantity) ? $quantity : null;
    }

    /**
     * @param  array{formula: TakeoffFormula|string, values: list<string|null>, factor?: string|null, label?: string|null, description?: string|null, unit?: string|null, boq_item_id?: int|null, article_id?: int|null, position?: int|null}  $data
     */
    public function saveLine(Takeoff $takeoff, array $data, ?TakeoffLine $line = null): TakeoffLine {
        if (! $takeoff->isEditable()) {
            throw new RuntimeException((string) __('takeoff.error.locked'));
        }
        $formula = $data['formula'] instanceof TakeoffFormula ? $data['formula'] : TakeoffFormula::from((string) $data['formula']);
        $values = self::cleanValues($formula, $data['values']);
        $factor = trim((string) ($data['factor'] ?? '')) === '' ? '1' : self::decimal($data['factor']);
        $quantity = $values === null || $factor === null ? null : $this->quantityOf($formula, $values, $factor);
        if ($values === null || $factor === null || $quantity === null) {
            throw new RuntimeException((string) __('takeoff.error.not_computable'));
        }

        $attributes = [
            'formula' => $formula->value,
            'values' => $values,
            'factor' => $factor,
            'quantity' => $quantity,
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
            'unit' => $data['unit'] ?? null,
            'boq_item_id' => $data['boq_item_id'] ?? null,
            'article_id' => $data['article_id'] ?? null,
        ];
        if ($line === null) {
            return $takeoff->lines()->create([
                ...$attributes,
                'organization_id' => $takeoff->organization_id,
                'position' => $data['position'] ?? ((int) $takeoff->lines()->max('position') + 1),
            ]);
        }
        $line->update([...$attributes, 'position' => $data['position'] ?? $line->position]);

        return $line;
    }

    /**
     * Geprüfte Eingabe (Formular oder Offline-Befehl) in die Form von {@see saveLine()}.
     *
     * @param  array<string, mixed>  $input
     * @return array{formula: string, values: list<string|null>, factor: string|null, label: string|null, description: string|null, unit: string|null, boq_item_id: int|null, article_id: int|null, position: int|null}
     */
    public static function lineInput(array $input): array {
        $text = static fn (string $key): ?string => isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : null;
        $id = static fn (string $key): ?int => isset($input[$key]) && is_numeric($input[$key]) ? (int) $input[$key] : null;

        return [
            'formula' => (string) $text('formula'),
            'values' => array_values(array_map(static fn ($v): ?string => is_scalar($v) ? (string) $v : null, (array) ($input['values'] ?? []))),
            'factor' => $text('factor'),
            'label' => $text('label'),
            'description' => $text('description'),
            'unit' => $text('unit'),
            'boq_item_id' => $id('boq_item_id'),
            'article_id' => $id('article_id'),
            'position' => $id('position'),
        ];
    }

    public function transition(Takeoff $takeoff, TakeoffStatus $to, User $actor): Takeoff {
        $this->assertStatusTransition($takeoff->status, $to);
        $takeoff->update(['status' => $to->value, 'updated_by' => $actor->id]);
        $takeoff->audit('takeoff.' . ($to === TakeoffStatus::Completed ? 'completed' : 'reopened'));

        return $takeoff;
    }

    /**
     * Mengen je Ziel: LV-Position, Artikel oder — ohne Ziel — Beschreibung und Einheit.
     *
     * @return list<array{key: string, label: string, unit: ?string, quantity: string, boqItem: ?BoqItem, article: ?Article}>
     */
    public function totals(Takeoff $takeoff): array {
        $takeoff->loadMissing(['lines.boqItem', 'lines.article']);
        $groups = [];
        foreach ($takeoff->lines as $line) {
            if ($line->quantity === null) {
                continue;
            }
            $key = match (true) {
                $line->boq_item_id !== null => 'boq:' . $line->boq_item_id,
                $line->article_id !== null => 'article:' . $line->article_id,
                default => 'text:' . mb_strtolower(trim((string) ($line->description ?? $line->label))) . '|' . (string) $line->unit,
            };
            $groups[$key] ??= [
                'key' => $key,
                'label' => $line->boqItem !== null
                    ? trim($line->boqItem->reference_no . ' ' . (string) $line->boqItem->short_text)
                    : ($line->article->name ?? (string) ($line->description ?? $line->label ?? '')),
                'unit' => $line->unit ?? $line->boqItem->unit ?? $line->article->base_unit ?? null,
                'quantity' => 0.0,
                'boqItem' => $line->boqItem,
                'article' => $line->article,
            ];
            $groups[$key]['quantity'] += (float) $line->quantity;
        }

        return array_values(array_map(static fn (array $g): array => [...$g, 'quantity' => NumberHelper::toUSFormat($g['quantity'], 4)], $groups));
    }

    /**
     * Aufmaßzeilen einer LV-Position aus abgeschlossenen Blättern für den GAEB-Export (X31/DA11).
     *
     * @return list<GaebTakeoffLine>
     */
    public function gaebLinesFor(BoqItem $item): array {
        $lines = TakeoffLine::query()
            ->where('boq_item_id', $item->id)
            ->whereHas('takeoff', fn ($q) => $q->where('status', TakeoffStatus::Completed->value))
            ->orderBy('takeoff_id')->orderBy('position')
            ->get();
        $gaeb = [];
        foreach ($lines as $line) {
            $converted = $this->gaebLine($line->formula, (array) $line->values, (string) $line->factor, $line->label);
            if ($converted !== null) {
                $gaeb[] = $converted;
            }
        }

        return $gaeb;
    }

    /** @param  array<int, string|null>  $values */
    private function gaebLine(TakeoffFormula $formula, array $values, string $factor, ?string $explanation): ?GaebTakeoffLine {
        $values = self::cleanValues($formula, $values);
        $factor = self::decimal($factor);
        if ($values === null || $factor === null || count($values) < $formula->requiredValues()) {
            return null;
        }
        $raw = $formula->isExpression()
            ? [str_replace(' ', '', $values[0]) . '=']
            : array_map(static fn (string $v): string => self::raw($v), $values);

        return new GaebTakeoffLine(
            kind: ' ',
            explanation: $explanation,
            factor: self::raw($factor),
            formula: $formula->value,
            values: $raw,
            closesResult: true,
        );
    }

    /**
     * Leere Werte entfallen — ein leeres Höhenfeld darf eine Fläche nicht zum
     * Körper mit Höhe 0 machen. Ein Wert, der keine Zahl ist, macht die Zeile
     * unrechenbar (`null`), statt still als 0 zu zählen.
     *
     * @param  array<int, string|null>  $values
     * @return list<string>|null
     */
    private static function cleanValues(TakeoffFormula $formula, array $values): ?array {
        $clean = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if ($formula->isExpression()) {
                $clean[] = $value;

                continue;
            }
            $decimal = self::decimal($value);
            if ($decimal === null) {
                return null;
            }
            $clean[] = $decimal;
        }

        return $formula->isExpression() ? array_slice($clean, 0, 1) : $clean;
    }

    /** @return numeric-string|null */
    private static function decimal(?string $value): ?string {
        return $value === null ? null : NumberHelper::normalizeDecimalStringOrNull($value);
    }

    /** REB-Darstellung: ganze Tausendstel mit vorangestelltem Vorzeichen. */
    private static function raw(string $decimal): string {
        $value = (int) round((float) $decimal * self::SCALE);

        return ($value < 0 ? '-' : '') . (string) abs($value);
    }
}
