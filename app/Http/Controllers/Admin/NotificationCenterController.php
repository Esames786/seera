<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $rows = $access->filterVisible($actor->notifications()
            ->whereIn('type', NotificationAccess::TYPES)
            ->latest()->orderByDesc('id')->limit(NotificationAccess::HISTORY_LIMIT)->get(), $actor)
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
            ->whereIn('type', NotificationAccess::TYPES)
            ->whereKey($notification)->firstOrFail();

        abort_unless($access->visible($notice, $request->user()), 404);
        $notice->markAsRead();

        return redirect()->route('admin.notifications.index');
    }

    public function readAll(Request $request, NotificationAccess $access): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->whereIn('type', NotificationAccess::TYPES)
            ->chunkById(NotificationAccess::HISTORY_LIMIT, function ($rows) use ($request, $access) {
                $access->filterVisible($rows, $request->user())->each(fn ($notice) => $notice->markAsRead());
            });

        return redirect()->route('admin.notifications.index');
    }
}
