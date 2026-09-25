<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Protocol;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Protocol\SaveProtocolTemplateRequest;
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Protocol\{Protocol, ProtocolTemplate};
use App\Services\Protocol\ProtocolTemplateService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Protokollvorlagen (MVP-901): entstehen aus einem ausgefüllten Muster-
 * protokoll („Als Vorlage speichern“), hier werden Name, Zuordnung,
 * Gültigkeit und Aktivierung gepflegt.
 */
class ProtocolTemplateController extends Controller {
    public function __construct(private readonly ProtocolTemplateService $templates) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::ProtocolTemplateManage->value);

        $q = trim($request->string('q')->toString());
        $query = ProtocolTemplate::query()->with(['entryType:id,label', 'customer:id,name']);
        if ($q !== '') {
            $query->whereLikeEscaped('name', $q);
        }

        return view('protocol-templates.index', [
            'templates' => $query->orderBy('name')->paginate(25)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function edit(ProtocolTemplate $template): View {
        Gate::authorize(Permission::ProtocolTemplateManage->value);

        return view('protocol-templates._form_dialog', $this->formData() + ['template' => $template]);
    }

    public function update(SaveProtocolTemplateRequest $request, ProtocolTemplate $template): RedirectResponse {
        Gate::authorize(Permission::ProtocolTemplateManage->value);

        $data = $request->validated();
        $template->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'entry_type_id' => $data['entry_type_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()->toList('protocol-templates.index')->with('success', __('protocol.template.flash.updated'));
    }

    public function destroy(ProtocolTemplate $template): RedirectResponse {
        Gate::authorize(Permission::ProtocolTemplateManage->value);

        $template->delete();

        return redirect()->toList('protocol-templates.index')->with('success', __('protocol.template.flash.deleted'));
    }

    /** Dialog „Als Vorlage speichern“ am Protokoll; mit Vorlage auch „Vorlage aktualisieren“. */
    public function fromProtocolForm(Protocol $protocol): View {
        Gate::authorize(Permission::ProtocolTemplateManage->value);
        Gate::authorize('view', $protocol);

        return view('protocols._template_dialog', $this->formData() + [
            'protocol' => $protocol,
            'existing' => $protocol->template,
        ]);
    }

    public function fromProtocol(SaveProtocolTemplateRequest $request, Protocol $protocol): RedirectResponse {
        Gate::authorize(Permission::ProtocolTemplateManage->value);
        Gate::authorize('view', $protocol);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $existing = $request->boolean('update_existing') ? $protocol->template : null;
        $template = $this->templates->fromProtocol($protocol, [
            'name' => (string) $data['name'],
            'entry_type_id' => isset($data['entry_type_id']) ? (int) $data['entry_type_id'] : null,
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'valid_from' => isset($data['valid_from']) ? (string) $data['valid_from'] : null,
            'valid_until' => isset($data['valid_until']) ? (string) $data['valid_until'] : null,
        ], $actor, $existing);

        return redirect()->route('protocols.show', $protocol)->with('success', __(
            $existing !== null ? 'protocol.template.flash.versioned' : 'protocol.template.flash.created',
            ['name' => $template->name, 'version' => $template->version],
        ));
    }

    /** @return array{entryTypes: \Illuminate\Support\Collection<int, EntryType>, customers: \Illuminate\Support\Collection<int, Customer>} */
    private function formData(): array {
        return [
            'entryTypes' => EntryType::query()->orderBy('label')->get(['id', 'label']),
            'customers' => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']),
        ];
    }
}
