<?php

namespace ModuHelp\Laravel\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

/** In-memory user: persisting is a no-op so logout works without a database. */
class TestUser extends User
{
    protected $guarded = [];

    public function save(array $options = []): bool
    {
        return true;
    }
}
