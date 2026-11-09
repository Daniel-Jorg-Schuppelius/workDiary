<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalSubscriptionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Timekeeping;

use App\Enums\Import\ImportEntity;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timekeeping\SaveIcalSubscriptionRequest;
use App\Models\Platform\User;
use App\Models\Time\IcalSubscription;
use App\Services\Timekeeping\IcalSubscriptionSync;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Kalender-Abos der Zeiterfassung (Feature 094, MVP-1516). Wer eine Entität
 * per Datei importieren darf, darf sie auch abonnieren.
 */
class IcalSubscriptionController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        $entities = $this->permittedEntities();
        abort_if($entities === [], 403);

        $subscriptions = IcalSubscription::query()
            ->whereIn('import_entity', array_map(static fn (ImportEntity $e): string => $e->value, $entities))
            ->with('user:id,name')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.imports.ical-subscriptions.index', [
            'subscriptions' => $subscriptions,
            'lookbackDays' => IcalSubscriptionSync::LOOKBACK_DAYS,
        ]);
    }

    public function create(): View {
        $entities = $this->permittedEntities();
        abort_if($entities === [], 403);

        return view('admin.imports.ical-subscriptions._form_dialog', [
            'subscription' => (new IcalSubscription)->forceFill(['is_active' => true, 'import_entity' => $entities[0]]),
            'entities' => $entities,
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveIcalSubscriptionRequest $request): RedirectResponse {
        $data = $this->validatedData($request);
        $this->authorizeEntity($data['import_entity']);

        $subscription = new IcalSubscription;
        $subscription->forceFill($data + [
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => $this->authUser()->id,
            'updated_by' => $this->authUser()->id,
        ])->save();

        return redirect()->toList('admin.imports.ical-subscriptions.index')
            ->with('success', __('import.ical_subscription.flash_created'));
    }

    public function edit(IcalSubscription $icalSubscription): View {
        $this->authorizeEntity($icalSubscription->import_entity);

        return view('admin.imports.ical-subscriptions._form_dialog', [
            'subscription' => $icalSubscription,
            'entities' => $this->permittedEntities(),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(SaveIcalSubscriptionRequest $request, IcalSubscription $icalSubscription): RedirectResponse {
        $this->authorizeEntity($icalSubscription->import_entity);
        $data = $this->validatedData($request);
        $this->authorizeEntity($data['import_entity']);

        if ($data['url'] === null) {
            unset($data['url']); // leer = gespeicherte Adresse behalten
        } elseif ($data['url'] !== $icalSubscription->url) {
            // Neue Quelle: Bedingungen des alten Abrufs gelten nicht mehr.
            $data += ['http_etag' => null, 'http_last_modified' => null];
        }

        $icalSubscription->forceFill($data + ['updated_by' => $this->authUser()->id])->save();

        return redirect()->toList('admin.imports.ical-subscriptions.index')
            ->with('success', __('import.ical_subscription.flash_updated'));
    }

    public function destroy(IcalSubscription $icalSubscription): RedirectResponse {
        $this->authorizeEntity($icalSubscription->import_entity);
        $icalSubscription->delete();

        return redirect()->toList('admin.imports.ical-subscriptions.index')
            ->with('success', __('import.ical_subscription.flash_deleted'));
    }

    public function sync(IcalSubscription $icalSubscription, IcalSubscriptionSync $sync): RedirectResponse {
        $this->authorizeEntity($icalSubscription->import_entity);

        $counts = $sync->sync($icalSubscription);
        $icalSubscription->refresh();

        if ($icalSubscription->last_status === \App\Enums\Import\IcalSyncStatus::Failed) {
            return redirect()->toList('admin.imports.ical-subscriptions.index')
                ->with('error', __('import.ical_subscription.flash_failed', ['error' => (string) $icalSubscription->last_error]));
        }

        return redirect()->toList('admin.imports.ical-subscriptions.index')
            ->with('success', __('import.ical_subscription.flash_synced', $counts));
    }

    /** @return array{name: string, import_entity: ImportEntity, url: ?string, user_id: ?int, category_allowlist: ?list<string>, allow_private_network: bool, is_active: bool} */
    private function validatedData(SaveIcalSubscriptionRequest $request): array {
        $data = $request->validated();

        return [
            'name' => (string) $data['name'],
            'import_entity' => ImportEntity::from((string) $data['import_entity']),
            'url' => isset($data['url']) && $data['url'] !== '' ? (string) $data['url'] : null,
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'category_allowlist' => $data['category_allowlist'] ?? null,
            'allow_private_network' => $request->boolean('allow_private_network'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** @return list<ImportEntity> */
    private function permittedEntities(): array {
        $user = $this->authUser();

        return array_values(array_filter(
            [ImportEntity::ProjectTimes, ImportEntity::Attendances],
            static fn (ImportEntity $entity): bool => $user->can($entity->permission()),
        ));
    }

    private function authorizeEntity(ImportEntity $entity): void {
        abort_unless($this->authUser()->can($entity->permission()), 403);
    }
}
