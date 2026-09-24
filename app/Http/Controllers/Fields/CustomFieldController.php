<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomFieldController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Fields;

use App\Enums\Fields\FieldType;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Services\Fields\CustomFieldService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Eigene Felder je Träger der aktuellen Organisation (MVP-868): Übersicht,
 * Schema-Dialog, Aktivieren/Deaktivieren. Löschen gibt es nicht — Werte
 * können am Schema hängen.
 */
class CustomFieldController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly CustomFieldService $customFields) {}

    public function index(): View {
        Gate::authorize(Permission::OrganizationCustomFieldsManage->value);
        $organization = $this->currentOrganization();
        $rows = [];
        foreach ($this->customFields->subjects() as $alias => $class) {
            $definition = $this->customFields->definition((int) $organization->id, $alias);
            $rows[] = [
                'alias' => $alias,
                'label' => (string) __('entity-types.' . class_basename($class)),
                'definition' => $definition,
                'usage' => $definition !== null ? $this->customFields->usageCount($definition) : 0,
            ];
        }

        return view('admin.custom-fields.index', ['rows' => $rows]);
    }

    public function edit(string $alias): View {
        Gate::authorize(Permission::OrganizationCustomFieldsManage->value);
        $subjects = $this->customFields->subjects();
        abort_unless(isset($subjects[$alias]), 404);
        $definition = $this->customFields->definition((int) $this->currentOrganization()->id, $alias);

        return view('admin.custom-fields._form_dialog', [
            'alias' => $alias,
            'label' => (string) __('entity-types.' . class_basename($subjects[$alias])),
            'definition' => $definition,
            'types' => array_values(array_filter(FieldType::cases(), static fn (FieldType $type): bool => ! $type->storesAttachment())),
            'isDialog' => true,
        ]);
    }

    public function update(Request $request, string $alias): RedirectResponse {
        Gate::authorize(Permission::OrganizationCustomFieldsManage->value);
        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'fields' => ['required', 'array'],
            'fields.*' => ['array'],
            'fields.*.label' => ['nullable', 'string', 'max:160'],
            'fields.*.type' => ['nullable', 'string', 'max:32'],
            'fields.*.required' => ['nullable'],
            'fields.*.listed' => ['nullable'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
            'fields.*.help' => ['nullable', 'string', 'max:500'],
            'fields.*.unit' => ['nullable', 'string', 'max:20'],
            'fields.*.min' => ['nullable', 'numeric'],
            'fields.*.max' => ['nullable', 'numeric'],
            'fields.*.visible_if' => ['nullable', 'array'],
            'fields.*.visible_if.field' => ['nullable', 'string', 'max:160'],
            'fields.*.visible_if.op' => ['nullable', 'string', 'max:16'],
            'fields.*.visible_if.value' => ['nullable', 'string', 'max:500'],
        ]);
        $this->customFields->saveDefinition($this->currentOrganization(), $alias, (array) $data['fields'], $request->boolean('is_active', true));

        return redirect()->route('admin.custom-fields.index')->with('success', __('fields.custom.saved'));
    }

    public function toggle(string $alias): RedirectResponse {
        Gate::authorize(Permission::OrganizationCustomFieldsManage->value);
        $definition = $this->customFields->definition((int) $this->currentOrganization()->id, $alias);
        abort_if($definition === null, 404);
        $this->customFields->setActive($definition, ! $definition->is_active);

        return redirect()->route('admin.custom-fields.index')->with('success', __('fields.custom.toggled'));
    }
}
