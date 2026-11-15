<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Kengele ya taarifa: kufungua taarifa (inawekwa "imesomwa") au kusoma zote.
 */
class NotificationController extends Controller
{
    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $personId = $notification->data['person_id'] ?? null;

        return $personId
            ? redirect()->route('people.show', $personId)
            : redirect()->route('dashboard');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
