<?php

namespace App\Controllers\API;

use App\Controllers\Controller;
use App\Models\Reminder;
use App\Resources\ReminderResource;
use App\Services\ReminderService;
use App\Enums\ReminderRecurrenceType;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ReminderController extends Controller
{
    private $reminderService;

    public function __construct(ReminderService $reminderService)
    {
        $this->reminderService = $reminderService;
    }
    

    /**
     * Creates a new reminder & stores it in the database
     * 
     */
    public function create(Request $request): Responsable // TODO: consider returning JsonResponse type 
    {
        $request->validate([
            'user' => ['required'],
            'text' => ['required', 'string'],
            'recurrenceType' => ['required', Rule::enum(ReminderRecurrenceType::class)], // TODO: improve error message if invalid enum
            'recurrenceValue' => ['required_if:recurrenceType,weekly', 'required_if:recurrenceType,every_n_days', 'integer'], // TODO: consider how to improve weekly // Weekly value: ISO 8601 (1 - Mon, 7 - Sun)
            'startDate' => ['required', 'date', 'after_or_equal:today'], // TODO: consider adding date_format instead // FYI: currently only considers UTC timezone
        ]);

        $user = $request->input('user');
        $text = $request->input('text');
        $recurrenceType = $request->input('recurrenceType');
        $recurrenceValue = $request->input('recurrenceValue');
        $startDate = $request->input('startDate');

        return new ReminderResource(Reminder::create([
            'user' => $user,
            'text' => $text,
            'recurrence_type' => $recurrenceType,
            'recurrence_value' => $recurrenceValue,
            'start_date' => $startDate,
        ]));
    }


    /**
     * Get reminder by ID
     * 
     */
    public function getById(int $id): Responsable
    {
        return new ReminderResource(Reminder::findOrFail($id));
    }


    /**
     * Returns reminder(s) based on a keyword
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
     * Returns reminder(s) in given date range
     * 
     */
    public function getRemindersInDateRange(Request $request): Responsable
    {
        $request->validate([
            'startDate' => ['required', 'date', 'after_or_equal:today'], // TODO: consider adding date_format instead // FYI: currently only considers UTC timezone
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
        ]);

        $remindersInRange = $this->reminderService->getRemindersInDateRange($request->input('startDate'), $request->input('endDate'));
        
        return ReminderResource::collection($remindersInRange);
    }


    /**
     * Patches an existing reminder and then returns the reminder
     * 
     */
    public function patch(Request $request, int $id): Responsable // TODO: consider returning JsonResponse type 
    {
        $validatedData = $request->validate([
            'text' => ['sometimes', 'required', 'string'],
            'recurrenceType' => ['sometimes', 'required', Rule::enum(ReminderRecurrenceType::class)], // TODO: improve error message if invalid enum
            'recurrenceValue' => ['required_if:recurrenceType,weekly', 'required_if:recurrenceType,every_n_days', 'integer'], // TODO: consider how to improve weekly // Weekly value: ISO 8601 (1 - Mon, 7 - Sun)
            'startDate' => ['sometimes', 'required', 'date', 'after_or_equal:today'], // TODO: consider adding date_format instead // FYI: currently only considers UTC timezone
        ]);

        $reminder = Reminder::findOrFail($id);
        $reminder->fillWithCamelCase($validatedData);
        $reminder->save();

        return new ReminderResource($reminder);
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
