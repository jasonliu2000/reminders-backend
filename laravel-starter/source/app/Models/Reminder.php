<?php

namespace App\Models;

use App\Services\DateTimeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reminder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user',
        'text',
        'recurrence_type',
        'recurrence_value',
        'start_date',
    ];


    /**
     * Transforms attributes before creating and returning a new Reminder.
     * 
     * @param array $attributes
     * @return Reminder
     */
    public static function transformAndCreate(array $attributes): Reminder
    {
        $attributes['startDate'] = DateTimeService::transformIntoRFC3339($attributes['startDate']);
        return self::createWithCamelCase($attributes);
    }


    /**
     * Creates new Reminder using attributes with camel case keys.
     * 
     * @param array $attributes
     * @return Reminder
     */
    static function createWithCamelCase(array $attributes): Reminder
    {
        $snakeCaseAttributes = [];
        foreach ($attributes as $key => $value) {
            $snakeKey = Str::snake($key);
            $snakeCaseAttributes[$snakeKey] = $value;
        }

        return self::create($snakeCaseAttributes);
    }


    /**
     * Transforms attributes before filling existing Reminder with patched fields and returning it.
     * 
     * @param array $attributes
     * @return $this
     */
    public function transformAndFill(array $attributes): Reminder
    {
        if (isset($attributes['startDate'])) {
            $attributes['startDate'] = DateTimeService::transformIntoRFC3339($attributes['startDate']);
        }
        return $this->fillWithCamelCase($attributes);
    }


    /**
     * Fills existing Reminder using attributes with camel case keys.
     * 
     * @param array $attributes
     * @return $this
     */
    function fillWithCamelCase(array $attributes): Reminder
    {
        $snakeCaseAttributes = [];
        foreach ($attributes as $key => $value) {
            $snakeKey = Str::snake($key);
            $snakeCaseAttributes[$snakeKey] = $value;
        }

        return $this->fill($snakeCaseAttributes);
    }

}
