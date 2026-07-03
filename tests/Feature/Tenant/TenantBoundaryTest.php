<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantBoundaryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Models\{CommunicationNote, Document, Event, FeatureUsageCounter, FormSubmission, FormTemplate, KnowledgeArticle, Milestone, Organization, PerDiemTrip, Project, Task, TimeEntry, Timesheet, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Erweitert die OrganizationIsolationTest-Suite um weitere Kerngeschäftsmodelle.
 * Belegt für jedes Modell, dass eine Eloquent-Default-Query unter
 * Organization A keinen Datensatz aus Organization B sieht.
 *
 * Verbindet sich mit dem Audit unter ../WorkDiary-Architecture/security/tenant-audit-2026.md.
 */
class TenantBoundaryTest extends TestCase {
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $userA;

    private User $userB;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $this->orgA = Organization::factory()->create(['slug' => 'boundary-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'boundary-b']);

        $this->userA = User::factory()->user()->create(['organization_id' => $this->orgA->id]);
        $this->userB = User::factory()->user()->create(['organization_id' => $this->orgB->id]);
    }

    public function test_task_is_not_visible_cross_organization(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $taskB = $this->withOrg($this->orgB, fn() => Task::factory()->for($projectB)->create([
            'created_by' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $taskB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Task::find($taskB->id));
        $this->assertSame(0, Task::query()->count());
    }

    public function test_milestone_is_not_visible_cross_organization(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $milestoneB = $this->withOrg($this->orgB, fn() => Milestone::factory()->for($projectB)->create([
            'created_by' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $milestoneB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Milestone::find($milestoneB->id));
        $this->assertSame(0, Milestone::query()->count());
    }

    public function test_event_is_not_visible_cross_organization(): void {
        $eventB = $this->withOrg($this->orgB, fn() => Event::factory()->create([
            'organization_id' => $this->orgB->id,
            'responsible_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $eventB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Event::find($eventB->id));
        $this->assertSame(0, Event::query()->count());
    }

    public function test_time_entry_is_not_visible_cross_organization(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $entryB = $this->withOrg($this->orgB, fn() => TimeEntry::factory()->for($projectB)->for($this->userB)->create());

        $this->assertSame((int) $this->orgB->id, (int) $entryB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(TimeEntry::find($entryB->id));
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_billing_transfer_is_not_visible_cross_organization(): void {
        $customerB = $this->withOrg($this->orgB, fn() => \App\Models\Customer::factory()->create());
        $transferB = $this->withOrg($this->orgB, fn() => \App\Models\Finance\BillingTransfer::factory()->create([
            'customer_id' => $customerB->id,
            'created_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $transferB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Finance\BillingTransfer::find($transferB->id));
        $this->assertSame(0, \App\Models\Finance\BillingTransfer::query()->count());
    }

    public function test_bank_account_is_not_visible_cross_organization(): void {
        $accountB = $this->withOrg($this->orgB, fn() => \App\Models\Finance\BankAccount::factory()->create());

        $this->assertSame((int) $this->orgB->id, (int) $accountB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Finance\BankAccount::find($accountB->id));
        $this->assertSame(0, \App\Models\Finance\BankAccount::query()->count());
    }

    public function test_bank_statement_is_not_visible_cross_organization(): void {
        $statementB = $this->withOrg($this->orgB, fn() => \App\Models\Finance\BankStatement::factory()->create());

        $this->assertSame((int) $this->orgB->id, (int) $statementB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Finance\BankStatement::find($statementB->id));
        $this->assertSame(0, \App\Models\Finance\BankStatement::query()->count());
    }

    public function test_bank_transaction_is_not_visible_cross_organization(): void {
        $transactionB = $this->withOrg($this->orgB, function () {
            $statement = \App\Models\Finance\BankStatement::factory()->create();

            return \App\Models\Finance\BankTransaction::factory()->create(['bank_statement_id' => $statement->id]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $transactionB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Finance\BankTransaction::find($transactionB->id));
        $this->assertSame(0, \App\Models\Finance\BankTransaction::query()->count());
    }

    public function test_payment_allocation_is_not_visible_cross_organization(): void {
        $allocationB = $this->withOrg($this->orgB, function () {
            $statement = \App\Models\Finance\BankStatement::factory()->create();
            $transaction = \App\Models\Finance\BankTransaction::factory()->create(['bank_statement_id' => $statement->id]);
            $customer = \App\Models\Customer::factory()->create();
            $invoice = \App\Models\Invoice::create([
                'organization_id' => $this->orgB->id,
                'customer_id' => $customer->id,
                'number' => 'RB-1',
                'status' => \App\Models\Invoice::STATUS_ISSUED,
                'type' => \App\Models\Invoice::TYPE_INVOICE,
                'category' => \App\Models\Invoice::CATEGORY_SERVICE,
                'currency' => 'EUR',
                'tax_rate' => '19.00',
                'subtotal' => '100.00',
                'tax_amount' => '19.00',
                'total' => '119.00',
            ]);

            return \App\Models\Finance\PaymentAllocation::factory()->create([
                'bank_transaction_id' => $transaction->id,
                'allocatable_type' => \App\Models\Invoice::class,
                'allocatable_id' => $invoice->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $allocationB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Finance\PaymentAllocation::find($allocationB->id));
        $this->assertSame(0, \App\Models\Finance\PaymentAllocation::query()->count());
    }

    public function test_per_diem_trip_is_not_visible_cross_organization(): void {
        $tripB = $this->withOrg($this->orgB, fn() => PerDiemTrip::factory()->for($this->userB)->create());

        $this->assertSame((int) $this->orgB->id, (int) $tripB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(PerDiemTrip::find($tripB->id));
        $this->assertSame(0, PerDiemTrip::query()->count());
    }

    public function test_timesheet_is_not_visible_cross_organization(): void {
        // Timesheet hat keine Factory – manuell anlegen.
        $timesheetB = $this->withOrg($this->orgB, function () {
            return Timesheet::create([
                'user_id' => $this->userB->id,
                'work_date' => now()->toDateString(),
                'kind' => \App\Enums\Timesheet\TimesheetKind::Project,
                'status' => \App\Enums\Timesheet\TimesheetStatus::Draft,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $timesheetB->organization_id, 'Trait befüllt organization_id');

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Timesheet::find($timesheetB->id));
        $this->assertSame(0, Timesheet::query()->count());
    }

    public function test_communication_note_is_not_visible_cross_organization(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $noteB = $this->withOrg($this->orgB, fn() => CommunicationNote::factory()
            ->for($projectB, 'notable')
            ->create([
                'organization_id' => $this->orgB->id,
                'created_by_user_id' => $this->userB->id,
            ]));

        $this->assertSame((int) $this->orgB->id, (int) $noteB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(CommunicationNote::find($noteB->id));
        $this->assertSame(0, CommunicationNote::query()->count());
    }

    public function test_document_is_not_visible_cross_organization(): void {
        $documentB = $this->withOrg($this->orgB, fn() => Document::factory()->create([
            'organization_id' => $this->orgB->id,
            'created_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $documentB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Document::find($documentB->id));
        $this->assertSame(0, Document::query()->count());
    }

    public function test_knowledge_article_is_not_visible_cross_organization(): void {
        $articleB = $this->withOrg($this->orgB, fn() => KnowledgeArticle::factory()->create([
            'organization_id' => $this->orgB->id,
            'created_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $articleB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(KnowledgeArticle::find($articleB->id));
        $this->assertSame(0, KnowledgeArticle::query()->count());
    }

    public function test_form_template_is_not_visible_cross_organization(): void {
        $templateB = $this->withOrg($this->orgB, fn() => FormTemplate::factory()->create([
            'organization_id' => $this->orgB->id,
            'created_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $templateB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(FormTemplate::find($templateB->id));
        $this->assertSame(0, FormTemplate::query()->count());
    }

    public function test_form_submission_is_not_visible_cross_organization(): void {
        $submissionB = $this->withOrg($this->orgB, fn() => FormSubmission::factory()->create([
            'organization_id' => $this->orgB->id,
            'form_template_id' => FormTemplate::factory()->create([
                'organization_id' => $this->orgB->id,
                'created_by_user_id' => $this->userB->id,
            ])->id,
            'submitted_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $submissionB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(FormSubmission::find($submissionB->id));
        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_isms_risk_is_not_visible_cross_organization(): void {
        $riskB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsRisk::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $riskB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsRisk::find($riskB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsRisk::query()->count());
    }

    public function test_isms_risk_assessment_is_not_visible_cross_organization(): void {
        $assessmentB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsRiskAssessment::factory()->create([
            'organization_id' => $this->orgB->id,
            'isms_risk_id' => \App\Models\Isms\IsmsRisk::factory()->create([
                'organization_id' => $this->orgB->id,
            ])->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $assessmentB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsRiskAssessment::find($assessmentB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsRiskAssessment::query()->count());
    }

    public function test_isms_control_is_not_visible_cross_organization(): void {
        $controlB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsControl::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $controlB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsControl::find($controlB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsControl::query()->count());
    }

    public function test_isms_security_incident_is_not_visible_cross_organization(): void {
        $incidentB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsSecurityIncident::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $incidentB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsSecurityIncident::find($incidentB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsSecurityIncident::query()->count());
    }

    public function test_isms_vulnerability_is_not_visible_cross_organization(): void {
        $vulnerabilityB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsVulnerability::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $vulnerabilityB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsVulnerability::find($vulnerabilityB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsVulnerability::query()->count());
    }

    public function test_isms_supplier_assessment_is_not_visible_cross_organization(): void {
        $assessmentB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsSupplierAssessment::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $assessmentB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsSupplierAssessment::find($assessmentB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsSupplierAssessment::query()->count());
    }

    public function test_isms_advisory_is_not_visible_cross_organization(): void {
        $advisoryB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsAdvisory::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $advisoryB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsAdvisory::find($advisoryB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsAdvisory::query()->count());
    }

    public function test_isms_requirement_is_not_visible_cross_organization(): void {
        $requirementB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsRequirement::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $requirementB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsRequirement::find($requirementB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsRequirement::query()->count());
    }

    public function test_isms_scope_is_not_visible_cross_organization(): void {
        $scopeB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsScope::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $scopeB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsScope::find($scopeB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsScope::query()->count());
    }

    public function test_isms_applicability_statement_is_not_visible_cross_organization(): void {
        $statementB = $this->withOrg($this->orgB, function () {
            $scope = \App\Models\Isms\IsmsScope::factory()->default()->create(['organization_id' => $this->orgB->id]);
            $requirement = \App\Models\Isms\IsmsRequirement::factory()->create(['organization_id' => $this->orgB->id]);

            return \App\Models\Isms\IsmsApplicabilityStatement::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_scope_id' => $scope->id,
                'isms_requirement_id' => $requirement->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $statementB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsApplicabilityStatement::find($statementB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsApplicabilityStatement::query()->count());
    }

    public function test_isms_audit_package_is_not_visible_cross_organization(): void {
        $packageB = $this->withOrg($this->orgB, function () {
            $scope = \App\Models\Isms\IsmsScope::factory()->default()->create(['organization_id' => $this->orgB->id]);

            return \App\Models\Isms\IsmsAuditPackage::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_scope_id' => $scope->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $packageB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsAuditPackage::find($packageB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsAuditPackage::query()->count());
    }

    public function test_isms_norm_status_is_not_visible_cross_organization(): void {
        $statusB = $this->withOrg($this->orgB, function () {
            $scope = \App\Models\Isms\IsmsScope::factory()->default()->create(['organization_id' => $this->orgB->id]);

            return \App\Models\Isms\IsmsNormStatus::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_scope_id' => $scope->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $statusB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsNormStatus::find($statusB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsNormStatus::query()->count());
    }

    public function test_isms_certificate_is_not_visible_cross_organization(): void {
        $certificateB = $this->withOrg($this->orgB, function () {
            $scope = \App\Models\Isms\IsmsScope::factory()->default()->create(['organization_id' => $this->orgB->id]);
            $status = \App\Models\Isms\IsmsNormStatus::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_scope_id' => $scope->id,
            ]);

            return \App\Models\Isms\IsmsCertificate::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_norm_status_id' => $status->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $certificateB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsCertificate::find($certificateB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsCertificate::query()->count());
    }

    public function test_isms_audit_is_not_visible_cross_organization(): void {
        $auditB = $this->withOrg($this->orgB, function () {
            $scope = \App\Models\Isms\IsmsScope::factory()->default()->create(['organization_id' => $this->orgB->id]);

            return \App\Models\Isms\IsmsAudit::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_scope_id' => $scope->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $auditB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsAudit::find($auditB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsAudit::query()->count());
    }

    public function test_isms_audit_finding_is_not_visible_cross_organization(): void {
        $findingB = $this->withOrg($this->orgB, function () {
            $scope = \App\Models\Isms\IsmsScope::factory()->default()->create(['organization_id' => $this->orgB->id]);
            $audit = \App\Models\Isms\IsmsAudit::factory()->inProgress()->create([
                'organization_id' => $this->orgB->id,
                'isms_scope_id' => $scope->id,
            ]);

            return \App\Models\Isms\IsmsAuditFinding::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_audit_id' => $audit->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $findingB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsAuditFinding::find($findingB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsAuditFinding::query()->count());
    }

    public function test_isms_corrective_action_is_not_visible_cross_organization(): void {
        $actionB = $this->withOrg($this->orgB, function () {
            $scope = \App\Models\Isms\IsmsScope::factory()->default()->create(['organization_id' => $this->orgB->id]);
            $audit = \App\Models\Isms\IsmsAudit::factory()->inProgress()->create([
                'organization_id' => $this->orgB->id,
                'isms_scope_id' => $scope->id,
            ]);
            $finding = \App\Models\Isms\IsmsAuditFinding::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_audit_id' => $audit->id,
            ]);

            return \App\Models\Isms\IsmsCorrectiveAction::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_audit_finding_id' => $finding->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $actionB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsCorrectiveAction::find($actionB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsCorrectiveAction::query()->count());
    }

    public function test_isms_management_review_is_not_visible_cross_organization(): void {
        $reviewB = $this->withOrg($this->orgB, function () {
            $scope = \App\Models\Isms\IsmsScope::factory()->default()->create(['organization_id' => $this->orgB->id]);

            return \App\Models\Isms\IsmsManagementReview::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_scope_id' => $scope->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $reviewB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsManagementReview::find($reviewB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsManagementReview::query()->count());
    }

    public function test_isms_software_product_is_not_visible_cross_organization(): void {
        $productB = $this->withOrg($this->orgB, fn() => \App\Models\Isms\IsmsSoftwareProduct::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $productB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsSoftwareProduct::find($productB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsSoftwareProduct::query()->count());
    }

    public function test_isms_software_installation_is_not_visible_cross_organization(): void {
        $installationB = $this->withOrg($this->orgB, function () {
            $product = \App\Models\Isms\IsmsSoftwareProduct::factory()->create(['organization_id' => $this->orgB->id]);

            return \App\Models\Isms\IsmsSoftwareInstallation::factory()->create([
                'organization_id' => $this->orgB->id,
                'isms_software_product_id' => $product->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $installationB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Isms\IsmsSoftwareInstallation::find($installationB->id));
        $this->assertSame(0, \App\Models\Isms\IsmsSoftwareInstallation::query()->count());
    }

    public function test_notification_rule_is_not_visible_cross_organization(): void {
        $ruleB = $this->withOrg($this->orgB, fn() => \App\Models\Notification\NotificationRule::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $ruleB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Notification\NotificationRule::find($ruleB->id));
        $this->assertSame(0, \App\Models\Notification\NotificationRule::query()->count());
    }

    public function test_surcharge_rule_is_not_visible_cross_organization(): void {
        $ruleB = $this->withOrg($this->orgB, fn() => \App\Models\Surcharge\SurchargeRule::factory()->create([
            'organization_id' => $this->orgB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $ruleB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Surcharge\SurchargeRule::find($ruleB->id));
        $this->assertSame(0, \App\Models\Surcharge\SurchargeRule::query()->count());
    }

    public function test_feature_usage_counter_is_not_visible_cross_organization(): void {
        // Telemetry-Light (Feature 036): Zähler sind org-scoped, keine Factory nötig.
        $counterB = $this->withOrg($this->orgB, fn() => FeatureUsageCounter::create([
            'feature' => 'documents.created',
            'period_date' => now()->toDateString(),
            'count' => 1,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $counterB->organization_id, 'Trait befüllt organization_id');

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(FeatureUsageCounter::find($counterB->id));
        $this->assertSame(0, FeatureUsageCounter::query()->count());
    }

    public function test_day_closure_is_not_visible_cross_organization(): void {
        $closureB = $this->withOrg($this->orgB, fn() => \App\Models\DayClosure::factory()->create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $closureB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\DayClosure::find($closureB->id));
        $this->assertSame(0, \App\Models\DayClosure::query()->count());
    }

    public function test_day_correction_request_is_not_visible_cross_organization(): void {
        $requestB = $this->withOrg($this->orgB, function () {
            $closure = \App\Models\DayClosure::factory()->closed()->create([
                'organization_id' => $this->orgB->id,
                'user_id' => $this->userB->id,
            ]);

            return \App\Models\DayCorrectionRequest::factory()->create([
                'organization_id' => $this->orgB->id,
                'day_closure_id' => $closure->id,
                'requested_by_user_id' => $this->userB->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $requestB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\DayCorrectionRequest::find($requestB->id));
        $this->assertSame(0, \App\Models\DayCorrectionRequest::query()->count());
    }

    public function test_sla_violation_is_not_visible_cross_organization(): void {
        $violationB = $this->withOrg($this->orgB, function () {
            $ticket = \App\Models\ServiceTicket::factory()->create(['organization_id' => $this->orgB->id]);

            return \App\Models\SlaViolation::factory()->create([
                'organization_id' => $this->orgB->id,
                'service_ticket_id' => $ticket->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $violationB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\SlaViolation::find($violationB->id));
        $this->assertSame(0, \App\Models\SlaViolation::query()->count());
    }

    public function test_datev_booking_batch_is_not_visible_cross_organization(): void {
        $batchB = $this->withOrg($this->orgB, fn() => \App\Models\Finance\DatevBookingBatch::factory()->create([
            'organization_id' => $this->orgB->id,
            'created_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $batchB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Finance\DatevBookingBatch::find($batchB->id));
        $this->assertSame(0, \App\Models\Finance\DatevBookingBatch::query()->count());
    }

    public function test_asset_assignment_is_not_visible_cross_organization(): void {
        $assetB = $this->withOrg($this->orgB, fn() => \App\Models\Asset::factory()->create(['organization_id' => $this->orgB->id]));
        $assignmentB = $this->withOrg($this->orgB, fn() => \App\Models\AssetAssignment::factory()->create([
            'organization_id' => $this->orgB->id,
            'asset_id' => $assetB->id,
            'assigned_to_user_id' => $this->userB->id,
            'checked_out_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $assignmentB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\AssetAssignment::find($assignmentB->id));
        $this->assertSame(0, \App\Models\AssetAssignment::query()->count());
    }

    public function test_asset_defect_is_not_visible_cross_organization(): void {
        $assetB = $this->withOrg($this->orgB, fn() => \App\Models\Asset::factory()->create(['organization_id' => $this->orgB->id]));
        $defectB = $this->withOrg($this->orgB, fn() => \App\Models\AssetDefect::factory()->create([
            'organization_id' => $this->orgB->id,
            'asset_id' => $assetB->id,
            'reported_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $defectB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\AssetDefect::find($defectB->id));
        $this->assertSame(0, \App\Models\AssetDefect::query()->count());
    }

    public function test_safety_event_is_not_visible_cross_organization(): void {
        $eventB = $this->withOrg($this->orgB, fn() => \App\Models\SafetyEvent::factory()->create([
            'organization_id' => $this->orgB->id,
            'reported_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $eventB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\SafetyEvent::find($eventB->id));
        $this->assertSame(0, \App\Models\SafetyEvent::query()->count());
    }

    public function test_cross_organization_update_is_blocked_by_scope(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $taskB = $this->withOrg($this->orgB, fn() => Task::factory()->for($projectB)->create([
            'created_by' => $this->userB->id,
        ]));

        app()->instance('currentOrganization', $this->orgA);
        // Eine Massen-Update-Query unter Org A darf den Datensatz aus Org B nicht treffen.
        $affected = Task::query()->where('id', $taskB->id)->update(['title' => 'hijacked']);
        $this->assertSame(0, $affected, 'Cross-Org-Update darf keine Zeilen treffen');

        // Originalwert muss erhalten bleiben.
        $reloaded = $this->withOrg($this->orgB, fn() => Task::find($taskB->id));
        $this->assertNotNull($reloaded);
        $this->assertNotSame('hijacked', $reloaded->title);
    }

    public function test_cross_organization_delete_is_blocked_by_scope(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $taskB = $this->withOrg($this->orgB, fn() => Task::factory()->for($projectB)->create([
            'created_by' => $this->userB->id,
        ]));

        app()->instance('currentOrganization', $this->orgA);
        $affected = Task::query()->where('id', $taskB->id)->delete();
        $this->assertSame(0, $affected, 'Cross-Org-Delete darf keine Zeilen treffen');

        $stillThere = $this->withOrg($this->orgB, fn() => Task::find($taskB->id));
        $this->assertNotNull($stillThere, 'Datensatz aus Org B darf nicht gelöscht worden sein');
    }

    public function test_webhook_endpoint_is_not_visible_cross_organization(): void {
        $endpointB = $this->withOrg($this->orgB, fn() => \App\Models\Integration\WebhookEndpoint::factory()->create([
            'organization_id' => $this->orgB->id,
            'created_by_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $endpointB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Integration\WebhookEndpoint::find($endpointB->id));
        $this->assertSame(0, \App\Models\Integration\WebhookEndpoint::query()->count());
    }

    public function test_webhook_delivery_is_not_visible_cross_organization(): void {
        $deliveryB = $this->withOrg($this->orgB, function () {
            $endpoint = \App\Models\Integration\WebhookEndpoint::factory()->create([
                'organization_id' => $this->orgB->id,
                'created_by_user_id' => $this->userB->id,
            ]);

            return \App\Models\Integration\WebhookDelivery::factory()->create([
                'organization_id' => $this->orgB->id,
                'webhook_endpoint_id' => $endpoint->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $deliveryB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\Integration\WebhookDelivery::find($deliveryB->id));
        $this->assertSame(0, \App\Models\Integration\WebhookDelivery::query()->count());
    }

    public function test_vehicle_reservation_is_not_visible_cross_organization(): void {
        $reservationB = $this->withOrg($this->orgB, function () {
            $vehicle = \App\Models\Vehicle::factory()->create(['organization_id' => $this->orgB->id]);

            return \App\Models\VehicleReservation::factory()->create([
                'organization_id' => $this->orgB->id,
                'vehicle_id' => $vehicle->id,
                'reserved_by_user_id' => $this->userB->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $reservationB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\VehicleReservation::find($reservationB->id));
        $this->assertSame(0, \App\Models\VehicleReservation::query()->count());
    }

    public function test_availability_window_is_not_visible_cross_organization(): void {
        $windowB = $this->withOrg($this->orgB, fn() => \App\Models\AvailabilityWindow::factory()->create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $windowB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\AvailabilityWindow::find($windowB->id));
        $this->assertSame(0, \App\Models\AvailabilityWindow::query()->count());
    }

    public function test_desired_shift_is_not_visible_cross_organization(): void {
        $desiredB = $this->withOrg($this->orgB, fn() => \App\Models\DesiredShift::factory()->create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $desiredB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\DesiredShift::find($desiredB->id));
        $this->assertSame(0, \App\Models\DesiredShift::query()->count());
    }

    public function test_shift_exchange_is_not_visible_cross_organization(): void {
        $exchangeB = $this->withOrg($this->orgB, function () {
            $shift = \App\Models\ScheduledShift::factory()->create([
                'organization_id' => $this->orgB->id,
                'user_id' => $this->userB->id,
            ]);

            return \App\Models\ShiftExchange::factory()->create([
                'organization_id' => $this->orgB->id,
                'scheduled_shift_id' => $shift->id,
                'requested_by_user_id' => $this->userB->id,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $exchangeB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(\App\Models\ShiftExchange::find($exchangeB->id));
        $this->assertSame(0, \App\Models\ShiftExchange::query()->count());
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
