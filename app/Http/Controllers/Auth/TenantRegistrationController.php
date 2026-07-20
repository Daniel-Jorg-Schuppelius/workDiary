<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantRegistrationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Auth;

use App\Enums\User\UserRole;
use App\Http\Controllers\Controller;
use App\Models\{Organization, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class TenantRegistrationController extends Controller {
    /**
     * Nur erreichbar wenn REGISTRATION_ENABLED=true in der Konfiguration.
     */
    public function showForm(): View {
        abort_unless(config('app.registration_enabled'), 404);

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse {
        abort_unless(config('app.registration_enabled'), 404);

        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        // Vollaudit 2026-07 (H8): max_orgs der Installations-Lizenz gilt auch
        // für die öffentliche Registrierung — vorher ohne jeden Check erreichbar.
        try {
            app(\App\Services\Licensing\LimitGuard::class)->ensureCanCreateOrganization();
        } catch (\App\Exceptions\LimitExceededException) {
            return back()->withInput()->withErrors([
                'org_name' => __('Diese Installation hat ihr Organisations-Limit erreicht — bitte den Betreiber kontaktieren.'),
            ]);
        }

        $user = DB::transaction(function () use ($data): User {
            // Organisation anlegen (owner_id wird nach User-Erstellung gesetzt)
            $org = Organization::create([
                'name' => $data['org_name'],
                'plan' => Organization::PLAN_FREE,
                'locale' => app()->getLocale(),
                'timezone' => 'Europe/Berlin',
                'is_active' => true,
            ]);

            /** @var User $user */
            $user = User::create([
                'organization_id' => $org->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_new_system' => true,
            ]);

            // Owner setzen
            $org->update(['owner_id' => $user->id]);

            // Admin-Rolle zuweisen (Rolle muss existieren)
            $adminRole = Role::findOrCreate(UserRole::Admin->value, 'web');
            $user->assignRole($adminRole);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', __('Willkommen! Ihre Organisation wurde eingerichtet.'));
    }
}
