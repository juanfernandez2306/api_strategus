<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User\Register;

use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Rules\MaxActiveUsersRule;
use PHPUnit\Framework\TestCase;

class MaxActiveUsersRuleTest extends TestCase
{
    public function testAllowsRegistrationWhenActiveUsersAreBelowLimit(): void
    {

        $repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $repositoryMock->expects($this->once())
            ->method('countActiveUsers')
            ->willReturn(29);


        $rule = new MaxActiveUsersRule($repositoryMock);
        $rule->fillParameters(['30']);


        $result = $rule->check('any_value');


        $this->assertTrue($result);
    }

    public function testDeniesRegistrationWhenActiveUsersReachOrExceedLimit(): void
    {

        $repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $repositoryMock->expects($this->once())
            ->method('countActiveUsers')
            ->willReturn(30);

        $rule = new MaxActiveUsersRule($repositoryMock);
        $rule->fillParameters(['30']);

        $result = $rule->check('any_value');


        $this->assertFalse($result);
    }
}
