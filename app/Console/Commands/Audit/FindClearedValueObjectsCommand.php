<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FindClearedValueObjectsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Audit;

use App\Models\{Article, AuditLog, Customer, Expense, ExpenseCategory, Material, User};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Nur lesend: findet Wertobjekt-Felder, die ein Update seit dem Cast-Umbau
 * (2026-07-27) von „gesetzt" auf leer gebracht hat. Bis zum Fix vom
 * 2026-09-19 schrieben die Formulare „85.00 EUR" in type=number-Felder, der
 * Browser verwarf den Wert und jedes Speichern leerte optionale Sätze.
 *
 * Absichtliches Leeren sieht im Audit-Log genauso aus — die Liste ist ein
 * Prüfauftrag, keine Reparatur. Aufgaben und Tagessätze führen kein
 * Audit-Log; dort hilft nur ein Abgleich mit einer Sicherung.
 */
class FindClearedValueObjectsCommand extends Command {
    protected $signature = 'audit:cleared-values
        {--since=2026-07-27 : Ab diesem Tag suchen (Y-m-d)}
        {--all : Auch Felder zeigen, die inzwischen wieder befüllt sind}';

    protected $description = 'Listet Beträge/Sätze, die ein Update seit dem Wertobjekt-Umbau geleert hat (nur lesend).';

    /** @var array<class-string<Model>, list<string>> Felder, die die Formulare still leeren konnten. */
    private const FIELDS = [
        Customer::class => ['hourly_rate', 'internal_rate'],
        Article::class => ['default_purchase_price', 'default_sale_price'],
        Material::class => ['default_unit_price', 'tax_rate'],
        User::class => ['payroll_hourly_wage', 'flat_amount', 'compensation_rate'],
        ExpenseCategory::class => ['default_tax_rate'],
        Expense::class => ['amount_net', 'tax_rate'],
    ];

    public function handle(): int {
        $since = CarbonImmutable::parse((string) $this->option('since'))->startOfDay();
        $showAll = (bool) $this->option('all');

        $classes = [];
        foreach (array_keys(self::FIELDS) as $class) {
            $classes[(new $class())->getMorphClass()] = $class;
        }

        $rows = [];
        $restored = 0;
        AuditLog::query()
            ->withoutGlobalScopes()
            ->whereIn('auditable_type', array_keys($classes))
            ->whereIn('event', ['updated', 'archived', 'restored'])
            ->where('created_at', '>=', $since->utc())
            ->with('user:id,name')
            ->lazyById(500)
            ->each(function (AuditLog $log) use ($classes, $showAll, &$rows, &$restored): void {
                $class = $classes[$log->auditable_type] ?? null;
                $changes = (array) ($log->changes ?? []);
                $before = (array) ($changes['before'] ?? []);
                $after = (array) ($changes['after'] ?? []);
                if ($class === null) {
                    return;
                }

                foreach (self::FIELDS[$class] as $field) {
                    if (! array_key_exists($field, $after) || ! self::isEmpty($after[$field]) || self::isEmpty($before[$field] ?? null)) {
                        continue;
                    }

                    $model = $class::query()->withoutGlobalScopes()->find($log->auditable_id);
                    $current = $model?->getRawOriginal($field);
                    if (! self::isEmpty($current)) {
                        $restored++;
                        if (! $showAll) {
                            continue;
                        }
                    }

                    $rows[] = [
                        $log->created_at?->timezone((string) config('app.display_timezone'))->format('d.m.Y H:i'),
                        (string) ($log->organization_id ?? '—'),
                        class_basename($class),
                        self::label($model, (int) $log->auditable_id),
                        $field,
                        self::format($before[$field]),
                        self::isEmpty($current) ? '— (leer)' : (string) $current,
                        $log->user->name ?? '—',
                    ];
                }
            });

        $this->line('Geleerte Wertobjekt-Felder seit ' . $since->toDateString() . ' (nur Audit-pflichtige Modelle; Aufgaben/Tagessätze ohne Audit-Log):');
        if ($rows === []) {
            $this->info('Keine Treffer.');
        } else {
            $this->table(['Zeitpunkt', 'Org', 'Modell', 'Datensatz', 'Feld', 'Vorher', 'Jetzt', 'Geändert von'], $rows);
        }
        if ($restored > 0 && ! $showAll) {
            $this->line("{$restored} weitere Felder sind inzwischen wieder befüllt (--all zeigt sie).");
        }

        return self::SUCCESS;
    }

    private static function isEmpty(mixed $value): bool {
        return $value === null || $value === '' || $value === [];
    }

    /** Vorher-Wert lesbar: Money-JSON {amount,currency}, Percentage {value,scale} oder Rohstring. */
    private static function format(mixed $value): string {
        if (is_array($value)) {
            if (isset($value['amount'])) {
                return $value['amount'] . ' ' . ($value['currency'] ?? '');
            }
            if (isset($value['value'])) {
                return (string) $value['value'];
            }

            return JsonHelper::encode($value);
        }

        return $value === AuditLog::REDACTED ? '[geschwärzt]' : (string) $value;
    }

    private static function label(?Model $model, int $id): string {
        if ($model === null) {
            return "#{$id} (gelöscht)";
        }
        foreach (['name', 'label', 'number', 'description', 'vendor'] as $attribute) {
            $value = $model->getAttribute($attribute);
            if (is_string($value) && $value !== '') {
                return "#{$id} " . mb_strimwidth($value, 0, 40, '…');
            }
        }

        return "#{$id}";
    }
}
