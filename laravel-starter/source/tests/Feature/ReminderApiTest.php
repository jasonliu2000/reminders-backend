<?php

namespace Tests\Feature;

use App\Models\Reminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderApiTest extends TestCase
{
    use RefreshDatabase;

    protected $responseStructure;
    protected $errorResponseStructure;

    protected function setUp(): void
    {
        parent::setUp();
        $reminderObject = [
            'id',
            'user',
            'text',
            'recurrenceType',
            'customRecurrence',
            'startDate',
            'createdAt',
            'updatedAt',
        ];

        $this->responseStructure = [
            'data' => $reminderObject
        ];

        $this->errorResponseStructure = ['status', 'message'];
    }


    // Test GET by ID
    
    public function test_get_by_id_is_successful()
    {
        $reminder = Reminder::factory()->create();
        $text = $reminder->text;
        $response = $this->getJson("/api/reminders/$reminder->id");

        $response->assertOk();
        $response->assertJsonStructure($this->responseStructure);
        $response->assertJson(['data' => ['text' => $text]]);
    }

    public function test_get_by_id_not_found()
    {
        $response = $this->getJson('/api/reminders/123');
        $response->assertNotFound();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('Reminder not found', $message);
    }


    // Test GET by keyword

    public function test_get_by_empty_keyword_is_successful()
    {
        Reminder::factory()->count(3)->create();

        $response = $this->getJson('/api/reminders/search?keyword=');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_get_by_keyword_is_successful()
    {
        for ($x = 0; $x < 3; $x++) {
            Reminder::factory()->create([
                'text' => 'test' . $x,
            ]);
        }

        Reminder::factory()->create([
            'text' => 'Does not match keyword t_e_s_t',
        ]);

        $response = $this->getJson('/api/reminders/search?keyword=test');

        $response->assertOk();
        foreach ($response->json()['data'] as $reminderJson) {
            $this->assertStringContainsString('test', $reminderJson['text']);
        }
    }

    public function test_get_by_keyword_no_results()
    {
        $response = $this->getJson('/api/reminders/search?keyword=any');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_get_by_keyword_bad_request()
    {
        $response = $this->getJson('/api/reminders/search?keywo=');
        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('keyword field must be present', $message);
    }



    // Test GET by date range

    public function test_get_date_range_is_successful()
    {
        $reminderDates = [
            '2030-01-01T12:00:00Z',
            '2030-02-28T00:00:00Z',
            '2030-03-20T10:21:00Z',
        ];

        foreach ($reminderDates as $date) {
            Reminder::factory()->create([
                'start_date' => $date,
            ]);
        }

        // from: 2029-06-06T00:00:00Z
        // to: 2031-02-02T00:00:00Z
        $response = $this->getJson('/api/reminders?startDate=20290606T000000Z&endDate=20310202T000000Z');
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        
        // from: 2029-06-06T00:00:00Z
        // to: 2029-12-02T00:00:00Z
        $responseNone = $this->getJson('/api/reminders?startDate=20290606T000000Z&endDate=20291202T000000Z');
        $responseNone->assertOk();
        $responseNone->assertJsonCount(0, 'data');
    }

    public function test_get_by_date_range_missing_date()
    {
        $response = $this->getJson('/api/reminders?sta');
        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message'];
        $this->assertStringContainsString('start date field is required', $message);
    }

    public function test_get_by_date_range_bad_date()
    {
        $response = $this->getJson('/api/reminders?startDate=123&endDate=098');
        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message'];
        $this->assertStringContainsString('start date field must match the format', $message);
    }

    public function test_get_by_date_range_past_date()
    {
        $response = $this->getJson('/api/reminders?startDate=20000101T000000Z&endDate=20300101T121212Z');
        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message'];
        $this->assertStringContainsString('start date field must be a date after or equal to now', $message);
    }

    public function test_get_by_date_range_invalid_end()
    {
        $response = $this->getJson('/api/reminders?startDate=20300101T000000Z&endDate=20290101T000000Z');
        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message'];
        $this->assertStringContainsString('end date field must be a date after or equal to start date', $message);
    }


    // Test DELETE

    public function test_delete_by_id_is_successful()
    {
        $reminder = Reminder::factory()->create();
        $response = $this->delete('/api/reminders/' . $reminder->id);

        $response->assertNoContent();
    }

    public function test_delete_by_id_not_found()
    {
        $response = $this->delete('/api/reminders/123');
        $response->assertNotFound();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('Reminder not found', $message);
    }


    // Test POST

    public function test_create_one_time_reminder_is_successful()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'go to the dentist',
            'recurrenceType' => 'none',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertCreated();
        $responseData = $response->json()['data'];

        // Assert response payload
        foreach ($payload as $key => $value) {
            $this->assertArrayHasKey($key, $responseData, "Missing key: $key");

            if ($key === 'startDate') {
                $value = '2030-01-01T07:00:00Z';
            }

            $this->assertEquals($value, $responseData[$key]);
        }
    }

    public function test_create_daily_reminder_is_successful()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'Wake up',
            'recurrenceType' => 'daily',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertCreated();
        $responseData = $response->json()['data'];

        // Assert response payload
        foreach ($payload as $key => $value) {
            $this->assertArrayHasKey($key, $responseData, "Missing key: $key");

            if ($key === 'startDate') {
                $value = '2030-01-01T07:00:00Z';
            }

            $this->assertEquals($value, $responseData[$key]);
        }
    }

    public function test_create_weekly_reminder_is_successful()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'take weekly pill',
            'recurrenceType' => 'weekly',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertCreated();
        $responseData = $response->json()['data'];

        // Assert response payload
        foreach ($payload as $key => $value) {
            $this->assertArrayHasKey($key, $responseData, "Missing key: $key");

            if ($key === 'startDate') {
                $value = '2030-01-01T07:00:00Z';
            }

            $this->assertEquals($value, $responseData[$key]);
        }
    }

    public function test_create_monthly_reminder_is_successful()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'monthly doctors checkup',
            'recurrenceType' => 'monthly',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertCreated();
        $responseData = $response->json()['data'];

        // Assert response payload
        foreach ($payload as $key => $value) {
            $this->assertArrayHasKey($key, $responseData, "Missing key: $key");

            if ($key === 'startDate') {
                $value = '2030-01-01T07:00:00Z';
            }

            $this->assertEquals($value, $responseData[$key]);
        }
    }

    public function test_create_custom_reminder_is_successful()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'take pill every 3 days',
            'recurrenceType' => 'custom',
            'customRecurrence' => 3,
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertCreated();
        $responseData = $response->json()['data'];

        // Assert response payload
        foreach ($payload as $key => $value) {
            $this->assertArrayHasKey($key, $responseData, "Missing key: $key");

            if ($key === 'startDate') {
                $value = '2030-01-01T07:00:00Z';
            }

            $this->assertEquals($value, $responseData[$key]);
        }
    }

    public function test_create_unsuccessful_missing_text()
    {
        $payload = [
            'user' => 'jason',
            'recurrenceType' => 'none',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('text field is required', $message); 
    }

    public function test_create_unsuccessful_missing_recurrence_type()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('recurrence type field is required', $message); 
    }

    public function test_create_unsuccessful_invalid_recurrence_type()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'random',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('recurrence type is invalid', $message); 
    }

    public function test_create_unsuccessful_missing_custom_recurrence()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'custom',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('custom recurrence field is required', $message); 
    }

    public function test_create_unsuccessful_invalid_custom_recurrence()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'custom',
            'customRecurrence' => 0,
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('custom recurrence field must be at least 1', $message); 
    }

    public function test_create_unsuccessful_custom_recurrence_noninteger()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'custom',
            'customRecurrence' => 'zero',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('custom recurrence field must be an integer', $message); 
    }

    public function test_create_unsuccessful_missing_start_date()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'daily',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('start date field is required', $message); 
    }

    public function test_create_unsuccessful_past_start_date()
    {
        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'daily',
            'startDate' => '20000101T070000Z',
        ];
        
        $response = $this->postJson('/api/reminders/', $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('start date field must be a date after or equal to now', $message); 
    }


    // Test PATCH

    public function test_patch_is_successful()
    {
        $reminder = Reminder::factory()->create();

        $payload = [
            'text' => 'changed text',
            'recurrenceType' => 'none',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertOk();
        $responseData = $response->json()['data'];

        // Assert response payload
        foreach ($payload as $key => $value) {
            $this->assertArrayHasKey($key, $responseData, "Missing key: $key");

            if ($key === 'startDate') {
                $value = '2030-01-01T07:00:00Z';
            }

            $this->assertEquals($value, $responseData[$key]);
        }
    }

    public function test_patch_custom_is_successful()
    {
        $reminder = Reminder::factory()->create();

        $payload = [
            'text' => 'changed text',
            'recurrenceType' => 'custom',
            'customRecurrence' => 10,
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertOk();
        $responseData = $response->json()['data'];

        // Assert response payload
        foreach ($payload as $key => $value) {
            $this->assertArrayHasKey($key, $responseData, "Missing key: $key");

            if ($key === 'startDate') {
                $value = '2030-01-01T07:00:00Z';
            }

            $this->assertEquals($value, $responseData[$key]);
        }
    }

    public function test_patch_unsuccessful_missing_text()
    {
        $reminder = Reminder::factory()->create();
        
        $payload = [
            'text' => '',
            'recurrenceType' => 'none',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('text field is required', $message); 
    }

    public function test_patch_unsuccessful_missing_recurrence_type()
    {
        $reminder = Reminder::factory()->create();

        $payload = [
            'text' => 'brush teeth',
            'recurrenceType' => '',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('recurrence type field is required', $message); 
    }

    public function test_patch_unsuccessful_invalid_recurrence_type()
    {
        $reminder = Reminder::factory()->create();

        $payload = [
            'text' => 'brush teeth',
            'recurrenceType' => 'random',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('recurrence type is invalid', $message); 
    }

    public function test_patch_unsuccessful_missing_custom_recurrence()
    {
        $reminder = Reminder::factory()->create();

        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'custom',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('custom recurrence field is required', $message); 
    }

    public function test_patch_unsuccessful_invalid_custom_recurrence()
    {
        $reminder = Reminder::factory()->create();

        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'custom',
            'customRecurrence' => 0,
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('custom recurrence field must be at least 1', $message); 
    }

    public function test_patch_unsuccessful_custom_recurrence_noninteger()
    {
        $reminder = Reminder::factory()->create();

        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'custom',
            'customRecurrence' => 'zero',
            'startDate' => '20300101T070000Z',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('custom recurrence field must be an integer', $message); 
    }

    public function test_patch_unsuccessful_missing_start_date()
    {
        $reminder = Reminder::factory()->create();

        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'daily',
            'startDate' => '',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('start date field is required', $message); 
    }

    public function test_patch_unsuccessful_past_start_date()
    {
        $reminder = Reminder::factory()->create();

        $payload = [
            'user' => 'jason',
            'text' => 'brush teeth',
            'recurrenceType' => 'daily',
            'startDate' => '20000101T070000Z',
        ];
        
        $response = $this->patchJson("/api/reminders/$reminder->id", $payload);

        $response->assertBadRequest();
        $response->assertJsonStructure($this->errorResponseStructure);

        $message = $response->json()['message']; 
        $this->assertStringContainsString('start date field must be a date after or equal to now', $message); 
    }

}
