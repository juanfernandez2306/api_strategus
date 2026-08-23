<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User;

use App\Shared\Exceptions\ValidationException;
use App\Users\Actions\Auth\RegisterAction;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Crud\UserCrudRepositoryInterface;
use App\Users\Services\Mail\MailRegisterInterface;
use App\Users\Validators\Auth\RegisterValidator;
use Faker\Factory;
use Faker\Generator;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class RegisterActionTest extends TestCase
{
    private Generator $faker;
    private PDO&MockObject $pdoMock;
    private RegisterValidator&MockObject $validatorMock;
    private UserCrudRepositoryInterface&MockObject $userCrudRepoMock;
    private PasswordResetRepositoryInterface&MockObject $passwordResetRepoMock;
    private MailRegisterInterface&MockObject $mailRegisterServiceMock;
    private ServerRequestInterface&MockObject $requestMock;
    private ResponseInterface&MockObject $responseMock;
    private StreamInterface&MockObject $streamMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create('es_ES');

        $this->pdoMock = $this->createMock(PDO::class);
        $this->validatorMock = $this->createMock(RegisterValidator::class);
        $this->userCrudRepoMock = $this->createMock(UserCrudRepositoryInterface::class);
        $this->passwordResetRepoMock = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->mailRegisterServiceMock = $this->createMock(MailRegisterInterface::class);

        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->responseMock = $this->createMock(ResponseInterface::class);
        $this->streamMock = $this->createMock(StreamInterface::class);
    }


    public function testExecuteReturns201OnSuccessfulRegistration(): void
    {
        $dummyPayload = [
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'email'      => $this->faker->email(),
        ];


        $this->validatorMock->method('validate')->willReturn($dummyPayload);
        $this->userCrudRepoMock->method('create')->willReturn($this->faker->randomNumber());
        $this->passwordResetRepoMock->method('save')->willReturn(true);
        $this->mailRegisterServiceMock->method('send')->willReturn(true);

        $this->requestMock->method('getParsedBody')->willReturn($dummyPayload);
        $this->responseMock->method('getBody')->willReturn($this->streamMock);

        $this->responseMock
            ->method('withHeader')
            ->willReturnSelf();

        $this->responseMock
            ->expects($this->once())
            ->method('withStatus')
            ->with(201)
            ->willReturnSelf();


        $action = new RegisterAction(
            $this->pdoMock,
            $this->validatorMock,
            $this->userCrudRepoMock,
            $this->passwordResetRepoMock,
            $this->mailRegisterServiceMock
        );

        $response = $action($this->requestMock, $this->responseMock);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }


    public function testExecuteThrowsExceptionOnValidationError(): void
    {
        $invalidPayload = [];

        $validationErrors = [
            'email' => ['El campo correo electrónico es obligatorio.']
        ];

        $this->validatorMock
            ->method('validate')
            ->willThrowException(new ValidationException($validationErrors));

        $this->requestMock->method('getParsedBody')->willReturn($invalidPayload);

        $this->expectException(ValidationException::class);

        $action = new RegisterAction(
            $this->pdoMock,
            $this->validatorMock,
            $this->userCrudRepoMock,
            $this->passwordResetRepoMock,
            $this->mailRegisterServiceMock
        );

        $action($this->requestMock, $this->responseMock);
    }
}
