<?php

namespace App\Controllers\API;

use App\Controllers\Controller;
use App\Models\Reminder;
use App\Resources\ReminderResource;
use App\Services\ReminderService;
use App\Services\DateTimeService;
use App\Enums\ReminderRecurrenceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PDOException;
use Exception;

class ReminderController extends Controller
{
    /**
     * Creates a new reminder & stores it in the database
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'user' => ['required'],
                'text' => ['required', 'string'],
                'recurrenceType' => ['required', Rule::enum(ReminderRecurrenceType::class)],
                'customRecurrence' => ['nullable', 'required_if:recurrenceType,custom', 'integer', 'min:1'],
                'startDate' => ['required', $this->getDateFormat(), 'after_or_equal:now'],
            ]);

            return (new ReminderResource(Reminder::transformAndCreate($validatedData)))->toResponse(request());
        }
        
        catch (ValidationException $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(400, $e->getMessage());
        }
        
        catch (PDOException $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(500, 'Failed to add reminder to database.');
        }
        
        catch (Exception $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(500, 'Failed to create and store reminder due to an internal error.');
        }

    }


    /**
     * Get reminder by ID
     */
    public function getById(int $id): JsonResponse
    {
        try {
            $reminder = Reminder::findOrFail($id);
            return (new ReminderResource($reminder))->toResponse(request());
        } 
        
        catch (ModelNotFoundException) {
            return $this->errorResponse(404, 'Reminder not found');
        }

        catch (Exception $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(500, 'Failed to get reminder due to an internal error.');
        }
    }


    /**
     * Returns reminder(s) based on a keyword
     */
    public function getRemindersByKeyword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'keyword' => 'present',
            ]);
            
            $keyword = e($request->query('keyword'));
            Log::info('Getting reminders matching keyword: ' . $keyword);
    
            $reminders = Reminder::where('text', 'like', '%' . $keyword . '%')->get();
            return (ReminderResource::collection($reminders))->toResponse(request());
        } 
        
        catch (ValidationException $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(400, $e->getMessage());
        }

        catch (Exception $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(500, 'Failed to retrieve reminders due to an internal error.');
        }
    }


    /**
     * Returns reminder(s) in given date range
     */
    public function getRemindersInDateRange(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'startDate' => ['required', $this->getDateFormat(), 'after_or_equal:now'],
                'endDate' => ['required', $this->getDateFormat(), 'after_or_equal:startDate'],
            ]);
    
            Log::info('Getting reminders for given date range:', [$request->input()]);
    
            $remindersInRange = ReminderService::getRemindersInDateRange($request->input('startDate'), $request->input('endDate'));
            return (ReminderResource::collection($remindersInRange))->toResponse(request());
        }
        
        catch (ValidationException $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(400, $e->getMessage());
        }
        
        catch (Exception $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(500, 'Failed to retrieve reminders due to an internal error.');
        }

    }


    /**
     * Patches an existing reminder and then returns the reminder
     */
    public function patch(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'text' => ['sometimes', 'required', 'string'],
                'recurrenceType' => ['sometimes', 'required', Rule::enum(ReminderRecurrenceType::class)],
                'customRecurrence' => ['required_if:recurrenceType,custom', 'integer', 'min:1'],
                'startDate' => ['sometimes', 'required', $this->getDateFormat(), 'after_or_equal:now'],
            ]);
    
            $reminder = Reminder::findOrFail($id);
            $reminder->transformAndFill($validatedData);
            $reminder->save();

            return (new ReminderResource($reminder))->toResponse(request());
        } 
        
        catch (ModelNotFoundException) {
            return $this->errorResponse(404, 'Reminder not found');
        } 
        
        catch (ValidationException $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(400, $e->getMessage());
        } 
        
        catch (Exception $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(500, 'Failed to patch reminder due to an internal error.');
        }

    }


    /**
     * Delete reminder by ID
     */
    public function delete(int $id): JsonResponse
    {
        try {
            $reminder = Reminder::findOrFail($id);
            $reminder->delete();
            return response()->json(null, 204);
        } 
        
        catch (ModelNotFoundException) {
            return $this->errorResponse(404, 'Reminder not found');
        }

        catch (Exception $e) {
            Log::error('Error: ' . $e->getMessage());
            return $this->errorResponse(500, 'Failed to delete reminder due to an internal error.');
        }
    }


    /**
     * Get date format string that should be used to validate all date inputs
     */
    function getDateFormat(): string
    {
        return 'date_format:' . DateTimeService::getDateFormat();
    }


    /**
     * Returns custom error response
     * 
     * @param int $status - the HTTP status code
     * @param string $message - the error message
     * @return JsonResponse
     */
    function errorResponse(int $status, string $message): JsonResponse
    {
        $statusMessages = [
            400 => '400 Bad Request',
            404 => '404 Not Found',
            500 => '500 Internal Server Error',
        ];

        $statusMessage = $statusMessages[$status] ?? $status . ' Error';

        return response()->json([
            'status' => $statusMessage,
            'message' => $message,
        ], $status);
    }

}
