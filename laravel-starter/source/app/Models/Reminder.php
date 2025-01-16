<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Reminder extends Model
{
    // use HasFactory, Notifiable;

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
     * @return Reminder $reminder
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

    // /**
    //  * The attributes that should be hidden for serialization.
    //  *
    //  * @var array<int, string>
    //  */
    // protected $hidden = [
    //     'password',
    //     'remember_token',
    // ];

    // /**
    //  * Get the attributes that should be cast.
    //  *
    //  * @return array<string, string>
    //  */
    // protected function casts(): array
    // {
    //     return [
    //         'email_verified_at' => 'datetime',
    //         'password' => 'hashed',
    //     ];
    // }
}
