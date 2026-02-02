<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use App\Models\Tenant\UserActivity;
use Illuminate\Support\Facades\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = UserActivity::query();
        // Filters
        if ($request->filled('activity_type')) {
            $query->where('activity_type', $request->input('activity_type'));
        }
        if ($request->filled('user')) {
            // user will be name or id? if int we keep id filter
            if (is_numeric($request->input('user'))) {
                $query->where('user_id', $request->input('user'));
            } else {
                $query->whereHas('user', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->input('user') . '%');
                });
            }
        }
        if ($request->filled('table')) {
            $query->where('table_name', $request->input('table'));
        }
        $activities = $query->orderByDesc('created_at')->paginate(25);
        return view('activity_logs.index', compact('activities'));
    }

    public function show($id)
    {
        $activity = UserActivity::findOrFail($id);
        return view('activity_logs.show', compact('activity'));
    }

    public function export(Request $request)
    {
        try {
            $query = UserActivity::query();
            if ($request->filled('activity_type')) {
                $query->where('activity_type', $request->input('activity_type'));
            }
            if ($request->filled('user')) {
                if (is_numeric($request->input('user'))) {
                    $query->where('user_id', $request->input('user'));
                } else {
                    $query->whereHas('user', function($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('user') . '%');
                    });
                }
            }
            if ($request->filled('table')) {
                $query->where('table_name', $request->input('table'));
            }
            $activities = $query->orderByDesc('created_at')->get();
            $columns = ['id', 'user', 'type', 'table', 'description', 'ip', 'datetime'];
            $filename = 'activity_logs_' . now()->format('Ymd_His') . '.csv';
            $callback = function() use ($activities, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);
                foreach ($activities as $activity) {
                    fputcsv($file, [
                        $activity->id,
                        $activity->user ? $activity->user->name : '',
                        $activity->activity_type,
                        $activity->table_name,
                        $activity->description,
                        $activity->ip_address,
                        $activity->created_at,
                    ]);
                }
                fclose($file);
            };
            return response()->streamDownload($callback, $filename, [
                'Content-Type' => 'text/csv', // this is causing a 404 error in some environments
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to export activity logs: ' . $e->getMessage());
            return response()->view('errors.export', ['error' => $e->getMessage()], 500);
        }
    }
}
