<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiMemoryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Ai;

use App\Enums\Ai\AiMemoryEntryType;
use App\Http\Controllers\Controller;
use App\Models\Ai\AiMemoryEntry;
use App\Models\Customer;
use App\Services\Ai\AiCapabilityRegistry;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Pflege-UI des KI-Gedächtnisses (Feature 025, MVP-401): Glossare,
 * Stil-Regeln und Beispielpaare je Organisation/Kunde/Capability.
 * Einträge sind Fachdaten — auditiert, editierbar, deaktivier- und
 * löschbar; gelernte Einträge sind als solche gekennzeichnet.
 */
class AiMemoryController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', AiMemoryEntry::class);

        $entries = AiMemoryEntry::query()
            ->with('customer:id,name')
            ->when($request->query('kunde') !== null, static fn ($q) => $q->where('customer_id', (int) $request->query('kunde')))
            ->orderByRaw('customer_id IS NULL')
            ->orderBy('customer_id')
            ->orderBy('capability')
            ->orderByDesc('id')
            ->get();

        return view('admin.ai.memory', [
            'entries' => $entries,
            'canManage' => Gate::allows('create', AiMemoryEntry::class),
        ]);
    }

    /** Anlege-Dialog (modal-first). */
    public function create(AiCapabilityRegistry $registry): View {
        Gate::authorize('create', AiMemoryEntry::class);

        return view('admin.ai._memory_dialog', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'capabilities' => $registry->all(),
        ]);
    }

    public function store(Request $request, AiCapabilityRegistry $registry): RedirectResponse {
        Gate::authorize('create', AiMemoryEntry::class);

        // Sqid-Input dekodieren (numerischer Fallback für Alt-Clients).
        if ($request->filled('customer_id')) {
            $request->merge(['customer_id' => Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id'))]);
        }

        $data = $request->validate([
            'entry_type' => ['required', 'string', 'in:' . implode(',', array_column(AiMemoryEntryType::cases(), 'value'))],
            'scope' => ['required', 'string', 'in:organization,customer,capability'],
            'customer_id' => ['nullable', 'integer'],
            'capability' => ['nullable', 'string', 'max:80'],
            'term' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:2000'],
            'source_text' => ['nullable', 'string', 'max:2000'],
            'translation_en' => ['nullable', 'string', 'max:300'],
            'translation_es' => ['nullable', 'string', 'max:300'],
            'translation_fr' => ['nullable', 'string', 'max:300'],
            'translation_it' => ['nullable', 'string', 'max:300'],
        ]);

        $type = AiMemoryEntryType::from($data['entry_type']);

        // Ebenen-Konsistenz: Kunde nur mit gültigem eigenen Kunden,
        // Capability nur mit registriertem Key (Registry = Allowlist).
        $customerId = null;
        $capability = null;
        if ($data['scope'] === 'customer') {
            $customerId = (int) ($data['customer_id'] ?? 0);
            abort_unless(Customer::query()->whereKey($customerId)->exists(), 422);
        } elseif ($data['scope'] === 'capability') {
            $capability = (string) ($data['capability'] ?? '');
            abort_unless($registry->has($capability), 422);
        }

        if ($type === AiMemoryEntryType::Glossary && blank($data['term'] ?? null)) {
            return back()->withInput()->with('error', __('ai.flash.term_required'));
        }
        if ($type === AiMemoryEntryType::Example && blank($data['source_text'] ?? null)) {
            return back()->withInput()->with('error', __('ai.flash.source_required'));
        }

        $translations = array_filter([
            'en' => $data['translation_en'] ?? null,
            'es' => $data['translation_es'] ?? null,
            'fr' => $data['translation_fr'] ?? null,
            'it' => $data['translation_it'] ?? null,
        ], static fn (?string $v): bool => filled($v));

        AiMemoryEntry::create([
            'customer_id' => $customerId,
            'capability' => $capability,
            'entry_type' => $type,
            'term' => $data['term'] ?? null,
            'content' => $data['content'],
            'source_text' => $data['source_text'] ?? null,
            'translations' => $translations === [] ? null : $translations,
            'origin' => AiMemoryEntry::ORIGIN_MANUAL,
            'active' => true,
            'created_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.ai.memory')->with('success', __('ai.flash.memory_saved'));
    }

    public function toggle(AiMemoryEntry $entry): RedirectResponse {
        Gate::authorize('update', $entry);

        $entry->forceFill(['active' => ! $entry->active])->save();

        return back()->with('success', $entry->active ? __('ai.flash.memory_activated') : __('ai.flash.memory_deactivated'));
    }

    public function destroy(AiMemoryEntry $entry): RedirectResponse {
        Gate::authorize('delete', $entry);

        $entry->delete();

        return back()->with('success', __('ai.flash.memory_deleted'));
    }
}
