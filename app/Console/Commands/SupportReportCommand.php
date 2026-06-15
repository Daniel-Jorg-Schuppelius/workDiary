<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Support\SupportReportBuilder;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Console\Command;
use Throwable;

/**
 * Erzeugt den anonymisierten Supportbericht (Feature 041) auf der
 * Kommandozeile — gedacht für On-Premise-Installationen und CI, wo kein
 * Browser-Zugang zur Admin-Seite besteht.
 *
 * Der Bericht enthält ausschließlich technische, NICHT-personenbezogene
 * Informationen (Versionen, Health-Status, Counts, Konfigurations-Flags) —
 * keine Kundendaten, keine Secrets. Die Whitelist liegt vollständig im
 * {@see SupportReportBuilder}.
 */
class SupportReportCommand extends Command {
    protected $signature = 'support:report {--output= : Pfad für die JSON-Ausgabedatei (Standard: STDOUT)}';

    protected $description = 'Erzeugt den anonymisierten Supportbericht (Versionen, Health, Counts) als JSON.';

    public function handle(SupportReportBuilder $builder): int {
        try {
            $bundle = $builder->build();
        } catch (Throwable $e) {
            $this->error('Supportbericht konnte nicht erzeugt werden: ' . $e->getMessage());

            return self::FAILURE;
        }

        $json = JsonHelper::encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $output = $this->option('output');

        if (! is_string($output) || $output === '') {
            $this->line($json);

            return self::SUCCESS;
        }

        if (@file_put_contents($output, $json) === false) {
            $this->error(sprintf('Konnte Datei nicht schreiben: %s', $output));

            return self::FAILURE;
        }

        $this->info(sprintf('Supportbericht geschrieben: %s (%d Bytes)', $output, strlen($json)));

        return self::SUCCESS;
    }
}
