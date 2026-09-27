<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonalOnboardingResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Enums\User\UserRole;
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Time\{Attendance, TimeEntry};
use App\Models\Travel\Expense;
use Illuminate\Support\Facades\Route;

/**
 * Persönlicher Einstieg je Rolle (MVP-911): wenige Schritte mit Ziel, die
 * sich — wo möglich — selbst als erledigt erkennen; Schritte ohne Merkmal
 * hakt die Person ab. Ergänzt die Checkliste der Organisation (Admins).
 * Stand in den Nutzereinstellungen (`onboarding_personal`).
 */
final class PersonalOnboardingResolver {
    public const PREFERENCE = 'onboarding_personal';

    /**
     * Schritt → Rollen (leer = alle) und Ziel-Route.
     *
     * @var array<string, array{roles: list<UserRole>, route: string}>
     */
    private const STEPS = [
        'profile.two_factor' => ['roles' => [], 'route' => 'account.2fa.show'],
        'profile.startpage' => ['roles' => [], 'route' => 'account.profile.edit'],
        'dashboard.customize' => ['roles' => [], 'route' => 'dashboard.customize'],
        'time.first' => ['roles' => [UserRole::User, UserRole::Aussendienst, UserRole::Teamleitung], 'route' => 'today.show'],
        'attendance.first' => ['roles' => [UserRole::User, UserRole::Aussendienst], 'route' => 'attendance.index'],
        'expense.first' => ['roles' => [UserRole::Aussendienst], 'route' => 'expenses.index'],
        'diary.first' => ['roles' => [UserRole::Teamleitung, UserRole::Callcenter, UserRole::Support], 'route' => 'diary.index'],
        'invoice.first' => ['roles' => [UserRole::Buchhaltung], 'route' => 'invoices.index'],
        'reports.accounting' => ['roles' => [UserRole::Buchhaltung, UserRole::Geschaeftsfuehrung], 'route' => 'reports.accounting.index'],
        'org.checklist' => ['roles' => [UserRole::Admin], 'route' => 'onboarding.index'],
        'help.center' => ['roles' => [], 'route' => 'help.center.index'],
    ];

    /**
     * @return array{steps: list<array{code: string, url: ?string, done: bool, manual: bool}>, done: int, total: int, percent: int, dismissed: bool}
     */
    public function forUser(User $user): array {
        $state = $this->state($user);
        $roles = $user->getRoleNames()->all();
        $steps = [];
        foreach (self::STEPS as $code => $step) {
            $applies = $step['roles'] === [] || array_intersect(array_map(static fn (UserRole $r): string => $r->value, $step['roles']), $roles) !== [];
            if (! $applies || ! Route::has($step['route'])) {
                continue;
            }
            $detected = $this->detected($code, $user);
            $steps[] = [
                'code' => $code,
                'url' => route($step['route']),
                'done' => $detected === true || in_array($code, $state['done'], true),
                'manual' => $detected === null,
            ];
        }
        $done = count(array_filter($steps, static fn (array $s): bool => $s['done']));

        return [
            'steps' => $steps,
            'done' => $done,
            'total' => count($steps),
            'percent' => $steps === [] ? 100 : intdiv($done * 100, count($steps)),
            'dismissed' => $state['dismissed_at'] !== null,
        ];
    }

    public function markDone(User $user, string $code): void {
        abort_unless(array_key_exists($code, self::STEPS), 404);
        $state = $this->state($user);
        $state['done'] = array_values(array_unique([...$state['done'], $code]));
        $user->setPreference(self::PREFERENCE, $state);
    }

    public function dismiss(User $user): void {
        $user->setPreference(self::PREFERENCE, ['dismissed_at' => now()->toIso8601String()] + $this->state($user));
    }

    /** null = kein Merkmal, die Person hakt selbst ab. */
    private function detected(string $code, User $user): ?bool {
        return match ($code) {
            'profile.two_factor' => $user->two_factor_confirmed_at !== null || $user->twoFactorCredentials()->exists(),
            'profile.startpage' => filled($user->getPreference('startpage')),
            'dashboard.customize' => $user->dashboardWidgets()->exists(),
            'time.first' => TimeEntry::query()->where('user_id', $user->id)->exists(),
            'attendance.first' => Attendance::query()->where('user_id', $user->id)->exists(),
            'expense.first' => Expense::query()->where('user_id', $user->id)->exists(),
            'diary.first' => DiaryEntry::query()->where('user_id', $user->id)->exists(),
            'invoice.first' => Invoice::query()->where('created_by', $user->id)->exists(),
            default => null,
        };
    }

    /** @return array{done: list<string>, dismissed_at: ?string} */
    private function state(User $user): array {
        $raw = $user->getPreference(self::PREFERENCE, []);
        $raw = is_array($raw) ? $raw : [];

        return [
            'done' => array_values(array_filter((array) ($raw['done'] ?? []), 'is_string')),
            'dismissed_at' => is_string($raw['dismissed_at'] ?? null) ? $raw['dismissed_at'] : null,
        ];
    }
}
