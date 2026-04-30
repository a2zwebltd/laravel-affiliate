<?php

namespace A2ZWeb\Affiliate\Tests;

use A2ZWeb\Affiliate\Concerns\HasAffiliateProgram;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasAffiliateProgram, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password'];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! $user->name) {
                $user->name = 'Test User';
            }
            if (! $user->password) {
                $user->password = bcrypt('password');
            }
        });
    }
}
