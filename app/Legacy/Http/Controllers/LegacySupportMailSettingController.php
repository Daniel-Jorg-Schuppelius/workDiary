<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportMailSettingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\RequiresLegacyAdmin;
use App\Legacy\Models\LegacySupportMailSetting;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/** Mail-Hinweis bei neuen Kundenaufträgen (Schalter der Auftragsübersicht im Altportal). */
class LegacySupportMailSettingController extends Controller {
    use RequiresLegacyAdmin;

    public function edit(): View {
        $this->ensureAdmin();

        return view('legacy.support.mail._form_dialog', ['setting' => LegacySupportMailSetting::current()]);
    }

    public function update(Request $request): RedirectResponse {
        $this->ensureAdmin();
        $data = $request->validate([
            'email' => ['nullable', 'required_if_accepted:enabled', 'email', 'max:' . LegacySupportMailSetting::EMAIL_MAX_LENGTH],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $enabled = (bool) ($data['enabled'] ?? false);
        $setting = LegacySupportMailSetting::current() ?? new LegacySupportMailSetting();
        $setting->id = LegacySupportMailSetting::ROW_ID;
        $setting->email = (string) ($data['email'] ?? '');
        $setting->schalt = $enabled ? LegacySupportMailSetting::SWITCH_ON : LegacySupportMailSetting::SWITCH_OFF;
        $setting->save();

        return redirect()->route('legacy.support.orders.index')
            ->with('success', $enabled ? __('legacy_support.flash.mail_on') : __('legacy_support.flash.mail_off'));
    }
}
