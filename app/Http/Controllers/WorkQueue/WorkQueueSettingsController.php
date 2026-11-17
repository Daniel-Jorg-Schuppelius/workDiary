<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkQueueSettingsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\WorkQueue;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Services\WorkQueue\WorkQueue;
use App\Settings\{SettingScope, SettingsRegistry};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Einstellung des Arbeitsvorrats je Organisation (Feature 179, MVP-1670): ab
 * wie vielen Tagen ohne Bewegung ein Vorgang als ruhend gilt (Vorgabe 14).
 * Pflegen darf, wer die Organisation bearbeitet.
 */
class WorkQueueSettingsController extends Controller {
    use ResolvesCurrentOrganization;

    public function edit(WorkQueue $queue): View {
        Gate::authorize(Permission::OrganizationUpdate->value);

        return view('work-queue._settings_dialog', [
            'dormantDays' => $queue->dormantDays($this->currentOrganization()),
        ]);
    }

    public function update(Request $request, SettingsRegistry $settings): RedirectResponse {
        Gate::authorize(Permission::OrganizationUpdate->value);
        $data = $request->validate([
            'dormant_days' => ['required', 'integer', 'min:1', 'max:' . WorkQueue::MAX_DORMANT_DAYS],
        ]);

        $settings->set(WorkQueue::SETTING_DORMANT_DAYS, (int) $data['dormant_days'], SettingScope::Organization, $this->currentOrganization(), (int) $this->authUser()->id);

        return back()->with('success', __('work-queue.settings.saved'));
    }
}
