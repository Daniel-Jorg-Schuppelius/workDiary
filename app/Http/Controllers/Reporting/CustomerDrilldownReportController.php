<?php

/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jorg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDrilldownReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{Customer, DiaryEntry, OpenIssue, Project, Protocol};
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerDrilldownReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function openIssues(Request $request): View {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $customerId = (int) $request->integer('customer_id');
        $projectId = $request->filled('project_id') ? (int) $request->integer('project_id') : null;
        $userId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;
        $escalatedOnly = $request->boolean('escalated');

        $customer = $customerId > 0
            ? Customer::query()->find($customerId)
            : null;

        $projectIds = Project::query()
            ->where('customer_id', $customerId)
            ->when($projectId !== null, fn ($q) => $q->where('id', $projectId))
            ->pluck('id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $entryIds = DiaryEntry::query()
            ->where('customer_id', $customerId)
            ->whereBetween('created_at', [$from, $to])
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->pluck('id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $openStatuses = [
            OpenIssueStatus::Open->value,
            OpenIssueStatus::InProgress->value,
            OpenIssueStatus::Blocked->value,
            OpenIssueStatus::Reopened->value,
        ];

        $issues = OpenIssue::query()
            ->with(['assignee:id,name'])
            ->whereIn('status', $openStatuses)
            ->when($escalatedOnly, fn ($q) => $q->where('status', OpenIssueStatus::Blocked->value))
            ->where(function ($q) use ($customerId, $entryIds, $projectIds): void {
                $q->where(function ($sub) use ($customerId): void {
                    $sub->where('subject_type', Customer::class)
                        ->where('subject_id', $customerId);
                });

                if ($entryIds !== []) {
                    $q->orWhere(function ($sub) use ($entryIds): void {
                        $sub->where('subject_type', DiaryEntry::class)
                            ->whereIn('subject_id', $entryIds);
                    });
                }

                if ($projectIds !== []) {
                    $q->orWhere(function ($sub) use ($projectIds): void {
                        $sub->where('subject_type', Project::class)
                            ->whereIn('subject_id', $projectIds);
                    });
                }
            })
            ->orderByDesc('updated_at')
            ->paginate(50)
            ->withQueryString();

        /** @var view-string $openIssuesView */
        $openIssuesView = 'reports.drilldown.customer-open-issues';

        return view($openIssuesView, [
            'issues' => $issues,
            'customer' => $customer,
            'label' => $range['label'],
            'customerId' => $customerId,
            'projectId' => $projectId,
            'userId' => $userId,
            'escalatedOnly' => $escalatedOnly,
        ]);
    }

    public function protocols(Request $request): View {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $customerId = (int) $request->integer('customer_id');
        $projectId = $request->filled('project_id') ? (int) $request->integer('project_id') : null;
        $userId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;

        $customer = $customerId > 0
            ? Customer::query()->find($customerId)
            : null;

        $entryIds = DiaryEntry::query()
            ->where('customer_id', $customerId)
            ->whereBetween('created_at', [$from, $to])
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->pluck('id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $protocols = Protocol::query()
            ->with(['creator:id,name'])
            ->where('type', ProtocolType::Defect->value)
            ->where('subject_type', DiaryEntry::class)
            ->whereBetween('occurred_at', [$from, $to])
            ->when($entryIds !== [], fn ($q) => $q->whereIn('subject_id', $entryIds), fn ($q) => $q->whereRaw('1=0'))
            ->orderByDesc('occurred_at')
            ->paginate(50)
            ->withQueryString();

        /** @var view-string $protocolsView */
        $protocolsView = 'reports.drilldown.customer-protocols';

        return view($protocolsView, [
            'protocols' => $protocols,
            'customer' => $customer,
            'label' => $range['label'],
            'customerId' => $customerId,
            'projectId' => $projectId,
            'userId' => $userId,
        ]);
    }
}
