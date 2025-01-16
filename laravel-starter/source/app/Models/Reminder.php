<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
     * Custom method to fill model using camel case keys.
     * 
     * @param array $attributes
     * @return $this
     */
    public function fillWithCamelCase(array $attributes)
    {
        $snakeCaseAttributes = [];

        foreach ($attributes as $key => $value) {
            $snakeKey = \Illuminate\Support\Str::snake($key);
            $snakeCaseAttributes[$snakeKey] = $value;
        }

        return $this->fill($snakeCaseAttributes);
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
