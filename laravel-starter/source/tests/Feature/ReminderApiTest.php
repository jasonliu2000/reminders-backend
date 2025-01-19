<?php

namespace Tests\Feature;

use App\Models\Reminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ReminderApiTest extends TestCase
{
    use RefreshDatabase;

    protected $responseStructure;

    protected function setUp(): void
    {
        parent::setUp();
        $reminderObject = [
            'id',
            'user',
            'text',
            'recurrenceType',
            'recurrenceValue',
            'startDate',
            'createdAt',
            'updatedAt',
        ];

        $this->responseStructure = [
            'data' => $reminderObject
        ];
    }
    
    public function test_get_by_id_is_successful(): void
    {
        $reminder = Reminder::factory()->create();
        $text = $reminder->text;
        $response = $this->getJson('/api/reminders/' . $reminder->id);
        // var_dump($response->collect());

        $response->assertOk();
        $response->assertJsonStructure($this->responseStructure);
        $response->assertJson(['data' => ['text' => $text]]);
    }

    public function test_get_by_id_not_found(): void
    {
        $response = $this->getJson('/api/reminders/123');
        $response->assertNotFound();
    }

    public function test_get_by_empty_keyword_is_successful(): void
    {
        Reminder::factory()->count(3)->create();

        $responseAll = $this->getJson('/api/reminders/search?keyword=');
        // var_dump($responseAll->collect()[0]);

        $responseAll->assertOk();
        $responseAll->assertJsonCount(3, 'data');
    }

    public function test_get_by_keyword_is_successful(): void
    {
        for ($x = 0; $x < 3; $x++) {
            Reminder::factory()->create([
                'text' => 'test' . $x,
            ]);
        }

        Reminder::factory()->create([
            'text' => 'Does not match keyword t_e_s_t',
        ]);

        $responseAll = $this->getJson('/api/reminders/search?keyword=test');

        $responseAll->assertOk();
        foreach ($responseAll->json()['data'] as $reminderJson) {
            $this->assertStringContainsString('test', $reminderJson['text']);
        }
    }

    public function test_get_by_keyword_no_results(): void
    {
        $responseAll = $this->getJson('/api/reminders/search?keyword=any');

        $responseAll->assertOk();
        $responseAll->assertJsonCount(0, 'data');
    }

    public function test_get_date_range_is_successful(): void
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
        $responseAll = $this->getJson('/api/reminders?startDate=20290606T000000Z&endDate=20310202T000000Z');
        $responseAll->assertOk();
        $responseAll->assertJsonCount(3, 'data');
        
        // from: 2029-06-06T00:00:00Z
        // to: 2029-12-02T00:00:00Z
        $responseNone = $this->getJson('/api/reminders?startDate=20290606T000000Z&endDate=20291202T000000Z');
        $responseNone->assertOk();
        $responseNone->assertJsonCount(0, 'data');
    }

}
