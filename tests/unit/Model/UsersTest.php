<?php

namespace App\Test\Unit\Models;

use UnitTester;
use Codeception\Test\Unit;
use TTDemo\Models\Users;

class UsersTest extends Unit
{
    /**
     * The Users model.
     * @var Users
     */
    protected $user;

    /**
     * UnitTester Object
     * @var UnitTester
     */
    protected $tester;

    protected function _before()
    {
        $this->user = new Users;
    }

    public function testGetSource()
    {
        $user = $this->user->findFirst();
        $this->assertEquals($this->user->getSource(), 'users');
        $this->assertEquals($user->username, 'demo');
    }
}
