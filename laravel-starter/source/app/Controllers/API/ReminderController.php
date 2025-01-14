<?php

namespace App\Controllers\API;

use App\Controllers\Controller;
use App\Models\Reminder;
use App\Resources\ReminderResource;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    /**
     * Gets reminder(s) based on a keyword
     * 
     */
    public function searchRemindersByKeyword(Request $request): Responsable
    {   
        Log::info('all:', [Reminder::all()]);

        $keyword = $request->input('keyword');
        Log::info('keyword:', [$keyword]);

        $reminders = Reminder::where('text', 'like', "%$keyword%")->get();
        Log::info('Reminders data:', ['reminders' => $reminders]);

        return ReminderResource::collection($reminders);
    }
}
