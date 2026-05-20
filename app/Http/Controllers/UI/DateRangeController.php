<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DateRangeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\UI;

use App\Http\Controllers\Controller;
use App\Services\UI\DateRangeContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DateRangeController extends Controller {
    public function update(Request $request, DateRangeContext $context): RedirectResponse {
        $data = $request->validate([
            'preset' => ['required', 'string', Rule::in(DateRangeContext::PRESETS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $context->set(
            (string) $data['preset'],
            isset($data['from']) ? (string) $data['from'] : null,
            isset($data['to']) ? (string) $data['to'] : null,
        );

        return redirect()->back();
    }

    public function shift(Request $request, DateRangeContext $context): RedirectResponse {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['prev', 'next'])],
        ]);

        $context->shift($data['direction'] === 'next' ? 1 : -1);

        return redirect()->back();
    }
}
