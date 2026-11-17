<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Form\Demo;

use App\Models\DocumentDesign\DocumentRenderSnapshot;
use App\Models\Form\{FormSubmission, FormTemplate};
use App\Models\Platform\Organization;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Fields\FieldValues;
use App\Services\Form\FormService;
use App\Support\MorphMap;

/**
 * Formulare zur Vorführung (MVP-1532): ein Einsatzbericht und eine
 * Gefährdungsbeurteilung (aktiv, mit Einreichungen am Hauptauftrag) und ein
 * Entwurf. Felder und Werte laufen über `App\Services\Fields` wie im Dialog.
 */
final class FormDemoBlock implements DemoBlock {
    public function __construct(private readonly FormService $forms) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        $operatorA = $context->users->get(1);
        $operatorB = $context->users->get(2);
        if (! $context->moduleActive('module.forms') || $admin === null || $operatorA === null || $operatorB === null) {
            return ['form_templates' => 0, 'form_submissions' => 0];
        }

        $result = [
            'done' => (string) __('demo.forms.report.result_done'),
            'follow_up' => (string) __('demo.forms.report.result_follow_up'),
            'material' => (string) __('demo.forms.report.result_material'),
        ];
        $report = $this->forms->createTemplate($admin, [
            'name' => (string) __('demo.forms.report.name'),
            'description' => (string) __('demo.forms.report.description'),
            'fields' => [
                ['key' => 'work_done', 'label' => (string) __('demo.forms.report.work_done'), 'type' => 'textarea', 'required' => true, 'listed' => true],
                ['key' => 'hours_on_site', 'label' => (string) __('demo.forms.report.hours_on_site'), 'type' => 'number', 'unit' => 'h', 'min' => 0, 'max' => 24, 'required' => true],
                ['key' => 'result', 'label' => (string) __('demo.forms.report.result'), 'type' => 'choice', 'options' => array_values($result), 'required' => true, 'listed' => true],
                ['key' => 'briefed', 'label' => (string) __('demo.forms.report.briefed'), 'type' => 'boolean'],
                ['key' => 'satisfaction', 'label' => (string) __('demo.forms.report.satisfaction'), 'type' => 'scale', 'min' => 1, 'max' => 5],
            ],
        ]);
        $this->forms->activate($report, $admin);

        $safety = $this->forms->createTemplate($admin, [
            'name' => (string) __('demo.forms.safety.name'),
            'description' => (string) __('demo.forms.safety.description'),
            'fields' => [
                ['key' => 'ppe_complete', 'label' => (string) __('demo.forms.safety.ppe_complete'), 'type' => 'boolean', 'listed' => true],
                ['key' => 'area_secured', 'label' => (string) __('demo.forms.safety.area_secured'), 'type' => 'boolean', 'listed' => true],
                ['key' => 'hazards', 'label' => (string) __('demo.forms.safety.hazards'), 'type' => 'textarea'],
            ],
        ]);
        $this->forms->activate($safety, $admin);

        $this->forms->createTemplate($admin, [
            'name' => (string) __('demo.forms.handover.name'),
            'description' => (string) __('demo.forms.handover.description'),
            'fields' => [
                ['key' => 'open_tasks', 'label' => (string) __('demo.forms.handover.open_tasks'), 'type' => 'textarea', 'required' => true],
                ['key' => 'contact', 'label' => (string) __('demo.forms.handover.contact'), 'type' => 'text'],
            ],
        ]);

