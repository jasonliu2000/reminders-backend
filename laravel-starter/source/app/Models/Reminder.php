<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reminder extends Model
{
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
     * Fills model using attributes with camel case keys.
     * 
     * @param array $attributes
     * @return $this
     */
    public function fillWithCamelCase(array $attributes): Reminder
    {
        $snakeCaseAttributes = [];
        foreach ($attributes as $key => $value) {
            $snakeKey = Str::snake($key);
            $snakeCaseAttributes[$snakeKey] = $value;
        }

        return $this->fill($snakeCaseAttributes);
    }


    /**
     * Creates new Reminder using attributes with camel case keys.
     * 
     * @param array $attributes
     * @return Reminder
     */
    public static function createWithCamelCase(array $attributes): Reminder
    {
        $snakeCaseAttributes = [];
        foreach ($attributes as $key => $value) {
            $snakeKey = Str::snake($key);
            $snakeCaseAttributes[$snakeKey] = $value;
        }

        return Reminder::create($snakeCaseAttributes);
    }

}
