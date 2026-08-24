<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegalPageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Setting;
use Illuminate\View\View;

/**
 * Öffentliche Rechtstexte der Installation (MVP-326): Impressum und
 * Datenschutzerklärung. Inhalte pflegt der Betreiber über die
 * Settings-Registry (legal.imprint / legal.privacy, System-Scope);
 * ohne hinterlegten Text erscheint ein Hinweis-Platzhalter, damit die
 * Seiten nie ins Leere laufen.
 */
class LegalPageController extends Controller {
    public function imprint(): View {
        return $this->page('legal.imprint', __('Impressum'));
    }

    public function privacy(): View {
        return $this->page('legal.privacy', __('Datenschutz'));
    }

    /**
     * Barrierefreiheitserklärung (Vollscan 2026-08-23, H18 — BFSG):
     * Betreiber-Text über `legal.accessibility`; ohne hinterlegten Text
     * rendert die View das Anlage-3-Gerüst als strukturierten Default,
     * damit die Pflichtseite nie leer ist.
     */
    public function accessibility(): View {
        $content = Setting::get('legal.accessibility');

        return view('legal.accessibility', [
            'title' => __('Barrierefreiheit'),
            'content' => is_string($content) && trim($content) !== '' ? $content : null,
        ]);
    }

    private function page(string $settingKey, string $title): View {
        $content = Setting::get($settingKey);

        return view('legal.show', [
            'title' => $title,
            'settingKey' => $settingKey,
            'content' => is_string($content) && trim($content) !== '' ? $content : null,
        ]);
    }
}
