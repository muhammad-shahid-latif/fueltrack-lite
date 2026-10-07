<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Owner-only audit trail of every create, change and delete.
 */
class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'module' => ['nullable', Rule::in(array_keys(ActivityLog::MODULES))],
            'event' => ['nullable', 'in:created,updated,deleted'],
            'user' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->date('from'), fn ($query, $from) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($query, $to) => $query->where('created_at', '<=', $to->endOfDay()))
            ->when($request->input('module'), fn ($query, $module) => $query->where('subject_type', $module))
            ->when($request->input('event'), fn ($query, $event) => $query->where('event', $event))
            ->when($request->integer('user'), fn ($query, $user) => $query->where('user_id', $user))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('activity.index', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(),
            'modules' => ActivityLog::MODULES,
            'filters' => $request->only(['from', 'to', 'module', 'event', 'user']),
        ]);
    }
}
