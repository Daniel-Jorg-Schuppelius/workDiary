<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementDigestCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reporting;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\User\Permission;
use App\Mail\ManagementDigestMail;
use App\Models\Platform\{Organization, User};
use App\Services\Reporting\ManagementReportPdf;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{App, Mail};
use Spatie\Permission\PermissionRegistrar;

/**
 * Geplanter Leitungsbericht (Feature 184): je Organisation an alle mit dem
 * Recht „Leitungsbericht per E-Mail erhalten“, Inhalt nach den Rechten des
 * Empfängers. Zeitraum: Monatsanfang bis gestern — am Monatsersten der ganze
 * Vormonat.
 */
class ManagementDigestCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'reports:management-digest ' . self::ORGANIZATION_OPTION;

    protected $description = 'Versendet den Leitungsbericht als PDF an die berechtigten Empfänger (Feature 184)';

    public function handle(ManagementReportPdf $report): int {
        $to = CarbonImmutable::yesterday();
        $from = $to->startOfMonth();

        $failures = $this->forEachOrganization(function (Organization $organization) use ($report, $from, $to): void {
            // Konsole ohne Spatie-Team-Kontext — ohne ihn fänden die Rechteprüfungen keine Org-Rollen.
            $registrar = app(PermissionRegistrar::class);
            $previousTeam = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId($organization->id);
            $sent = 0;

            try {
                $recipients = User::query()
                    ->where('organization_id', $organization->id)
                    ->whereNull('deactivated_at')
                    ->whereNotNull('email')
                    ->get()
                    ->filter(fn (User $user): bool => $user->hasEffectivePermission(Permission::ReportManagementDigest->value));

                foreach ($recipients as $recipient) {
                    $previousLocale = App::getLocale();
                    App::setLocale($recipient->preferredLocale() ?? $previousLocale);
                    try {
                        $range = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);
                        Mail::to($recipient)->send(new ManagementDigestMail(
                            (string) $recipient->name,
                            (string) $organization->name,
                            $range,
                            $report->render($recipient, $organization, $from, $to),
                            sprintf('leitungsbericht_%s_%s.pdf', $from->toDateString(), $to->toDateString()),
                        ));
                        $sent++;
                    } finally {
                        App::setLocale($previousLocale);
                    }
                }
            } finally {
                $registrar->setPermissionsTeamId($previousTeam);
            }

            $this->line("Organisation #{$organization->id} ({$organization->name}): {$sent} Leitungsberichte versendet.");
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
