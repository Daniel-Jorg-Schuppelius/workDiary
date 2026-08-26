<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureSqidsSaltCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Services\Install\InstallationManager;
use Illuminate\Console\Command;

/**
 * Stellt sicher, dass in der .env ein SQIDS_SALT gesetzt ist, und erzeugt
 * andernfalls einen kryptografisch sicheren Wert. Ohne Salt verweigert der
 * SqidEncoder in Produktion den Dienst (RuntimeException), sobald eine
 * Sqid-Route gebaut wird.
 *
 * Nützlich, um bestehende Installationen nachzuziehen, die vor Einführung der
 * automatischen Salt-Generierung im Installer eingerichtet wurden.
 *
 * Ein bereits gesetzter Salt wird NIEMALS überschrieben (außer mit --force),
 * da daran die öffentlich sichtbaren Route-Keys (Sqids) hängen.
 */
class EnsureSqidsSaltCommand extends Command {
    protected $signature = 'app:sqids-salt {--force : Vorhandenen Salt überschreiben (ändert ALLE Sqid-URLs!)}';

    protected $description = 'Stellt sicher, dass SQIDS_SALT in der .env gesetzt ist (erzeugt ihn bei Bedarf).';

    /**
     * {@see InstallationManager} kommt aus dem Container (wie in AdminCommand)
     * statt aus ::make() — sonst schreibt jeder Lauf zwingend in die echte
     * .env und der Befehl wäre nicht testbar.
     */
    public function handle(InstallationManager $installer): int {
        if ($installer->hasSqidsSalt() && ! $this->option('force')) {
            $this->info('SQIDS_SALT ist bereits gesetzt – keine Änderung.');

            return self::SUCCESS;
        }

        if ($installer->hasSqidsSalt() && $this->option('force')) {
            $this->warn('Achtung: --force überschreibt den vorhandenen Salt. Alle bereits');
            $this->warn('verteilten Sqid-URLs (Bookmarks, Links) werden danach ungültig.');
            if (! $this->confirm('Wirklich fortfahren?', false)) {
                return self::FAILURE;
            }

            $installer->regenerateSqidsSalt();
            $this->info('✓ Neuer SQIDS_SALT erzeugt und in .env geschrieben.');
        } else {
            $installer->ensureSqidsSalt();
            $this->info('✓ SQIDS_SALT erzeugt und in .env geschrieben.');
        }

        $this->newLine();
        $this->line('Bei gecachter Config anschließend ausführen:');
        $this->line('  php artisan config:clear   (oder: php artisan config:cache)');

        return self::SUCCESS;
    }
}
