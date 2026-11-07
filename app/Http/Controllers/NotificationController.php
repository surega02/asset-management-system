<?php

namespace App\Http\Controllers;

use App\Support\SystemNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NotificationController
{
    public function index(Request $request, SystemNotifications $notifications): View
    {
        $inbox = $notifications->inbox($request->user())->orderByDesc('notifications.id')
            ->simplePaginate(20, ['notifications.id', 'notifications.read_at', 'notifications.created_at', 'event.kind', 'event.subject_id'])
            ->through(function (object $row): object {
                $row->url = match ($row->kind) {
                    'asset_import' => route('assets.import.show', $row->subject_id),
                    'employee_import' => route('employees.import.show', $row->subject_id),
                    'employee_transferred' => route('employees.show', $row->subject_id),
                    'acting_expiry' => route('appointments.index'),
                    default => route('assets.show', $row->subject_id),
                };

                return $row;
            });

        return view('system.notifications', compact('inbox'));
    }

    public function read(Request $request, int $id, SystemNotifications $notifications): RedirectResponse
    {
        $row = $notifications->inbox($request->user())->where('notifications.id', $id);
        abort_unless((clone $row)->exists(), 404);
        $row->whereNull('notifications.read_at')->update(['notifications.read_at' => now()]);

        return to_route('system.notifications');
    }
}
