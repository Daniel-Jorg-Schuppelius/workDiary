<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardCustomizationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Me;

use App\Dashboard\WidgetRegistry;
use App\Http\Controllers\Controller;
use App\Models\{User, UserDashboardWidget};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\View\View;

class DashboardCustomizationController extends Controller {
    public function index(WidgetRegistry $registry): View {
        /** @var User $user */
        $user = Auth::user();

        $available = $registry->availableFor($user);
        $config = $user->dashboardWidgets()->get()->keyBy('widget_key');

        // Merge stored preferences with available widgets and sort.
        $items = $available->map(function ($widget) use ($config) {
            /** @var UserDashboardWidget|null $stored */
            $stored = $config->get($widget->key());

            return [
                'key' => $widget->key(),
                'label' => $widget->label(),
                'icon' => $widget->icon(),
                'sort_order' => $stored !== null ? $stored->sort_order : 999,
                'hidden' => $stored !== null ? $stored->hidden : false,
            ];
        })
            ->sortBy(fn(array $i) => [$i['sort_order'], $i['label']])
            ->values()
            ->all();

        return view('dashboard.customize', [
            'items' => $items,
        ]);
    }

    public function save(Request $request, WidgetRegistry $registry): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        $payload = $request->validate([
            'widgets' => ['required', 'array'],
            'widgets.*.key' => ['required', 'string', 'max:80'],
            'widgets.*.hidden' => ['nullable', 'boolean'],
        ]);

        $availableKeys = $registry->availableFor($user)->keys()->all();

        DB::transaction(function () use ($user, $payload, $availableKeys): void {
            $user->dashboardWidgets()->delete();
            $sort = 0;
            foreach ($payload['widgets'] as $row) {
                if (! in_array($row['key'], $availableKeys, true)) {
                    continue;
                }
                UserDashboardWidget::create([
                    'user_id' => $user->id,
                    'widget_key' => $row['key'],
                    'sort_order' => $sort++,
                    'hidden' => (bool) ($row['hidden'] ?? false),
                ]);
            }
        });

        return redirect()->route('dashboard.customize')
            ->with('status', __('Dashboard-Konfiguration gespeichert.'));
    }
}
