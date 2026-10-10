<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoicesDigestCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, User};
use App\Notifications\Finance\IncomingInvoicesDigestNotification;
use Illuminate\Console\Command;

/**
 * Rechnungseingang (Feature 163, MVP-1110): benachrichtigt je Organisation die
 * Buchhaltung, wenn Eingänge auf ihre Zuordnung warten. Ohne Befund geht
 * nichts hinaus; die Arbeitsliste trägt denselben Zähler im Menü.
 */
class IncomingInvoicesDigestCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'incoming-invoices:digest ' . self::ORGANIZATION_OPTION;

    protected $description = 'Benachrichtigt die Buchhaltung über zuzuordnende Rechnungseingänge.';

    public function handle(): int {
        $this->forEachOrganization(function (Organization $org): void {
            $open = IncomingEInvoice::query()
                ->whereNull('supplier_id')->whereNull('customer_id')
                ->where('status', '!=', IncomingEInvoiceStatus::Rejected->value);
            $openCount = (clone $open)->count();
            if ($openCount === 0) {
                return;
            }
            $unrecognized = (clone $open)->where('recognition', IncomingInvoiceRecognition::None->value)->count();

            // Konsole ohne Spatie-Team-Kontext: ohne ihn findet die Rollenprüfung keine Org-Rollen.
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            $previousTeamId = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId($org->id);
            try {
                $recipients = User::query()
                    ->where('organization_id', $org->id)
                    ->get()
                    ->filter(static fn (User $user): bool => $user->canManageBilling())
                    ->values();
            } finally {
                $registrar->setPermissionsTeamId($previousTeamId);
            }

            foreach ($recipients as $recipient) {
                $recipient->notify(new IncomingInvoicesDigestNotification($openCount, $unrecognized));
            }
            $this->line("Organisation #{$org->id}: {$openCount} zuzuordnen, davon {$unrecognized} ohne Daten → {$recipients->count()} Empfänger.");
        });

        return self::SUCCESS;
    }
}