        // Langer Bogen (Feature 203): vier Abschnitte, Knöpfe statt Auswahlliste,
        // Ergänzung zur Einschätzung; ein angefangener Entwurf am Hauptauftrag.
        $channels = [
            (string) __('demo.forms.intake.channel_phone'),
            (string) __('demo.forms.intake.channel_mail'),
            (string) __('demo.forms.intake.channel_visit'),
        ];
        $intake = $this->forms->createTemplate($admin, [
            'name' => (string) __('demo.forms.intake.name'),
            'description' => (string) __('demo.forms.intake.description'),
            'fields' => [
                ['key' => 'section_contact', 'label' => (string) __('demo.forms.intake.section_contact'), 'type' => 'section'],
                ['key' => 'person', 'label' => (string) __('demo.forms.intake.person'), 'type' => 'text', 'required' => true],
                ['key' => 'channel', 'label' => (string) __('demo.forms.intake.channel'), 'type' => 'choice', 'options' => $channels],
                ['key' => 'section_request', 'label' => (string) __('demo.forms.intake.section_request'), 'type' => 'section'],
                ['key' => 'request', 'label' => (string) __('demo.forms.intake.request'), 'type' => 'textarea', 'required' => true],
                ['key' => 'urgent', 'label' => (string) __('demo.forms.intake.urgent'), 'type' => 'boolean'],
                ['key' => 'section_risk', 'label' => (string) __('demo.forms.intake.section_risk'), 'type' => 'section'],
                ['key' => 'risk', 'label' => (string) __('demo.forms.intake.risk'), 'type' => 'choice', 'required' => true, 'allows_note' => true, 'options' => [
                    (string) __('demo.forms.intake.risk_low'),
                    (string) __('demo.forms.intake.risk_medium'),
                    (string) __('demo.forms.intake.risk_high'),
                ]],
                ['key' => 'documents_checked', 'label' => (string) __('demo.forms.intake.documents_checked'), 'type' => 'boolean'],
                ['key' => 'section_close', 'label' => (string) __('demo.forms.intake.section_close'), 'type' => 'section'],
                ['key' => 'next_step', 'label' => (string) __('demo.forms.intake.next_step'), 'type' => 'text'],
                ['key' => 'follow_up', 'label' => (string) __('demo.forms.intake.follow_up'), 'type' => 'date'],
            ],
        ]);
        $this->forms->activate($intake, $admin);
        $draft = $this->forms->startDraft($intake, $context->mainDiary, $operatorA);
        $draft->storeFieldAutosave(FieldValues::fromArray([
            'person' => (string) __('demo.forms.intake.person_value'),
            'channel' => $channels[0],
            'request' => (string) __('demo.forms.intake.request_value'),
        ]), $draft->version + 1);

        /** @var array{title: string, content: string} $main */
        $main = $context->blueprint['main_case'];
        $submissions = 0;
        $this->forms->submit($safety, $context->mainDiary, [
            'ppe_complete' => true,
            'area_secured' => true,
            'hazards' => (string) __('demo.forms.safety.hazards_value'),
        ], $operatorA);
        $submissions++;
        $this->forms->submit($report, $context->mainDiary, [
            'work_done' => $main['content'],
            'hours_on_site' => 5.5,
            'result' => $result['follow_up'],
            'briefed' => true,
            'satisfaction' => 4,
        ], $operatorA);
        $submissions++;
        $this->forms->submit($report, null, [
            'work_done' => (string) __('demo.forms.report.work_done_value'),
            'hours_on_site' => 2,
            'result' => $result['done'],
            'briefed' => true,
            'satisfaction' => 5,
        ], $operatorB);
        $submissions++;

        return ['form_templates' => 4, 'form_submissions' => $submissions];
    }

    public function purge(Organization $organization): void {
        $submissionIds = FormSubmission::query()->withoutGlobalScopes()->withTrashed()->where('organization_id', $organization->id)->pluck('id');
        // Demo-Mandant (is_demo): der eingefrorene Designstand der Demo-Einreichungen ist kein Nachweis.
        DocumentRenderSnapshot::query()->withoutGlobalScopes()
            ->where('documentable_type', MorphMap::alias(FormSubmission::class))
            ->whereIn('documentable_id', $submissionIds)
            ->delete();
        FormSubmission::query()->withoutGlobalScopes()->withTrashed()->whereKey($submissionIds)->forceDelete();
        FormTemplate::query()->withoutGlobalScopes()->withTrashed()->where('organization_id', $organization->id)->forceDelete();
    }
}
