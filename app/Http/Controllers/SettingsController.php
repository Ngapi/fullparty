<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display the user's settings page.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->notification_preferences_reviewed_at === null) {
            $user->forceFill([
                'notification_preferences_reviewed_at' => now(),
            ])->save();

            $request->session()->flash('success', ['notification_preferences_reviewed']);
        }

        return Inertia::render('Dashboard/Settings/Index');
    }
}
