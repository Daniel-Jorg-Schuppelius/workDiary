<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedactAuditLog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit\AuditRedactionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Schwärzt einzelne Werte im Audit-Protokoll auf ein Löschverlangen nach
 * Art. 17 DSGVO hin (Sicherheitsscan 2026-08-23, S-21).
 *
 * Bewusst ein Konsolenkommando und keine Schaltfläche: der Eingriff bricht das
 * Grundversprechen des Protokolls und soll die Hürde einer bewussten,
 * begründeten Handlung haben. Ohne `--reason` läuft er nicht.
 *
 * Beispiel:
 *   php artisan audit:redact --type=customer --id=42 \
 *     --fields=bank_iban,bank_bic --reason="Löschverlangen Art. 17" \
 *     --request=DSR-2026-0007
 */
class RedactAuditLog extends Command {
    protected $signature = 'audit:redact
        {--type= : Morph-Alias oder Klassenname des betroffenen Datensatzes}
        {--id= : ID des betroffenen Datensatzes}
        {--fields= : Kommaliste der zu schwärzenden Felder}
        {--chain=audit_logs : Kette, in der geschwärzt wird}
        {--reason= : Begründung (Pflicht, geht in den Nachweis ein)}
        {--request= : Aktenzeichen des Betroffenenverlangens}
        {--actor= : E-Mail der handelnden Person}
        {--dry-run : Nur zeigen, was geschwärzt würde}';

    protected $description = 'Schwärzt Werte im Audit-Protokoll (Art. 17 DSGVO) und rechnet die Hash-Kette nach.';

    public function handle(AuditRedactionService $service): int {
        $type = (string) $this->option('type');
        $id = (int) $this->option('id');
        $fields = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('fields')))));
        $reason = (string) $this->option('reason');

        if ($type === '' || $id <= 0 || $fields === [] || trim($reason) === '') {
            $this->error('--type, --id, --fields und --reason sind Pflicht.');

            return self::INVALID;
        }

        // Im Protokoll steht, was getMorphClass() liefert — je nach Morph-Map
        // der Alias oder der Klassenname. Beides als --type zulassen.
        $morph = $type;
        if (Relation::getMorphedModel($type) === null && class_exists($type)) {
            $model = new $type();
            $morph = $model instanceof \Illuminate\Database\Eloquent\Model ? $model->getMorphClass() : $type;
        }

        $actor = null;
        if (is_string($this->option('actor')) && $this->option('actor') !== '') {
            $actor = User::query()->where('email', $this->option('actor'))->first();
            if (! $actor instanceof User) {
                $this->error('Handelnde Person nicht gefunden: ' . $this->option('actor'));

                return self::INVALID;
            }
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $service->redact(
                chainTable: (string) $this->option('chain'),
                auditableType: $morph,
                auditableId: $id,
                fields: $fields,
                reason: $reason,
                requestReference: is_string($this->option('request')) && $this->option('request') !== '' ? (string) $this->option('request') : null,
                actor: $actor,
                dryRun: $dryRun,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        if ($result['rows'] === 0) {
            $this->info('Keine Protokollzeile enthält diese Felder — nichts zu tun.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("Probelauf: {$result['rows']} Zeile(n) in " . implode(', ', $result['chains']) . ' würden geschwärzt.');

            return self::SUCCESS;
        }

        $this->info("{$result['rows']} Zeile(n) geschwärzt, Ketten neu gerechnet: " . implode(', ', $result['chains']) . '.');
        $this->line('Nachweis in audit_redactions: #' . implode(', #', $result['redactions']));
        $this->line('Zur Kontrolle: php artisan audit:verify');

        return self::SUCCESS;
    }
}
