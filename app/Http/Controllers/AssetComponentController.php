<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComponentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Article, Asset, AssetComponent, StockSerial};
use App\Services\Asset\AssetComponentService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Anlagen-Stückliste (Feature 118, MVP-607).
 */
class AssetComponentController extends Controller {
    public function __construct(private readonly AssetComponentService $components) {}

    public function index(Asset $asset): View {
        Gate::authorize('view', $asset);

        return view('assets.components.index', [
            'asset' => $asset,
            'installed' => $this->components->installed($asset),
            'history' => $this->components->history($asset),
            'due' => $this->components->dueComponents($asset),
        ]);
    }

    public function form(Asset $asset, ?AssetComponent $component = null): View {
        Gate::authorize('update', $asset);

        return view('assets.components._form_dialog', [
            'asset' => $asset,
            // Nicht 'component': innerhalb einer Blade-Komponente ist
            // $component reserviert (AnonymousComponent) und würde die
            // View-Variable überschreiben.
            'part' => $component,
            'articles' => Article::query()->orderBy('name')->limit(500)->get(['id', 'name', 'number', 'base_unit']),
            // Feature 118: Seriennummern der eigenen Bestandsführung als
            // optionale Verknüpfung; Fremdteile bleiben beim Freitext.
            'serials' => StockSerial::query()
                ->with('article:id,name')
                ->orderByDesc('id')
                ->limit(500)
                ->get(['id', 'article_id', 'serial_no', 'status']),
        ]);
    }

    public function store(Request $request, Asset $asset): RedirectResponse {
        Gate::authorize('update', $asset);
        $data = $this->withSerialNumber($this->validated($request));

        AssetComponent::query()->create($data + [
            'organization_id' => $asset->organization_id,
            'asset_id' => $asset->id,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('status', __('asset.components.saved'));
    }

    /** Teil ersetzen: das alte bleibt mit Ausbaudatum in der Historie. */
    public function replace(Request $request, Asset $asset, AssetComponent $component): RedirectResponse {
        Gate::authorize('update', $asset);
        abort_unless((int) $component->asset_id === (int) $asset->id, 404);

        try {
            $this->components->replace($component, $this->withSerialNumber($this->validated($request)), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('asset.components.replaced'));
    }

    public function remove(Asset $asset, AssetComponent $component): RedirectResponse {
        Gate::authorize('update', $asset);
        abort_unless((int) $component->asset_id === (int) $asset->id, 404);

        $component->forceFill([
            'status' => AssetComponent::STATUS_REMOVED,
            'removed_on' => now()->toDateString(),
        ])->save();

        return back()->with('status', __('asset.components.removed'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $request->merge([
            'article_id' => $request->filled('article_id')
                ? Sqid::decodeOrNumeric(Article::class, (string) $request->input('article_id'))
                : null,
            'stock_serial_id' => $request->filled('stock_serial_id')
                ? Sqid::decodeOrNumeric(StockSerial::class, (string) $request->input('stock_serial_id'))
                : null,
        ]);

        return $request->validate([
            'article_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('articles')],
            'label' => ['nullable', 'string', 'max:191', 'required_without:article_id'],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
            'unit' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'string', 'max:120'],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'stock_serial_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('stock_serials')],
            'installed_on' => ['nullable', 'date'],
            'replace_interval_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * Ist eine Lager-Seriennummer verknüpft, gewinnt ihre Nummer über den
     * Freitext (Feature 118) — zwei verschiedene Nummern an einem Teil wären
     * schlimmer als eine fehlende.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSerialNumber(array $data): array {
        $serialId = $data['stock_serial_id'] ?? null;
        if ($serialId === null) {
            return $data;
        }

        $serial = StockSerial::query()->whereKey($serialId)->first();
        if ($serial instanceof StockSerial) {
            $data['serial_no'] = $serial->serial_no;
        }

        return $data;
    }
}
