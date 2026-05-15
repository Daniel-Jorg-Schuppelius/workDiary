<?php

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
}
