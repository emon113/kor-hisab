<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'salary_ratios'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'salary_ratios' => 'array',
        ];
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class);
    }

    public function wealthStatements(): HasMany
    {
        return $this->hasMany(WealthStatement::class);
    }

    public function firstName(): string
    {
        return strtok($this->name, ' ') ?: $this->name;
    }
}
