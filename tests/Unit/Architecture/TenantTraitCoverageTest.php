<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantTraitCoverageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Models\{BackupHeartbeat, Classification, GeocodeCache, HelpTopic, HelpView, ImportRunError, LicenseFlagOverride, MonthClosureEvent, OpenIssueEvent, Organization, OrganizationAuditLog, PerDiemRate, PluginError, PluginState, ProcedureBackupProof, ProcedureRunEvent, ProcedureStepDef, ProcedureStepRun, ProcedureTemplateVersion, ProtocolEvent, ProtocolItem, ProtocolItemPhoto, ProtocolSignature, ProtocolSignatureToken, TimeCorrectionItem, TimeExportEvent, TimeExportLine, User, UserBookmark, UserDashboardWidget, UserFilterPreset, UserGroup};
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Architektur-Gate für Mandantenfähigkeit (MVP-001 / Issue #1):
 * Jede Eloquent-Model-Klasse unter app/Models/ muss
 *   - entweder den Trait App\Models\Concerns\BelongsToOrganization nutzen,
 *   - oder explizit in der Allow-List unten geführt sein.
 *
 * Hintergrund: docs/security/tenant-audit-2026.md
 *
 * Schlägt dieser Test bei einem neuen Modell fehl, ist das ein Hinweis,
 * dass die Mandantengrenze für dieses Modell entschieden werden muss
 * (Trait einbauen oder bewusst in die Allow-List eintragen — letzteres
 * mit Audit-Eintrag begründen).
 */
class TenantTraitCoverageTest extends TestCase {
    /**
     * Modelle, die bewusst KEIN BelongsToOrganization-Trait nutzen.
     * Erweiterungen brauchen einen Audit-Eintrag in
     * docs/security/tenant-audit-2026.md (Abschnitt „Allow-List").
     *
     * @var array<int, class-string>
     */
    private const ALLOW_LIST = [
        Organization::class,
        User::class,
        UserGroup::class,
        OrganizationAuditLog::class,
        PerDiemRate::class,
        GeocodeCache::class,
        OpenIssueEvent::class,
        ProtocolItem::class,
        ProtocolSignature::class,
        ProtocolEvent::class,
        ProtocolSignatureToken::class,
        ProtocolItemPhoto::class,
        ProcedureTemplateVersion::class,
        ProcedureStepDef::class,
        ProcedureStepRun::class,
        ProcedureRunEvent::class,
        ProcedureBackupProof::class,
        Classification::class,
        // Child-Entitäten von mandantengebundenen Aggregaten — Mandantengrenze
        // wird transitiv über das Parent-Modell (TimeExport, TimeCorrection,
        // MonthClosure, ImportRun) durchgesetzt; eigene organization_id wäre redundant.
        TimeExportLine::class,
        TimeExportEvent::class,
        TimeCorrectionItem::class,
        MonthClosureEvent::class,
        ImportRunError::class,
        // Globale Hilfe-Inhalte (HelpTopic) und anonyme Hilfe-Telemetrie (HelpView,
        // nullable organization_id) gehören bewusst nicht zur Mandantengrenze.
        HelpTopic::class,
        HelpView::class,
        // Persönliche User-Daten (Lesezeichen, Dashboard-Widget-Konfiguration) sind
        // bereits über user_id an den User gebunden und damit transitiv
        // mandantenfähig — kein eigenes organization_id-Feld nötig.
        UserBookmark::class,
        UserDashboardWidget::class,
        UserFilterPreset::class,
        // System-weiter Backup-Heartbeat (MVP-046 §5): externer Backup-Job postet
        // ohne Tenant-Kontext, gehört bewusst nicht zur Mandantengrenze.
        BackupHeartbeat::class,
        // Plugin-Lifecycle (Installation/Schema/Health) und Plugin-Fehlerinbox sind
        // systemweit (instance-wide) — Plugins werden global installiert, per-Mandanten-
        // Aktivierung erfolgt über PluginSetting. PluginState/PluginError dienen Admin-
        // Inbox & Auto-Disable und gehören bewusst nicht zur Mandantengrenze.
        PluginState::class,
        PluginError::class,
        // Lizenz-Flag-Overrides (MVP-047 Option A): nullable organization_id —
        // ein Eintrag ohne organization_id deaktiviert den Flag plattformweit.
        // Eine globale Scope-Bindung via BelongsToOrganization würde genau
        // diese Plattform-Einträge ausblenden.
        LicenseFlagOverride::class,
        // Eurostat-Mindestlohn-Referenz: länderweite, mandantenübergreifende
        // Vergleichsdaten (kein Org-Bezug). Der org-spezifische Mindestlohn
        // liegt separat in MinimumWage (tenant-scoped).
        \App\Models\MinimumWageReference::class,
        // Chat: Child-Entitäten von Message/Poll — Mandantengrenze wird transitiv
        // über das tenant-gebundene Parent (Channel/Message, beide mit
        // BelongsToOrganization) durchgesetzt; eigene organization_id wäre redundant.
        \App\Models\Chat\MessageReaction::class,
        \App\Models\Chat\Poll::class,
        \App\Models\Chat\PollOption::class,
        \App\Models\Chat\PollVote::class,
        \App\Models\Chat\Reminder::class,
        \App\Models\Chat\ScheduledMessage::class,
        // Beteiligte einer Kommunikationsnotiz (MVP-012) — Mandantengrenze
        // transitiv über die tenant-gebundene CommunicationNote (eigene
        // organization_id wäre redundant). Siehe Allow-List im Audit-Doc.
        \App\Models\CommunicationNoteParticipant::class,
        // Datei-Version eines Dokuments (MVP-031) — append-only Kind-Tabelle,
        // Mandantengrenze transitiv über das tenant-gebundene Document
        // (documents.organization_id). Siehe Allow-List im Audit-Doc.
        \App\Models\DocumentVersion::class,
        // Wissensbasis (Feature 011): Verknüpfungen und Feedback sind
        // Kind-Tabellen des tenant-gebundenen KnowledgeArticle —
        // Mandantengrenze transitiv (knowledge_articles.organization_id),
        // Controller bindet Links nur in Kombination mit dem Artikel.
        // Siehe Allow-List im Audit-Doc.
        \App\Models\KnowledgeArticleLink::class,
        \App\Models\KnowledgeArticleFeedback::class,
        // Append-only Event-Hash-Ketten (Hinweisgeber-/Datenschutzmodul) —
        // analog OrganizationAuditLog: nullable organization_id BEWUSST ohne
        // FK und ohne Global-Scope, da die Ketten (config('audit.chains'))
        // via `audit:verify` scope-frei über alle Zeilen verifizierbar sein
        // müssen und Einträge die Löschung von Fall/Org überdauern
        // (organization_id geht in den Hash ein, ein Cascade würde die
        // Kette zerreißen). Siehe Allow-List im Audit-Doc.
        \App\Models\Whistleblowing\CaseEvent::class,
        \App\Models\Privacy\RequestEvent::class,
        \App\Models\Privacy\IncidentEvent::class,
        // Tombstone-Ledger des Hinweisgebermoduls: Minimalnachweis OHNE
        // Meldeinhalte, überlebt die Fall-Löschung (nullable organization_id
        // ohne FK) und wird beim Backup-Restore wieder angewandt, um nach
        // dem Backup gelöschte Fälle erneut zu sperren — ein Global-Scope
        // würde genau diese Restore-/Nachweis-Funktion aushebeln.
        \App\Models\Whistleblowing\CaseTombstone::class,
        // Downgrade-/Karenz-Ledger des Lizenz-Layers (plan_module_grace):
        // hat organization_id (FK + Unique je Org+Modul), wird aber in
        // Command-/Gate-Kontexten (plans:purge, PlanModuleService) ohne
        // Tenant-Kontext orgübergreifend gelesen und immer explizit nach
        // organization_id gefiltert — bewusst ohne Global-Scope.
        \App\Models\PlanModuleGrace::class,
        // Zweiter Faktor eines Users (TOTP/E-Mail/WebAuthn): über user_id
        // (FK cascade) an den User gebunden und damit transitiv
        // mandantenfähig — analog UserBookmark; Zugriff ausschließlich über
        // $user->twoFactorCredentials() bzw. mit explizitem Ownership-Check.
        \App\Models\Auth\TwoFactorCredential::class,
        // Quellnachweis-Positionen eines Übergabenachweises (Feature 045):
        // Kind-Tabelle des tenant-gebundenen BillingTransfer — Mandantengrenze
        // transitiv über billing_transfers.organization_id (analog
        // TimeExportLine); Zugriff ausschließlich über den BillingTransfer.
        \App\Models\Finance\BillingTransferItem::class,
        // Append-only Event-Hash-Kette der Finanzschnittstelle (Feature 045) —
        // analog Whistleblowing\CaseEvent: nullable organization_id BEWUSST
        // ohne FK und ohne Global-Scope, da die Kette (config('audit.chains'))
        // via `audit:verify` scope-frei über alle Zeilen verifizierbar sein
        // muss und Einträge die Löschung von Transfer/Org überdauern.
        \App\Models\Finance\BillingTransferEvent::class,
    ];

    public function test_every_model_uses_tenant_trait_or_is_allow_listed(): void {
        $modelsDir = realpath(__DIR__ . '/../../../app/Models');
        $this->assertNotFalse($modelsDir, 'app/Models darf nicht fehlen');

        $offenders = [];

        foreach ($this->iterateModelFiles($modelsDir) as $file) {
            $class = $this->classFromFile($file, $modelsDir);
            if ($class === null) {
                continue;
            }

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            // Abstrakte Klassen, Interfaces, Traits überspringen.
            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            // Legacy-Modelle sind über separate DB-Connection isoliert
            // und Teil des Legacy-Schutzkonzepts (siehe Audit-Doku).
            if (str_starts_with($reflection->getNamespaceName(), 'App\\Models\\Legacy')) {
                continue;
            }

            // Nur Klassen prüfen, die tatsächlich auf Eloquent\Model basieren.
            if (! $reflection->isSubclassOf(Model::class) && $reflection->getName() !== Model::class) {
                // User extends Authenticatable extends Model — daher trotzdem prüfen.
                if (! $this->isAuthenticatableModel($reflection)) {
                    continue;
                }
            }

            if (in_array($class, self::ALLOW_LIST, true)) {
                continue;
            }

            if (! $this->usesTraitRecursive($reflection, BelongsToOrganization::class)) {
                $offenders[] = $class;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Folgende Modelle nutzen weder BelongsToOrganization noch sind sie in der Allow-List '
                . "(siehe docs/security/tenant-audit-2026.md):\n - " . implode("\n - ", $offenders),
        );
    }

    /**
     * @return iterable<string>
     */
    private function iterateModelFiles(string $modelsDir): iterable {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modelsDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (! $fileInfo->isFile()) {
                continue;
            }
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $path = (string) $fileInfo->getRealPath();
            // Concerns/, Scopes/, Contracts/ enthalten keine Models.
            if (preg_match('#/Models/(Concerns|Scopes|Contracts)/#', $path) === 1) {
                continue;
            }
            yield $path;
        }
    }

    private function classFromFile(string $file, string $modelsDir): ?string {
        $relative = ltrim(str_replace($modelsDir, '', $file), DIRECTORY_SEPARATOR);
        $withoutExt = preg_replace('/\.php$/', '', $relative);
        if ($withoutExt === null) {
            return null;
        }
        $class = 'App\\Models\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $withoutExt);

        return $class;
    }

    private function isAuthenticatableModel(ReflectionClass $reflection): bool {
        $parent = $reflection->getParentClass();
        while ($parent !== false) {
            if ($parent->getName() === Model::class) {
                return true;
            }
            $parent = $parent->getParentClass();
        }

        return false;
    }

    /**
     * @param  class-string  $traitFqcn
     */
    private function usesTraitRecursive(ReflectionClass $reflection, string $traitFqcn): bool {
        $current = $reflection;
        while ($current !== false) {
            if (in_array($traitFqcn, $this->collectTraitsRecursive($current), true)) {
                return true;
            }
            $current = $current->getParentClass();
        }

        return false;
    }

    /**
     * @return array<int, class-string>
     */
    private function collectTraitsRecursive(ReflectionClass $reflection): array {
        $traits = $reflection->getTraitNames();
        foreach ($reflection->getTraits() as $trait) {
            $traits = array_merge($traits, $this->collectTraitsRecursive($trait));
        }

        return array_values(array_unique($traits));
    }
}
