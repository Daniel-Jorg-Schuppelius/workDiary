<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrectionLearnController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Invoicing;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\TextCorrection;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Bestätigter Lernvorschlag aus dem „Merken?"-Dialog des Wörterbuchs.
 * Wer Belegtexte bearbeiten darf, darf bestätigt lernen (Analogie zum
 * KI-Lernen über `ai.use` + Fachrecht); die Pflege-UI selbst bleibt
 * `finance.config` ({@see \App\Policies\TextCorrectionPolicy}).
 */
class TextCorrectionLearnController extends Controller {
    public function __invoke(Request $request): RedirectResponse {
        abort_unless(Gate::any([
            Permission::InvoiceUpdate->value,
            Permission::FinanceTransferTime->value,
            Permission::FinanceTransferMaterial->value,
        ]), 403);

        $data = $request->validate([
            'wrong' => ['required', 'string', 'max:190'],
            'correct' => ['required', 'string', 'max:190'],
        ]);

        $wrongKey = TextCorrection::normalizeKey($data['wrong']);
        if ($wrongKey === '' || $wrongKey === TextCorrection::normalizeKey($data['correct'])) {
            return back()->with('error', __('textcorrections.flash.invalid'));
        }

        // Org-Scope kommt über den Global Scope (Web-Kontext); Duplikat wird
        // reaktiviert/aktualisiert statt doppelt angelegt.
        $existing = TextCorrection::query()->where('wrong_normalized', $wrongKey)->first();
        if ($existing !== null) {
            $existing->fill([
                'wrong' => $data['wrong'],
                'correct' => $data['correct'],
                'active' => true,
            ]);
            $existing->usage_count++;
            $existing->last_used_at = now();
            $existing->save();

            return back()->with('success', __('textcorrections.flash.duplicate_updated'));
        }

        TextCorrection::query()->create([
            'wrong' => $data['wrong'],
            'correct' => $data['correct'],
            'origin' => TextCorrection::ORIGIN_LEARNED,
            'created_by_user_id' => Auth::id(),
        ]);

        return back()->with('success', __('textcorrections.flash.learned'));
    }
}
