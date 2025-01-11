<?php

namespace App\Controllers\API;

use App\Controllers\Controller;
use App\Models\Reminder;
use App\Resources\ReminderResource;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    /**
     * Creates a new reminder & stores it in the database
     * 
     */
    public function create(Request $request): Responsable
    {
        $user = $request->input('user');
        $text = $request->input('text');
        // TODO: add recurrence

        return new ReminderResource(Reminder::create([
            'user' => $user,
            'text' => $text,
        ]));
    }
}
