<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningSettingsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Learning\LearningCourseCategory;
use App\Rules\MaxLineLength;
use App\Services\Learning\{LearningCourseService, LearningQuestionEditorService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Einstellungen der Lernplattform je Organisation (Feature 149, MVP-786):
 * Trainer-Scoping, Punkte/Abzeichen/Bestenliste, erlaubte Einbettungs-Hosts.
 * Alles liegt in `organizations.settings['learning']` — die Dienste lesen
 * genau diese Schlüssel.
 */
class LearningSettingsController extends Controller {
    use ResolvesCurrentOrganization;

    public function edit(): View {
        Gate::authorize(Permission::LearningManage->value);
        $settings = (array) ($this->currentOrganization()->settings['learning'] ?? []);

        return view('learning.courses._settings_dialog', [
            'scopeToCourses' => (bool) ($settings['scope_to_courses'] ?? false),
            'gamificationEnabled' => (bool) ($settings['gamification']['enabled'] ?? false),
            'leaderboardEnabled' => (bool) ($settings['gamification']['leaderboard'] ?? false),
            'embedHosts' => implode("\n", array_map('strval', (array) ($settings['embed_hosts'] ?? []))),
            // Kurskategorien (MVP-788): eine je Zeile, Reihenfolge = Position.
            'categories' => LearningCourseCategory::query()->orderBy('position')->orderBy('name')->pluck('name')->implode("\n"),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $data = $request->validate([
            'scope_to_courses' => ['nullable', 'boolean'],
            'gamification_enabled' => ['nullable', 'boolean'],
            'leaderboard_enabled' => ['nullable', 'boolean'],
            'embed_hosts' => ['nullable', 'string', 'max:2000'],
            'categories' => ['nullable', 'string', 'max:4000', new MaxLineLength(120)],
        ]);

        $organization = $this->currentOrganization();
        $settings = (array) ($organization->settings ?? []);
        data_set($settings, 'learning.scope_to_courses', (bool) ($data['scope_to_courses'] ?? false));
        data_set($settings, 'learning.gamification.enabled', (bool) ($data['gamification_enabled'] ?? false));
        // Die Bestenliste setzt Punkte voraus — ohne sie gäbe es nichts zu listen.
        data_set($settings, 'learning.gamification.leaderboard', (bool) ($data['gamification_enabled'] ?? false) && (bool) ($data['leaderboard_enabled'] ?? false));
        data_set($settings, 'learning.embed_hosts', array_values(array_unique(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            LearningQuestionEditorService::linesOf((string) ($data['embed_hosts'] ?? '')),
        ))));
        $organization->update(['settings' => $settings]);

        app(LearningCourseService::class)->syncCategories(
            $organization,
            LearningQuestionEditorService::linesOf((string) ($data['categories'] ?? '')),
        );

        return redirect()
            ->route('learning.courses.index')
            ->with('success', __('learning.flash.settings_saved'));
    }
}
