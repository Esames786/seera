<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\ApprovalActionNotification;
use App\Services\Notifications\NotificationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(Request $request, NotificationAccess $access): View
    {
        $actor = $request->user();
        // Limited MVP history; never collect another user's notifications.
        $rows = $actor->notifications()
            ->where('type', ApprovalActionNotification::class)
            ->latest()->limit(300)->get()
            ->filter(fn ($notice) => $access->visible($notice, $actor))
            ->values();
        $page = max(1, $request->integer('page', 1));
        $items = new LengthAwarePaginator(
            $rows->forPage($page, 20)->values(), $rows->count(), 20, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.notifications.index', ['notifications' => $items]);
    }

    public function read(Request $request, string $notification, NotificationAccess $access): RedirectResponse
    {
        $notice = $request->user()->notifications()
            ->where('type', ApprovalActionNotification::class)
            ->whereKey($notification)->firstOrFail();

        abort_unless($access->visible($notice, $request->user()), 404);
        $notice->markAsRead();

        return redirect()->route('admin.notifications.index');
    }

    public function readAll(Request $request, NotificationAccess $access): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->where('type', ApprovalActionNotification::class)
            ->get()->each(function ($notice) use ($request, $access) {
                if ($access->visible($notice, $request->user())) {
                    $notice->markAsRead();
                }
            });

        return redirect()->route('admin.notifications.index');
    }
}
