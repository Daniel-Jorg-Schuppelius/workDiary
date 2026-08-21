<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingContactController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Plugins\Contracts\ContactSyncer;
use App\Plugins\PluginManager;
use App\Services\Finance\Accounting\ContactPushService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Einzel-Push eines Kunden in die Buchhaltung (Feature 122, MVP-611).
 *
 * Ergänzt den Lauf: Wer einen Kunden gerade angelegt hat, will ihn sofort
 * drüben haben, ohne auf die Nacht zu warten.
 */
class AccountingContactController extends Controller {
    public function __construct(
        private readonly ContactPushService $contacts,
        private readonly PluginManager $plugins,
    ) {}

    public function push(Request $request, Customer $customer): RedirectResponse {
        Gate::authorize('update', $customer);

        $pluginId = trim((string) $request->input('plugin', ''));
        if ($pluginId === '') {
            $pluginId = $this->firstSyncer();
        }
        if ($pluginId === '') {
            return back()->with('error', __('accounting.flash.no_plugin'));
        }

        try {
            $externalId = $this->contacts->push($customer, $pluginId);
        } catch (Throwable $e) {
            return back()->with('error', __('accounting.flash.failed', ['msg' => $e->getMessage()]));
        }

        return back()->with('status', __('accounting.flash.pushed', ['id' => $externalId]));
    }

    /** Erstes aktives Plugin, das Kontakte überträgt. */
    private function firstSyncer(): string {
        foreach ($this->plugins->enabled() as $id => $plugin) {
            if ($plugin instanceof ContactSyncer) {
                return (string) $id;
            }
        }

        return '';
    }
}
