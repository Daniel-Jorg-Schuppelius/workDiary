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
 * Hintergrund: ../WorkDiary-Architecture/security/tenant-audit-2026.md
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
     * ../WorkDiary-Architecture/security/tenant-audit-2026.md (Abschnitt „Allow-List").
     *
     * @var array<int, class-string>
     */
    private const ALLOW_LIST = [
        Organization::class,
        User::class,
        UserGroup::class,
        OrganizationAuditLog::class,
        PerDiemRate::class,
        // Feature 070 (D9): globale Fristen-Defaults (org NULL) + Org-Overrides,
        // Auflösung filtert explizit (analog PerDiemRate).
        \App\Models\Crisis\CrisisDeadlineTemplate::class,
        // Feature 071 (D8/P3): globale Faktor-Sets/Matrix (org NULL) + Overrides,
        // Auflösung filtert explizit; Faktoren hängen am Set.
        \App\Models\Sustainability\SustainabilityFactorSet::class,
        \App\Models\Sustainability\SustainabilityEmissionFactor::class,
        \App\Models\Sustainability\SustainabilityFrameMapping::class,
        // Phase 23 (P1): globaler Steuerkatalog (org NULL) + Org-Overrides.
        \App\Models\TaxRule::class,
        // Feature 075 (P1): Prüfprofil-Katalog (org NULL = globale Vorlage,
        // Org-Zeilen überschreiben per Code) + Normen-Referenzmatrix —
        // Auflösung filtert explizit (scopeForOrganization/effectiveProfiles);
        // Anforderungen hängen als Katalog-Kind transitiv am Profil.
        \App\Models\AssetCompliance\AssetComplianceProfile::class,
        \App\Models\AssetCompliance\AssetComplianceRequirement::class,
        \App\Models\AssetCompliance\AssetComplianceNormReference::class,
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
        // SSO-Kontoverknüpfung (Feature 057, MVP-120/121): Kind der mandanten-
        // gebundenen SsoConnection — Mandantengrenze transitiv über
        // sso_connection_id (alle Zugriffe filtern darüber); zusätzlich prüft
        // SsoLoginService die Org des Users gegen die Org der Verbindung.
        \App\Models\SsoIdentity::class,
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
        // Qualifikations-Zuordnung je User (Pivot user_qualifications, Feature
        // 013): transitiv mandantenfähig über user_id → users.organization_id,
        // analog UserBookmark — kein eigenes organization_id-Feld nötig.
        \App\Models\UserQualification::class,
        // System-weiter Backup-Heartbeat (MVP-046 §5): externer Backup-Job postet
        // ohne Tenant-Kontext, gehört bewusst nicht zur Mandantengrenze.
        BackupHeartbeat::class,
        // Verschlüsselte Cloud-Backupziele (Feature 017 Phase 32, MVP-361):
        // Backups sichern die GESAMTE Installation — Verbindungen, Generationen
        // und Teile sind bewusst systemweit (Verwaltung nur Plattform-Admin,
        // Policies hart auf isGlobalAdmin, keine Org-Sicht).
        \App\Models\Backup\BackupTargetConnection::class,
        \App\Models\Backup\BackupGeneration::class,
        \App\Models\Backup\BackupGenerationPart::class,
        // Todoist-Webhook-Dedup (Feature 055, MVP-115): die Zeile entsteht VOR
        // der Org-Zuordnung (Signaturprüfung → erst danach Mapping über
        // todoist_user_id); nullable organization_id, ein Global-Scope würde
        // signierte, aber unzuordenbare Zustellungen ausblenden. Siehe
        // Allow-List im Audit-Doc.
        \App\Models\TodoistWebhookDelivery::class,
        // Restore-Test-Register (Feature 017): plattformweites Protokoll der
        // Wiederherstellungs-Tests — analog BackupHeartbeat findet der
        // Restore-Vorgang ohne Tenant-Kontext statt. Siehe Allow-List im Audit-Doc.
        \App\Models\RestoreTest::class,
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
        // Append-only Event-Hash-Kette des Zahlungsabgleichs (Feature 045,
        // Priorität 3) — analog BillingTransferEvent/Whistleblowing\CaseEvent:
        // nullable organization_id BEWUSST ohne FK und ohne Global-Scope, da die
        // Kette (config('audit.chains')) via `audit:verify` scope-frei über alle
        // Zeilen verifizierbar sein muss und Einträge die Löschung von
        // Umsatz/Org überdauern; payload bewusst ohne PII.
        \App\Models\Finance\PaymentReconciliationEvent::class,
        // Quellnachweis je Buchungssatz eines DATEV-Buchungsstapels (Feature 045,
        // Priorität 2): Kind-Tabelle des tenant-gebundenen DatevBookingBatch —
        // Mandantengrenze transitiv über datev_booking_batches.organization_id
        // (analog BillingTransferItem); Zugriff ausschließlich über den Batch.
        \App\Models\Finance\DatevBookingSource::class,
        // Append-only Event-Hash-Kette des DATEV-Buchungsexports (Feature 045,
        // Priorität 2) — analog BillingTransferEvent/PaymentReconciliationEvent:
        // nullable organization_id BEWUSST ohne FK und ohne Global-Scope, da die
        // Kette (config('audit.chains')) via `audit:verify` scope-frei über alle
        // Zeilen verifizierbar sein muss und Einträge die Löschung von
        // Batch/Org überdauern; payload bewusst ohne PII.
        \App\Models\Finance\DatevBookingEvent::class,
        // Prüfer-Download-Tokens der ISMS-Auditpakete (Feature 046, Inkrement E):
        // Kind-Tabelle des tenant-gebundenen IsmsAuditPackage — Mandantengrenze
        // transitiv über isms_audit_packages.organization_id (analog
        // ProtocolSignatureToken); der öffentliche Prüfer-Download (ohne Login,
        // ohne Org-Session) löst den Token über den SHA-256-Hash auf, ein
        // Global-Scope würde genau diesen Zugriff aushebeln. Interne Aktionen
        // (Widerruf) autorisieren immer über die org-gescopte Paket-Query.
        // Siehe Allow-List im Audit-Doc.
        \App\Models\Isms\IsmsAuditPackageToken::class,
        // Append-only Nachweis externer Aktionen (Feature 033): Kind-Tabelle
        // des tenant-gebundenen ExternalParticipant — Mandantengrenze transitiv
        // über external_participants.organization_id (analog TimeExportLine).
        // Akteur ist der externe Beteiligte (kein interner User), Zugriff
        // ausschließlich über die ExternalParticipant-Relation. Siehe
        // Allow-List im Audit-Doc.
        \App\Models\ExternalParticipantEvent::class,
        // Artikelstamm (MVP-060): Optionsdefinitionen/-werte und alternative
        // Einheiten sind Kind-Tabellen des tenant-gebundenen Article —
        // Mandantengrenze transitiv über articles.organization_id
        // (ArticleOptionValue über Definition → Article; der VariantResolver
        // validiert Optionswerte zusätzlich gegen article_id). Siehe
        // Allow-List im Audit-Doc.
        \App\Models\ArticleOptionDefinition::class,
        \App\Models\ArticleOptionValue::class,
        \App\Models\ArticleUnit::class,
        // Varianten-Stücklisten-Abweichung (MVP-061): Kind-Tabelle des
        // tenant-gebundenen ArticleVariant — Mandantengrenze transitiv über
        // article_variants.organization_id; Auflösung ausschließlich im
        // BomResolver gegen die übergebene Variante.
        \App\Models\ArticleVariantBomOverride::class,
        // Zähl-Zeile einer Inventur (MVP-068/E6): Kind-Tabelle des
        // tenant-gebundenen StockCount — Mandantengrenze transitiv über
        // stock_counts.organization_id (analog TimeExportLine); Zugriff
        // ausschließlich über $count->lines() bzw. den StocktakeService.
        \App\Models\StockCountLine::class,
        // Lieferanten-Katalogpreise und Staffelpreise (E4/Beschaffung):
        // Kind-Tabellen des tenant-gebundenen SupplierCatalogItem —
        // Mandantengrenze transitiv über supplier_catalog_items.organization_id;
        // gelesen/geschrieben nur in Kombination mit dem Katalog-Item.
        \App\Models\SupplierCatalogItemPrice::class,
        \App\Models\SupplierCatalogItemPriceTier::class,
        // Fertigung (MVP-061/062): Material-Snapshot und Rückmeldungen sind
        // Kind-Tabellen des tenant-gebundenen ManufacturingOrder —
        // Mandantengrenze transitiv über manufacturing_orders.organization_id;
        // Zugriff ausschließlich über den Auftrag bzw. dessen Services.
        \App\Models\ManufacturingOrderMaterial::class,
        \App\Models\ManufacturingOrderReport::class,
        // Stücklisten-Positionen und Parameter-Definitionen je Vorlagen-Version
        // (MVP-061): Kind-Tabellen der ProcedureTemplateVersion —
        // Mandantengrenze transitiv über Version → Vorlage →
        // procedure_templates.organization_id (analog ProcedureStepDef);
        // Queries filtern immer auf die Eltern-Version.
        \App\Models\ProcedureMaterialRequirement::class,
        \App\Models\ProcedureParameterDefinition::class,
        // Preis-Snapshot einer LV-Position (Feature 049/GAEB): append-only
        // Kind-Tabelle des tenant-gebundenen BoqItem — Mandantengrenze
        // transitiv über boq_items.organization_id; wird nur beim
        // Import/Reimport über das Item geschrieben.
        \App\Models\BoqItemPriceSnapshot::class,
        // Installationsweite Betriebs-/Systemdaten (Feature 067 + 041,
        // MVP-053–058/173–181): KEIN Mandantenbezug — Settings-Registry-
        // Overrides, Scheduler-Registry (Overrides/Läufe/Zustand),
        // Update-Erkennung und OSV-Sicherheitshinweise gelten je
        // Installation; Zugriff nur über Betreiber-Permissions
        // (platform.*), nie über fachliche Mandanten-Views.
        \App\Models\SystemSetting::class,
        \App\Models\ScheduledJobOverride::class,
        \App\Models\ScheduledJobRun::class,
        \App\Models\ScheduledJobState::class,
        \App\Models\ComponentUpdate::class,
        \App\Models\SecurityAdvisory::class,
        // Betriebsaufgaben + Wartungsfenster (MVP-055/058): haben
        // organization_id (installationsweite Zeilen hängen an der
        // Betreiber-Org, is_system-Flag), aber BEWUSST ohne Global-Scope —
        // Scanner/Watchdog laufen ohne Tenant-Kontext und die Controller
        // filtern explizit auf die aktuelle Organisation (Cross-Org → 404,
        // getestet in OperationsTaskCenterTest/MaintenanceWindowTest).
        \App\Models\OperationsTask::class,
        \App\Models\MaintenanceWindow::class,
        // Quelltext-Integritätsprüfungen (Feature 095) und Sicherheitsereignisse
        // (Feature 096): installationsweite Nachweise ohne Mandantenbezug — die
        // Baseline und die Angriffserkennung gelten je Installation, Zugriff nur
        // über Plattform-Admin (isGlobalAdmin), nie über fachliche Mandanten-Views.
        \App\Models\IntegrityCheck::class,
        \App\Models\SecurityEvent::class,
        // Bekannte Anmelde-Geräte (Feature 096): über user_id (FK cascade) an den
        // User gebunden und damit transitiv mandantenfähig — analog UserBookmark;
        // Zugriff ausschließlich über den eigenen User beim Login.
        \App\Models\UserKnownDevice::class,
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
                . "(siehe ../WorkDiary-Architecture/security/tenant-audit-2026.md):\n - " . implode("\n - ", $offenders),
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
