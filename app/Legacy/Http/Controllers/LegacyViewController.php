<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyViewController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Support\LegacyView;
use Illuminate\Http\{RedirectResponse, Request};

/** Umschalten zwischen klassischer und neuer Darstellung des Legacy-Bereichs (je Browser). */
class LegacyViewController extends Controller {
    /** Rücksprung nur auf Seiten des Legacy-Bereichs derselben Installation. */
    private const string BACK_PATTERN = '~^/legacy/[A-Za-z0-9/_\-]*(\?[A-Za-z0-9=&%_.+,\-]*)?$~';

    /** Fünf Jahre: wer die alte Darstellung will, soll sie behalten. */
    private const int COOKIE_MINUTES = 60 * 24 * 365 * 5;

    public function switch(Request $request, string $mode): RedirectResponse {
        $back = (string) $request->query('back', '');
        $fallback = match (true) {
            $request->user() !== null => route('legacy.diary.week'),
            $request->session()->has('legacy_callcenter_user') => route('legacy.callcenter.notdienst'),
            default => route('legacy.portal.login'),
        };
        $target = preg_match(self::BACK_PATTERN, $back) === 1 ? url($back) : $fallback;

        return redirect()->to($target)
            ->withCookie(cookie(LegacyView::COOKIE, $mode, self::COOKIE_MINUTES));
    }
}
