<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Knowledge;

use App\Enums\CloudIntake\CloudIntakeConnectionStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Communication\CommunicationNote;
use App\Models\Knowledge\{ContentCollection, KnowledgeArticle};
use App\Models\Platform\{Organization, User};
use App\Models\Plugins\Msgraph\MsgraphOneNoteConnection;
use App\Plugins\Contracts\DocumentIntakeSource;
use App\Plugins\Msgraph\Api\MsgraphOneNoteClient;
use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\PluginManager;
use App\Services\Collections\Import\{KnowledgeImportReport, KnowledgeImportService, ObsidianVaultReader, OneNoteNotebookReader};
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Einbahn-Übernahme aus Obsidian und OneNote (MVP-815, Feature 155): Dialoge
 * im Einstieg „Wissen“, Lauf auf Anstoß. Übernahmen legen viele Inhalte auf
 * einmal an und nutzen Anbindungen der Organisation — deshalb nur für
 * Administratoren, die zugleich Sammlungen pflegen dürfen.
 */
class KnowledgeImportController extends Controller {
    use ResolvesCurrentOrganization;

    public const SOURCE_OBSIDIAN = 'obsidian';

    public const SOURCE_ONENOTE = 'onenote';

    public function __construct(private readonly KnowledgeImportService $imports) {}

    public function create(Request $request): View {
        $user = $this->importer();
        $source = $request->query('source') === self::SOURCE_ONENOTE ? self::SOURCE_ONENOTE : self::SOURCE_OBSIDIAN;
        $organization = $this->currentOrganization();

        $notebooks = [];
        $oneNoteError = false;
        if ($source === self::SOURCE_ONENOTE) {
            $connection = $this->oneNoteConnection($organization);
            try {
                $notebooks = $connection !== null ? (new MsgraphOneNoteClient($connection))->notebooks() : [];
            } catch (Throwable) {
                $oneNoteError = true;
            }
        }

        return view('knowledge-hub._import_dialog', [
            'source' => $source,
            'targets' => $this->targets($user),
            'connections' => $source === self::SOURCE_OBSIDIAN ? $this->cloudConnections($organization) : collect(),
            'notebooks' => $notebooks,
            'oneNoteError' => $oneNoteError,
        ]);
    }

    public function storeObsidian(Request $request, ObsidianVaultReader $reader): RedirectResponse {
        $user = $this->importer();
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'connection' => ['required', 'string', 'max:64'],
            'vault_path' => ['nullable', 'string', 'max:500'],
            'target' => ['required', Rule::in($this->targets($user))],
            'title' => ['nullable', 'string', 'max:180'],
        ]);

        $connection = $this->cloudConnections($organization)
            ->firstWhere('id', Sqid::decodeOrNumeric(CloudDocumentConnection::class, (string) $data['connection']));
        abort_if($connection === null, 404);
        $adapter = app(PluginManager::class)->find($connection->provider->pluginId());
        if (! $adapter instanceof DocumentIntakeSource) {
            return back()->with('error', __('collections.import.flash.source_unavailable'));
        }

        $vaultPath = trim((string) ($data['vault_path'] ?? ''));
        $pluginId = $connection->provider->pluginId();
        try {
            $read = $reader->documents($connection, $adapter, $vaultPath, KnowledgeImportService::MAX_DOCUMENTS,
                $this->imports->knownChecker($organization, $pluginId, 'obsidian_note'));
            $report = $this->imports->import($organization, $user, $pluginId, 'obsidian_note',
                $this->rootTitle($data['title'] ?? null, $vaultPath !== '' ? basename($vaultPath) : 'Obsidian'),
                (string) $data['target'], $read['documents'], $read['limited']);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('collections.import.flash.failed'));
        }

        return $this->finished($report);
    }

    public function storeOneNote(Request $request, OneNoteNotebookReader $reader): RedirectResponse {
        $user = $this->importer();
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'notebook' => ['required', 'string', 'max:512'],
            'target' => ['required', Rule::in($this->targets($user))],
            'title' => ['nullable', 'string', 'max:180'],
        ]);

        $connection = $this->oneNoteConnection($organization);
        if ($connection === null) {
            return back()->with('error', __('collections.import.flash.source_unavailable'));
        }

        try {
            $client = new MsgraphOneNoteClient($connection);
            // Notizbuch serverseitig gegen die Liste des Kontos prüfen — keine untergeschobenen IDs.
            $notebook = collect($client->notebooks())->firstWhere('id', (string) $data['notebook']);
            if (! is_array($notebook)) {
                return back()->with('error', __('collections.import.flash.notebook_invalid'));
            }
            $read = $reader->documents($client, $notebook['id'], $notebook['name'], KnowledgeImportService::MAX_DOCUMENTS,
                $this->imports->knownChecker($organization, 'msgraph', 'onenote_page'));
            $report = $this->imports->import($organization, $user, 'msgraph', 'onenote_page',
                $this->rootTitle($data['title'] ?? null, $notebook['name']), (string) $data['target'], $read['documents'], $read['limited']);
            $connection->forceFill(['last_import_at' => now()])->save();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('collections.import.flash.failed'));
        }

        return $this->finished($report);
    }

    private function finished(KnowledgeImportReport $report): RedirectResponse {
        $redirect = redirect()
            ->route('knowledge-hub.index', array_filter(['collection' => $report->root?->sqid]))
            ->with('success', __('collections.import.flash.done', $report->counts()));

        return $report->limited ? $redirect->with('warning', __('collections.import.flash.limited', ['max' => KnowledgeImportService::MAX_DOCUMENTS])) : $redirect;
    }

    private function importer(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin() && Gate::allows('create', ContentCollection::class), 403);

        return $user;
    }

    /** @return list<string> */
    private function targets(User $user): array {
        $targets = [];
        if (Gate::forUser($user)->allows('create', CommunicationNote::class)) {
            $targets[] = KnowledgeImportService::TARGET_NOTE;
        }
        if (app(FeatureFlagResolver::class)->isEnabled('module.knowledge') && Gate::forUser($user)->allows('create', KnowledgeArticle::class)) {
            $targets[] = KnowledgeImportService::TARGET_ARTICLE;
        }
        abort_if($targets === [], 403);

        return $targets;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CloudDocumentConnection> */
    private function cloudConnections(Organization $organization) {
        return CloudDocumentConnection::query()
            ->where('organization_id', $organization->id)
            ->where('status', CloudIntakeConnectionStatus::Active->value)
            ->orderBy('name')
            ->get();
    }

    private function oneNoteConnection(Organization $organization): ?MsgraphOneNoteConnection {
        if (! MsgraphConfig::oneNoteImportEnabled((int) $organization->id)) {
            return null;
        }
        $connection = MsgraphOneNoteConnection::query()->where('organization_id', $organization->id)->first();

        return $connection instanceof MsgraphOneNoteConnection && $connection->isActive() ? $connection : null;
    }

    private function rootTitle(?string $title, string $fallback): string {
        $title = trim((string) $title);

        return $title !== '' ? $title : $fallback;
    }
}
