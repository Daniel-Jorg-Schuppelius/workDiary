<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgMemberController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\{Permission, UserRole};
use App\Http\Controllers\Concerns\{AuditsAccessChanges, ManagesUserContactDetails};
use App\Models\User;
use App\Services\Licensing\LimitGuard;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Hash};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Verwaltet Mitglieder der eigenen Organisation.
 * Nur Org-Admins dürfen zugreifen (Gate 'manage-members' via OrganizationPolicy).
 */
class OrgMemberController extends Controller {
    use AuditsAccessChanges;
    use ManagesUserContactDetails;
    public function index(Request $request): View {
        /** @var User $auth */
        $auth = Auth::user();
        $this->authorizeMemberDirectoryAccess($auth);

        // TENANT-BYPASS: User-Model nutzt keinen BelongsToOrganization-Trait
        // (Authenticatable-Sonderfall, siehe ../WorkDiary-Architecture/security/tenant-audit-2026.md).
        // Mandantengrenze daher hier explizit über organization_id.
        $query = User::withoutGlobalScopes()
            ->where('organization_id', $auth->organization_id)
            ->with('roles');

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'name'             => 'name',
            'personnel_number' => 'personnel_number',
            'email'            => 'email',
            'created_at'       => 'created_at',
        ], 'name', 'asc');

        $members = $query->paginate(25)->withQueryString();

        $roles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];
        $canManageMembers = $this->canManageMembers($auth);
        $canManagePayroll = $this->canManagePayroll($auth);

        return view('org.members.index', compact('members', 'roles', 'sort', 'dir', 'canManageMembers', 'canManagePayroll'));
    }

    public function create(): View {
        Gate::authorize('manage-members');

        /** @var User $auth */
        $auth = Auth::user();
        $roles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];
        $canManageMembers = $this->canManageMembers($auth);
        $canManagePayroll = $this->canManagePayroll($auth);

        return view('org.members._form_dialog', compact('roles', 'canManageMembers', 'canManagePayroll') + ['member' => null, 'isEdit' => false]);
    }

    public function store(Request $request, LimitGuard $limits): RedirectResponse {
        Gate::authorize('manage-members');

        /** @var User $auth */
        $auth = Auth::user();

        // Lizenz-Nutzerlimit der Organisation durchsetzen (Feature 021).
        // Wirft LimitExceededException (HTTP 423 / Flash-Error) bei Erreichen.
        if ($auth->organization !== null) {
            $limits->ensureCanCreateUser($auth->organization, $auth);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'personnel_number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('users', 'personnel_number')
                    ->where('organization_id', $auth->organization_id),
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:' . implode(',', [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value])],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ] + $this->payrollDetailRules($auth) + $this->contactDetailRules());

        $user = User::create([
            'organization_id' => $auth->organization_id,
            'name' => $data['name'],
            'personnel_number' => $this->blankToNull($data['personnel_number'] ?? null),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'must_change_password' => true,
            'is_new_system' => true,
        ]);
        $this->fillUserPayrollFields($user, $data, $auth);
        $this->fillUserContactFields($user, $data);
        $user->save();

        $this->syncUserAddress($user, (array) ($data['address'] ?? []));
        $this->syncUserBankAccount($user, (array) ($data['bank'] ?? []));

        $role = Role::findOrCreate($data['role'], 'web');
        $user->assignRole($role);
        // Bauturbo A17 (MVP-335): Rollen-Vergabe revisionssicher auditieren
        // (supportzugriff-grundsaetze.md §4.1). Neuer User → immer echte Vergabe.
        $this->auditAssignedRole($user, $role);

        return redirect()->route('org.members.index')
            ->with('success', __('Mitglied wurde angelegt.'));
    }

    /** MVP-537 (Q1 S. 110): Import-Dialog (CSV-Upload) der Benutzerverwaltung. */
    public function importForm(): View {
        Gate::authorize('manage-members');

        return view('org.members._import_dialog');
    }

    /** MVP-537: CSV-Vorlage mit den erwarteten Spalten. */
    public function importTemplate(): \Symfony\Component\HttpFoundation\Response {
        Gate::authorize('manage-members');

        return response("name;email;personnel_number;role\nMax Mustermann;max@example.com;1001;user\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="personal-import-vorlage.csv"',
        ]);
    }

    /**
     * MVP-537: Personalstamm-CSV-Import — legt fehlende Benutzer mit den
     * Vorlagen-Defaults an (Zufallspasswort, Passwortwechsel erzwungen,
     * `is_new_system`); vorhandene E-Mails und ungültige Zeilen werden mit
     * Grund übersprungen. Nutzerlimit der Lizenz wird je Zeile durchgesetzt.
     */
    public function import(Request $request, LimitGuard $limits): RedirectResponse {
        Gate::authorize('manage-members');

        /** @var User $auth */
        $auth = Auth::user();
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        // Toolkit-CSV statt Handparser (Vollscan 2026-08-23, C5): Delimiter-
        // Erkennung, Quoting und mehrzeilige Felder übers common-toolkit.
        $path = (string) $request->file('csv')->getRealPath();
        try {
            $delimiter = \CommonToolkit\Parsers\CSVDocumentParser::detectDelimiter($path);
            $header = array_map(
                static fn (string $h): string => strtolower(trim($h)),
                array_values(\CommonToolkit\Parsers\CSVDocumentParser::readHeader($path, $delimiter)->getColumnNames()),
            );
        } catch (\Throwable) {
            return back()->withErrors(['csv' => __('Die Datei ist leer.')]);
        }
        if (! in_array('name', $header, true) || ! in_array('email', $header, true)) {
            return back()->withErrors(['csv' => __('Kopfzeile muss mindestens die Spalten name und email enthalten.')]);
        }
        $allowedRoles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];

        $created = 0;
        $skipped = [];
        foreach (\App\Support\Toolkit\CsvFacade::streamAssoc($path, $delimiter) as $lineNo => $row) {
            $data = [];
            foreach ($row as $column => $value) {
                $data[strtolower(trim($column))] = $value;
            }
            if (trim(implode('', $data)) === '') {
                continue;
            }
            $name = trim((string) ($data['name'] ?? ''));
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $personnelNumber = trim((string) ($data['personnel_number'] ?? ''));
            $role = strtolower(trim((string) ($data['role'] ?? '')));
            $role = $role === '' ? UserRole::User->value : $role;

            if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = __('Zeile :line: Name oder E-Mail ungültig.', ['line' => $lineNo]);

                continue;
            }
            if (! in_array($role, $allowedRoles, true)) {
                $skipped[] = __('Zeile :line: unbekannte Rolle „:role".', ['line' => $lineNo, 'role' => $role]);

                continue;
            }
            if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
                $skipped[] = __('Zeile :line: E-Mail :email existiert bereits.', ['line' => $lineNo, 'email' => $email]);

                continue;
            }
            if ($personnelNumber !== '' && User::withoutGlobalScopes()
                ->where('organization_id', $auth->organization_id)
                ->where('personnel_number', $personnelNumber)->exists()) {
                $skipped[] = __('Zeile :line: Personalnummer :pn ist bereits vergeben.', ['line' => $lineNo, 'pn' => $personnelNumber]);

                continue;
            }

            try {
                if ($auth->organization !== null) {
                    $limits->ensureCanCreateUser($auth->organization, $auth);
                }
            } catch (\Throwable) {
                $skipped[] = __('Zeile :line und folgende: Nutzerlimit der Lizenz erreicht.', ['line' => $lineNo]);

                break;
            }

            $user = User::create([
                'organization_id' => $auth->organization_id,
                'name' => $name,
                'personnel_number' => $personnelNumber !== '' ? $personnelNumber : null,
                'email' => $email,
                'password' => Hash::make(\Illuminate\Support\Str::password(40)),
                'must_change_password' => true,
                'is_new_system' => true,
            ]);
            $roleModel = Role::findOrCreate($role, 'web');
            $user->assignRole($roleModel);
            $this->auditAssignedRole($user, $roleModel);
            $created++;
        }

        $summary = __(':created Benutzer angelegt, :skipped übersprungen.', ['created' => $created, 'skipped' => count($skipped)]);
        if ($skipped !== []) {
            $summary .= ' ' . implode(' ', array_slice($skipped, 0, 10));
            if (count($skipped) > 10) {
                $summary .= ' ' . __('(+:n weitere)', ['n' => count($skipped) - 10]);
            }
        }

        return redirect()->route('org.members.index')->with($created > 0 ? 'success' : 'error', $summary);
    }

    public function edit(User $member): View {
        /** @var User $auth */
        $auth = Auth::user();
        $this->authorizeMemberDirectoryAccess($auth);
        $this->ensureSameOrg($member);
        $member->loadMissing(['addresses', 'bankAccounts']);

        $roles = [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value];
        $canManageMembers = $this->canManageMembers($auth);
        $canManagePayroll = $this->canManagePayroll($auth);
        // Stellvertreter-Auswahl (MVP-523): Mitglieder derselben Organisation.
        $deputyOptions = User::withoutGlobalScopes()
            ->where('organization_id', $auth->organization_id)
            ->whereKeyNot($member->getKey())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('org.members._form_dialog', compact('member', 'roles', 'canManageMembers', 'canManagePayroll', 'deputyOptions') + ['isEdit' => true]);
    }

    public function update(Request $request, User $member): RedirectResponse {
        $this->ensureSameOrg($member);

        /** @var User $auth */
        $auth = Auth::user();
        $this->authorizeMemberDirectoryAccess($auth);

        // Personalverwaltung/Geschäftsführung (kein Admin): dürfen ausschließlich
        // den Personal-/Payroll-Block pflegen — keine Identität, Rolle, Passwort.
        if (! $this->canManageMembers($auth)) {
            abort_unless($this->canManagePayroll($auth), 403);

            $data = $request->validate($this->payrollDetailRules($auth));
            $this->fillUserPayrollFields($member, $data, $auth);
            $member->save();

            return redirect()->route('org.members.index')
                ->with('success', __('Personaldaten wurden aktualisiert.'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'personnel_number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('users', 'personnel_number')
                    ->where('organization_id', $auth->organization_id)
                    ->ignore($member->id),
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $member->id],
            'role' => ['required', 'in:' . implode(',', [UserRole::Admin->value, UserRole::User->value, UserRole::Buchhaltung->value])],
            // Admin kann optional ein neues Passwort setzen (Mitarbeiter ändert es beim nächsten Login).
            'new_password' => ['nullable', 'confirmed', Password::defaults()],
            // Stellvertretung (MVP-523): Sqid eines Org-Mitglieds oder leer.
            'deputy_user_id' => ['nullable', 'string'],
        ] + $this->payrollDetailRules($auth) + $this->contactDetailRules());

        // Stellvertreter auflösen: Org-Grenze + nicht sich selbst.
        $deputyId = null;
        if (filled($data['deputy_user_id'] ?? null)) {
            $deputyId = \App\Support\Sqid::decodeOrNumeric(User::class, (string) $data['deputy_user_id']);
            $deputyValid = $deputyId !== $member->getKey() && User::withoutGlobalScopes()
                ->where('organization_id', $auth->organization_id)
                ->whereKey($deputyId)
                ->exists();
            $deputyId = $deputyValid ? (int) $deputyId : null;
        }

        $member->fill([
            'name' => $data['name'],
            'personnel_number' => $this->blankToNull($data['personnel_number'] ?? null),
            'email' => $data['email'],
            'deputy_user_id' => $deputyId,
        ]);
        $this->fillUserPayrollFields($member, $data, $auth);
        $this->fillUserContactFields($member, $data);
        if (filled($data['new_password'] ?? null)) {
            // Neues Passwort gilt im Neu-System (bcrypt). is_new_system aktivieren,
            // damit der Login users.password prüft statt der Legacy-DB.
            $member->forceFill([
                'password' => Hash::make($data['new_password']),
                'is_new_system' => true,
                'must_change_password' => true,
            ]);
        }
        $member->save();

        $this->syncUserAddress($member, (array) ($data['address'] ?? []));
        $this->syncUserBankAccount($member, (array) ($data['bank'] ?? []));

        $role = Role::findOrCreate($data['role'], 'web');
        // Bauturbo A17 (MVP-335): Rollen-Wechsel als assigned/revoked-Diff
        // auditieren; unveränderte Rolle erzeugt kein Event.
        $this->syncRolesAudited($member, [$role]);

        return redirect()->route('org.members.index')
            ->with('success', __('Mitglied wurde aktualisiert.'));
    }

    /**
     * Austritt (Feature 126, MVP-689 — H1/E4): der Regelweg für ausscheidende
     * Mitarbeiter. Deaktiviert zum Stichtag, Nachweise bleiben stehen, der
     * Lizenzsitz wird frei. Zutrittsmedien-Guard wie beim Entfernen.
     */
    public function offboard(\Illuminate\Http\Request $request, User $member): RedirectResponse {
        Gate::authorize('manage-members');
        $this->ensureSameOrg($member);

        /** @var User $auth */
        $auth = Auth::user();
        if ($member->id === $auth->id) {
            return back()->with('error', __('Sie können sich nicht selbst entfernen.'));
        }

        $data = $request->validate(['left_at' => ['required', 'date']]);

        $openMedia = app(\App\Services\Access\AccessMediumService::class)->openMediaFor($member);
        if ($openMedia->isNotEmpty()) {
            return back()->with('error', __(':name hält noch :count Zutrittsmedien (:list) — erst zurücknehmen, dann entfernen.', [
                'name' => $member->name,
                'count' => $openMedia->count(),
                'list' => $openMedia->map(fn ($m) => ($m->label ?: __('Medium')) . ' …' . $m->number_suffix)->implode(', '),
            ]));
        }

        app(\App\Services\Org\UserOffboardingService::class)
            ->initiate($member, \Carbon\CarbonImmutable::parse($data['left_at']), $auth);

        return redirect()->route('org.members.index')
            ->with('success', $member->fresh()?->isDeactivated()
                ? __(':name ist ausgeschieden — das Konto ist deaktiviert, der Lizenzsitz ist frei.', ['name' => $member->name])
                : __('Austritt von :name zum :date vorgemerkt.', ['name' => $member->name, 'date' => \Carbon\CarbonImmutable::parse($data['left_at'])->format('d.m.Y')]));
    }

    public function destroy(User $member): RedirectResponse {
        Gate::authorize('manage-members');
        $this->ensureSameOrg($member);

        /** @var User $auth */
        $auth = Auth::user();

        if ($member->id === $auth->id) {
            return back()->with('error', __('Sie können sich nicht selbst entfernen.'));
        }

        // H1/E4: Konten mit aufbewahrungspflichtigen Nachweisen (ArbZG/MiLoG/
        // GoBD) werden nie gelöscht — der Austritt ist der Regelweg; die
        // RESTRICT-FKs (Migration 101000) sind das Netz darunter.
        if (app(\App\Services\Org\UserOffboardingService::class)->hasEvidence($member)) {
            return back()->with('error', __(':name hat aufbewahrungspflichtige Nachweise (Zeiten/Lohn) — bitte den Austritt nutzen statt zu löschen.', ['name' => $member->name]));
        }

        // Offboarding-Check (Feature 092): Wer geht, gibt erst ab. Offene
        // Zutrittsmedien blockieren das Entfernen - ein gelöschtes Mitglied
        // mit Transponder in der Tasche ist genau das Loch, das die
        // Medienverwaltung schließen soll.
        $openMedia = app(\App\Services\Access\AccessMediumService::class)->openMediaFor($member);
        if ($openMedia->isNotEmpty()) {
            return back()->with('error', __(':name hält noch :count Zutrittsmedien (:list) — erst zurücknehmen, dann entfernen.', [
                'name' => $member->name,
                'count' => $openMedia->count(),
                'list' => $openMedia->map(fn ($m) => ($m->label ?: __('Medium')) . ' …' . $m->number_suffix)->implode(', '),
            ]));
        }

        $member->delete();

        return redirect()->route('org.members.index')
            ->with('success', __('Mitglied wurde entfernt.'));
    }

    private function ensureSameOrg(User $member): void {
        /** @var User $auth */
        $auth = Auth::user();

        abort_unless($member->organization_id === $auth->organization_id, 403);
    }

    private function authorizeMemberDirectoryAccess(User $user): void {
        abort_unless($this->canManageMembers($user) || $this->canManagePayroll($user), 403);
    }

    /** Voller Mitglieder-Stamm (Identität, Rolle, Passwort, Anlegen/Löschen) — nur Admin. */
    private function canManageMembers(User $user): bool {
        return $user->isAdmin() && $user->organization_id !== null;
    }

    /**
     * Personal-/Lohndaten pflegen — Personalverwaltung + Geschäftsführung
     * (Admin via syncPermissions/Bypass). Steuert auch den Stundenlohn.
     */
    private function canManagePayroll(User $user): bool {
        return $user->organization_id !== null && $user->can(Permission::UserPayrollManage->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function payrollDetailRules(User $user): array {
        $rules = [
            'tax_identification_number' => ['nullable', 'string', 'max:32'],
            'social_security_number' => ['nullable', 'string', 'max:64'],
            'date_of_birth' => ['nullable', 'date'],
            'health_insurance' => ['nullable', 'string', 'max:128'],
            'tax_class' => ['nullable', 'string', 'max:16'],
            'child_allowances' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'church_tax' => ['nullable', 'boolean'],
            'employment_start_date' => ['nullable', 'date'],
            'employment_end_date' => ['nullable', 'date', 'after_or_equal:employment_start_date'],
            'employment_type' => ['nullable', Rule::enum(\App\Enums\User\EmploymentType::class)],
        ];

        if ($this->canManagePayroll($user)) {
            $rules['payroll_hourly_wage'] = ['nullable', 'numeric', 'min:0', 'max:99999999.99'];

            // Vergütungsmodell (auch extern): je nach Modell Pauschale (Betrag+Intervall) bzw. Stundensatz.
            $rules['compensation_model'] = ['nullable', Rule::enum(\App\Enums\User\CompensationModel::class)];
            $rules['flat_amount'] = ['nullable', 'required_if:compensation_model,pauschal', 'numeric', 'min:0', 'max:99999999.99'];
            $rules['flat_interval'] = ['nullable', 'required_if:compensation_model,pauschal', Rule::enum(\App\Enums\User\FlatInterval::class)];
            $rules['compensation_rate'] = ['nullable', 'required_if:compensation_model,nach_zeitaufwand', 'numeric', 'min:0', 'max:99999999.99'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillUserPayrollFields(User $member, array $data, User $auth): void {
        $payload = [
            'tax_identification_number' => $this->blankToNull($data['tax_identification_number'] ?? null),
            'social_security_number' => $this->blankToNull($data['social_security_number'] ?? null),
            'date_of_birth' => $this->blankToNull($data['date_of_birth'] ?? null),
            'health_insurance' => $this->blankToNull($data['health_insurance'] ?? null),
            'tax_class' => $this->blankToNull($data['tax_class'] ?? null),
            'child_allowances' => $data['child_allowances'] ?? null,
            'church_tax' => (bool) ($data['church_tax'] ?? false),
            'employment_start_date' => $this->blankToNull($data['employment_start_date'] ?? null),
            'employment_end_date' => $this->blankToNull($data['employment_end_date'] ?? null),
            'employment_type' => $this->blankToNull($data['employment_type'] ?? null),
        ];

        if ($this->canManagePayroll($auth)) {
            $payload['payroll_hourly_wage'] = $data['payroll_hourly_wage'] ?? null;

            $model = $this->blankToNull($data['compensation_model'] ?? null);
            $payload['compensation_model'] = $model;
            // Nur die zum Modell passenden Felder behalten, andere auf null setzen.
            $payload['flat_amount'] = $model === \App\Enums\User\CompensationModel::Pauschal->value ? ($data['flat_amount'] ?? null) : null;
            $payload['flat_interval'] = $model === \App\Enums\User\CompensationModel::Pauschal->value ? $this->blankToNull($data['flat_interval'] ?? null) : null;
            $payload['compensation_rate'] = $model === \App\Enums\User\CompensationModel::NachZeitaufwand->value ? ($data['compensation_rate'] ?? null) : null;
        }

        $member->fill($payload);
    }
}
