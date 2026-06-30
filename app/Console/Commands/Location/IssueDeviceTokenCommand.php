<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssueDeviceTokenCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Location;

use App\Http\Controllers\Api\LocationController;
use App\Models\Location\LocationDeviceToken;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Stellt für einen Nutzer ein Standort-Ingest-Token aus (OwnTracks/Traccar).
 * Der Klartext wird nur hier einmalig ausgegeben; gespeichert wird nur der Hash.
 */
class IssueDeviceTokenCommand extends Command {
    protected $signature = 'location:device-token
        {user : E-Mail oder ID des Nutzers}
        {--label=Mobilgerät : Bezeichnung des Geräts}';

    protected $description = 'Erzeugt ein Standort-Ingest-Token für ein Gerät und gibt die Push-URL aus.';

    public function handle(): int {
        $identifier = (string) $this->argument('user');

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('id', ctype_digit($identifier) ? (int) $identifier : 0)
            ->first();

        if (! $user instanceof User) {
            $this->error("Nutzer nicht gefunden: {$identifier}");

            return self::FAILURE;
        }

        [$token, $plain] = LocationDeviceToken::issue($user, (string) $this->option('label'));

        // Das Ausstellen eines Geräte-Tokens ist die bewusste Einwilligung in
        // die Standorterfassung – Opt-in setzen, damit der Ingest greift.
        if (! $user->getPreference(LocationController::OPT_IN_PREFERENCE, false)) {
            $user->setPreference(LocationController::OPT_IN_PREFERENCE, true);
            $this->line('Opt-in zur Standorterfassung aktiviert.');
        }

        $url = url("/api/location/ingest/{$plain}");

        $this->info("Token für {$user->email} ausgestellt (#{$token->id}).");
        $this->newLine();
        $this->line('Push-URL (in OwnTracks/Traccar eintragen):');
        $this->line("  <comment>{$url}</comment>");
        $this->newLine();
        $this->warn('Diese URL wird nur einmal angezeigt und kann nicht wiederhergestellt werden.');

        return self::SUCCESS;
    }
}
