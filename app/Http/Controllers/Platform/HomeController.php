<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HomeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Platform;

use App\Enums\Modules\ModuleKind;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Modules\{Manifest, ModuleRegistry};
use App\Plugins\PluginManager;
use App\Services\Navigation\StartPageResolver;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller {
    /**
     * Startseiten-Namen für Plugins, deren `name()` nicht der Produktname ist
     * (Zusatz wie „Issues“, übersetzter Titel, mehrere Anbieter); [] = kein
     * Produkt, etwa selbst gestellte Auskunftsdienste.
     *
     * @var array<string, list<string>>
     */
    public const array PRODUCT_NAMES = [
        'datev-online' => ['DATEV'],
        'dhl' => ['DHL'],
        'fritzbox' => ['FRITZ!Box'],
        'github' => ['GitHub'],
        'gitlab' => ['GitLab'],
        'google_calendar' => ['Google Calendar'],
        'orgamax' => ['orgaMAX'],
        'peppol-access-point' => ['Peppol'],
        'phonedirectory' => [],
        'remote-support' => ['AnyDesk', 'TeamViewer', 'RustDesk', 'Splashtop', 'pcvisit'],
    ];

    public function __invoke(Request $request, StartPageResolver $startPages, ModuleRegistry $modules, PluginManager $plugins): View|RedirectResponse {
        $canViewSensitive = Auth::check();
        $legacyConfigured = filled(config('database.connections.legacy.database'));

        /** @var User|null $user */
        $user = Auth::user();

        // Ohne Session-Modus greift die persistierte Modus-Wahl statt hart 'legacy'
        // (sonst fällt F5/neuer Login in Legacy zurück).
        $default = $user instanceof User ? $user->preferredWorkMode() : 'legacy';
        $currentMode = $request->session()->get('work_mode', $default);

        if ($user instanceof User) {
            // Modus auf einen tatsächlich erlaubten Wert korrigieren.
            if ($currentMode === 'legacy' && ! $user->canAccessLegacy()) {
                $currentMode = 'new';
                $request->session()->put('work_mode', 'new');
            } elseif ($currentMode === 'new' && ! $user->canAccessNew()) {
                $currentMode = 'legacy';
                $request->session()->put('work_mode', 'legacy');
            }
        }

        if (
            $canViewSensitive && $currentMode === 'legacy' && $legacyConfigured
            && (! $user instanceof User || $user->canAccessLegacy())
        ) {
            return redirect()->route('legacy.diary.week');
        }

        if ($canViewSensitive && (! $user instanceof User || $user->canAccessNew())) {
            $start = $user instanceof User ? $startPages->routeFor($user) : null;

            return redirect()->route($start ?? 'dashboard');
        }

        // Ab hier nur noch Gäste: öffentliche Produkt-Landingpage.
        return view('home', [
            'moduleCount' => count(array_filter(
                $modules->all(),
                static fn(Manifest $manifest): bool => $manifest->kind() !== ModuleKind::Platform,
            )),
            'integrations' => $this->integrationGroups($modules, $plugins),
        ]);
    }

    /**
     * Anbindungen der Startseite aus dem Bestand: jedes Plugin erscheint über das Modul
     * seines Manifests (oder ausdrücklich in `plugins`), dazu die fest eingebauten
     * Anbindungen ohne Plugin. Ein Plugin ohne passende Gruppe landet unter
     * „Weitere Anbindungen“, damit es nie fehlt.
     *
     * @return list<array{icon: string, title: string, items: list<string>}>
     */
    private function integrationGroups(ModuleRegistry $modules, PluginManager $plugins): array {
        $groups = [
            ['icon' => 'account_balance', 'title' => __('Buchhaltung, Zahlung & E-Rechnung'), 'modules' => ['finance', 'invoicing'], 'plugins' => [], 'items' => []],
            ['icon' => 'local_shipping', 'title' => __('Handel & Versand'), 'modules' => ['inventory', 'shipping'], 'plugins' => [], 'items' => []],
            ['icon' => 'task_alt', 'title' => __('Zeit, Aufgaben & Tickets'), 'modules' => ['time', 'kanban', 'project', 'helpdesk'], 'plugins' => [], 'items' => []],
            ['icon' => 'cloud', 'title' => __('Kalender, Cloud & Dokumente'), 'modules' => ['calendar', 'contacts', 'backup'], 'plugins' => ['paperless'], 'items' => []],
            ['icon' => 'forum', 'title' => __('Kommunikation, Ortung & IT-Service'), 'modules' => ['cti', 'notification', 'asset', 'domain'], 'plugins' => [], 'items' => ['Microsoft Teams', 'Mattermost', 'IMAP', 'OwnTracks', 'Traccar']],
            ['icon' => 'auto_awesome', 'title' => __('KI & Übersetzung'), 'modules' => [], 'plugins' => [], 'items' => ['OpenAI', 'Anthropic', 'Gemini', 'Azure OpenAI', 'Ollama', 'DeepL', 'Azure Translator', 'LibreTranslate']],
            ['icon' => 'extension', 'title' => __('Weitere Anbindungen'), 'modules' => [], 'plugins' => [], 'items' => []],
        ];
        $fallback = array_key_last($groups);

        foreach ($plugins->all() as $id => $plugin) {
            $module = $modules->byPlugin($id)?->code();
            $target = $fallback;
            foreach ($groups as $index => $group) {
                if (in_array($id, $group['plugins'], true) || ($module !== null && in_array($module, $group['modules'], true))) {
                    $target = $index;
                    break;
                }
            }
            array_push($groups[$target]['items'], ...(self::PRODUCT_NAMES[$id] ?? [$plugin->name()]));
        }

        $result = [];
        foreach ($groups as $group) {
            $items = array_values(array_unique($group['items']));
            if ($items === []) {
                continue;
            }
            natcasesort($items);
            $result[] = ['icon' => $group['icon'], 'title' => $group['title'], 'items' => array_values($items)];
        }

        return $result;
    }

    public function switchMode(Request $request, string $mode): RedirectResponse {
        if (! in_array($mode, ['legacy', 'new'], true)) {
            return back()->with('success', __('Unbekannter Modus.'));
        }

        $legacyConfigured = filled(config('database.connections.legacy.database'));
        if ($mode === 'legacy' && ! $legacyConfigured) {
            $request->session()->put('work_mode', 'new');

            return back()->with('success', __('Legacy-Modus ist nicht verfügbar (Legacy-DB nicht konfiguriert).'));
        }

        /** @var User|null $user */
        $user = $request->user();
        if ($user instanceof User) {
            if ($mode === 'legacy' && ! $user->canAccessLegacy()) {
                return back()->with('error', __('Kein Zugriff auf das Legacy-System.'));
            }
            if ($mode === 'new' && ! $user->canAccessNew()) {
                return back()->with('error', __('Kein Zugriff auf das neue System.'));
            }
        }

        $request->session()->put('work_mode', $mode);

        // Wahl dauerhaft am User merken, damit sie Session-Ablauf/Login/F5 überlebt
        // (siehe EnsureNewSystemAccess / HomeController-Default).
        if ($user instanceof User && $user->getPreference('work_mode') !== $mode) {
            $user->setPreference('work_mode', $mode);
        }

        $origin = (string) $request->input('origin', 'home');
        $targetRoute = $this->resolveModeRoute($origin, $mode);

        return redirect()
            ->route($targetRoute)
            ->with('mode_toast', $mode === 'legacy' ? __('Legacy-Modus aktiviert.') : __('Neuer Modus aktiviert.'));
    }

    private function resolveModeRoute(string $origin, string $mode): string {
        if ($origin === 'home') {
            return 'home';
        }

        return match ($origin) {
            'legacy.diary.index', 'diary.index' => $mode === 'legacy' ? 'legacy.diary.index' : 'diary.index',
            // Ohne verknüpftes Legacy-Konto gibt es im Altsystem nichts anzulegen.
            'legacy.diary.create', 'diary.create' => $mode === 'legacy'
                ? (\App\Legacy\LegacyBridge::hasLegacyAccount($this->authUser()) ? 'legacy.diary.create' : 'legacy.diary.index')
                : 'diary.create',
            default => $mode === 'legacy' ? 'legacy.diary.index' : 'diary.index',
        };
    }
}
