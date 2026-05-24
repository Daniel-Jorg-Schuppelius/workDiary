<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\User;
use App\Services\Onboarding\OnboardingChecklistResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class OnboardingController extends Controller {
    public function __invoke(Request $request, OnboardingChecklistResolver $resolver): View {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize(Permission::OrgOnboardingView->value);

        $organization = $user->organization;
        abort_if($organization === null, 404);

        $checklist = $resolver->forOrganization($organization, $user);

        $stepMeta = [
            'org.profile' => [
                'description' => __('Pflege Name, Zeitzone und lokale Grundeinstellungen der Organisation.'),
                'label' => __('Organisation öffnen'),
                'href' => Route::has('account.profile.edit') ? route('account.profile.edit') : null,
            ],
            'org.branch_profile' => [
                'description' => __('Wähle ein Branchenprofil, damit passende Defaults für Klassifikationen bereitstehen.'),
                'label' => __('Branchenprofile öffnen'),
                'href' => Route::has('admin.branch-profiles.index') ? route('admin.branch-profiles.index') : null,
            ],
            'users.invite' => [
                'description' => __('Lade mindestens eine weitere aktive Person in deine Organisation ein.'),
                'label' => __('Mitglieder öffnen'),
                'href' => Route::has('org-members.index') ? route('org-members.index') : null,
            ],
            'roles.check' => [
                'description' => __('Prüfe, dass mindestens ein Org-Admin und ein Operator zugewiesen sind.'),
                'label' => __('Rechteverwaltung öffnen'),
                'href' => Route::has('admin.access.members.index') ? route('admin.access.members.index') : null,
            ],
            'classification.check' => [
                'description' => __('Bestätige oder überschreibe mindestens eine Klassifikationsdomäne für die Organisation.'),
                'label' => __('Klassifikationen öffnen'),
                'href' => Route::has('admin.classifications.index') ? route('admin.classifications.index') : null,
            ],
            'customer.first' => [
                'description' => __('Lege den ersten Kunden manuell an oder nutze den CSV-Import.'),
                'label' => __('Kunden öffnen'),
                'href' => Route::has('customers.index') ? route('customers.index') : null,
            ],
            'work.first' => [
                'description' => __('Erzeuge ein erstes Projekt oder starte den ersten Auftrag im Tagebuch.'),
                'label' => __('Projekte öffnen'),
                'href' => Route::has('projects.index') ? route('projects.index') : null,
            ],
            'time.first' => [
                'description' => __('Erfasse mindestens einen Zeiteintrag, um die Arbeitszeiterfassung zu aktivieren.'),
                'label' => __('Zeiterfassung öffnen'),
                'href' => Route::has('time-entries.create') ? route('time-entries.create') : null,
            ],
            'protocol.first_signed' => [
                'description' => __('Erstelle ein Protokoll und schließe die Signatur ab.'),
                'label' => __('Tagebuch öffnen'),
                'href' => Route::has('diary.index') ? route('diary.index') : null,
            ],
            'backup.heartbeat' => [
                'description' => __('Konfiguriere den Backup-Lauf so, dass regelmäßig erfolgreiche Heartbeats geschrieben werden.'),
                'label' => __('Audit-Log öffnen'),
                'href' => Route::has('audit.index') ? route('audit.index') : null,
            ],
        ];

        $steps = array_map(
            static function (array $step) use ($stepMeta): array {
                $meta = $stepMeta[$step['code']] ?? [
                    'description' => null,
                    'label' => null,
                    'href' => null,
                ];

                return array_merge($step, $meta);
            },
            $checklist['steps']
        );

        return view('onboarding.index', [
            'organization' => $organization,
            'checklist' => array_merge($checklist, ['steps' => $steps]),
        ]);
    }
}
