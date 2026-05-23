<?php

namespace App\Http\Controllers;

use App\Models\Character;
use Inertia\Inertia;

class SettingsController extends Controller
{
	/**
	 * Display the user's settings page.
	 *
	 * @return \Inertia\Response
	 */
    public function index()
	{
		$characters = auth()->user()
            ->characters()
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->map(fn (Character $character) => [
                'id' => $character->id,
                'name' => $character->name,
                'world' => $character->world,
                'datacenter' => $character->datacenter,
                'is_primary' => (bool) $character->is_primary,
            ]);

		return Inertia::render('Dashboard/Settings/Index', [
            'characters' => $characters,
        ]);
	}
}
