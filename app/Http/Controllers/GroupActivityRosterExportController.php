<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Group;
use App\Services\Groups\ActivityRosterSpreadsheetExportService;
use Illuminate\Http\Response;

class GroupActivityRosterExportController extends Controller
{
    public function show(
        Group $group,
        Activity $activity,
        ActivityRosterSpreadsheetExportService $exportService,
    ): Response {
        $this->authorize('manageDashboard', [$activity, $group]);

        return response($exportService->render($activity), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $exportService->filename($activity)),
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'public',
        ]);
    }
}
