<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User\Register;

use App\Shared\Exceptions\ValidationException;
use App\Users\Actions\Auth\RegisterAction;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Services\Mail\UserMailServiceInterface;
use App\Users\Validators\Auth\RegisterValidator;
use Faker\Factory;
use Faker\Generator;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

class RegisterActionTest extends TestCase
{
    private Generator $faker;
    private PDO&MockObject $pdoMock;
    private RegisterValidator&MockObject $validatorMock;
    private UserRepositoryInterface&MockObject $userCrudRepoMock;
    private PasswordResetRepositoryInterface&MockObject $passwordResetRepoMock;
    private UserMailServiceInterface&MockObject $userMailServiceMock;
    private ServerRequestInterface&MockObject $requestMock;
    private ResponseInterface&MockObject $responseMock;
    private StreamInterface&MockObject $streamMock;
    private LoggerInterface&MockObject $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['FRONTEND_URL'] = 'https://ejemplo.com';

        $this->faker = Factory::create('es_ES');

        $this->pdoMock = $this->createMock(PDO::class);
        $this->validatorMock = $this->createMock(RegisterValidator::class);
        $this->userCrudRepoMock = $this->createMock(UserRepositoryInterface::class);
        $this->passwordResetRepoMock = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->userMailServiceMock = $this->createMock(UserMailServiceInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

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
        $this->userCrudRepoMock->method('create')->willReturn(1);
        $this->passwordResetRepoMock->method('save')->willReturn(true);

        // 2. Verificar que el método send reciba el contexto adecuado
        $this->userMailServiceMock
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (array $context) use ($dummyPayload) {
                return $context['toEmail'] === $dummyPayload['email']
                    && $context['viewTemplate'] === 'Emails.VerifyEmail'
                    && isset($context['actionUrl']);
            }))
            ->willReturn(true);

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
            $this->userMailServiceMock,
            $this->loggerMock
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
            $this->userMailServiceMock,
            $this->loggerMock
        );

        $action($this->requestMock, $this->responseMock);
    }
}
