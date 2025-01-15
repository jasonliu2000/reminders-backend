<?php

namespace App\Controllers\API;

use App\Controllers\Controller;
use App\Models\Reminder;
use App\Resources\ReminderResource;
use App\Enums\ReminderFrequency;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ReminderController extends Controller
{
    /**
     * Creates a new reminder & stores it in the database
     * 
     */
    public function create(Request $request): Responsable // TODO: consider returning JsonResponse type 
    {
        $request->validate([
            'user' => ['required'],
            'text' => ['required', 'string'],
            'frequency' => ['required', Rule::enum(ReminderFrequency::class)], // TODO: improve error message if invalid enum
            'customInterval' => ['required_if:frequency,custom', 'integer'],
            'startDate' => ['required', 'date', 'after_or_equal:today'], // TODO: consider adding date_format instead // FYI: currently only considers UTC timezone
        ]);

        $user = $request->input('user');
        $text = $request->input('text');
        $frequency = $request->input('frequency');
        $customInterval = $request->input('customInterval');
        $startDate = $request->input('startDate');
        
        // TODO: handle duplicate reminder created

        return new ReminderResource(Reminder::create([
            'user' => $user,
            'text' => $text,
            'frequency' => $frequency,
            'custom_interval' => $customInterval,
            'start_date' => $startDate,
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

    /**
     * Delete reminder by ID
     * 
     */
    public function delete(int $id): Response
    {
        $reminder = Reminder::findOrFail($id);
        // if ($reminder === null) {
        //     return response()->json(['error' => 'Reminder not found'], 404);
        // }

        $reminder->delete();
        return response()->noContent();
    }
}
