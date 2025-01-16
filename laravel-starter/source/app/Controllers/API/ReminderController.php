<?php

namespace App\Controllers\API;

use App\Controllers\Controller;
use App\Models\Reminder;
use App\Resources\ReminderResource;
use App\Services\ReminderService;
use App\Enums\ReminderRecurrenceType;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

use Illuminate\Validation\ValidationException;
use PDOException;
use Exception;

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
    public function create(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'user' => ['required'],
                'text' => ['required', 'string'],
                'recurrenceType' => ['required', Rule::enum(ReminderRecurrenceType::class)], // TODO: improve error message if invalid enum
                'recurrenceValue' => ['nullable', 'required_if:recurrenceType,weekly', 'required_if:recurrenceType,every_n_days', 'integer'], // TODO: consider how to improve weekly // Weekly value: ISO 8601 (1 - Mon, 7 - Sun)
                'startDate' => ['required', 'date', 'after_or_equal:today'], // TODO: consider adding date_format instead // FYI: currently only considers UTC timezone
            ]);

            return response()->json(Reminder::createWithCamelCase($validatedData), 201);

        } catch (ValidationException $e) {
            Log::error('Exception:', [$e->getMessage()]);
            return response()->json([
                'status' => '400 Bad Request',
                'message' => $e->getMessage(),
            ], 400);
        } catch (PDOException $e) {
            Log::error('Exception:', [$e->getMessage()]);
            return response()->json([
                'status' => '500 Internal Server Error',
                'message' => 'Failed to add reminder to database.',
            ], 500);
        } catch (Exception $e) {
            Log::error('Exception:', [$e->getMessage()]);
            return response()->json([
                'status' => '500 Internal Server Error',
                'message' => 'Failed to create and store reminder due to internal error.',
            ], 500);
        }
    }


    /**
     * Get reminder by ID
     * 
     */
    public function getById(int $id): JsonResponse
    {
        try {
            $reminder = Reminder::findOrFail($id);
            return response()->json($reminder);
        } catch (Exception $e) {
            Log::error('Exception:', [$e->getMessage()]);
            return response()->json([
                'status' => '404 Not Found',
                'message' => 'Reminder not found',
            ], 404);
        }
    }


    /**
     * Returns reminder(s) based on a keyword
     * 
     */
    public function searchRemindersByKeyword(Request $request): JsonResponse
    {
        $keyword = $request->query('keyword');
        $keyword = e($keyword);
        Log::info('Getting reminders matching keyword:', [$keyword]);

        $reminders = Reminder::where('text', 'like', '%' . $keyword . '%')->get();
        return response()->json($reminders, 200);
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
    public function delete(int $id): JsonResponse
    {
        try {
            $reminder = Reminder::findOrFail($id);
            $reminder->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            Log::error('Exception:', [$e->getMessage()]);
            return response()->json([
                'status' => '404 Not Found',
                'message' => 'Reminder not found',
            ], 404);
        }
    }
}
