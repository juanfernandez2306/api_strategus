<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User\Register;

use App\Shared\Http\HttpStatus;
use App\Users\Actions\Auth\ResendVerificationEmailAction;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Services\Mail\UserMailServiceInterface;
use App\Users\Validators\Auth\EmailRequestValidator;
use Faker\Factory as Faker;
use Faker\Generator;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Slim\Psr7\Response;

class ResendVerificationEmailActionTest extends TestCase
{
    private Generator $faker;
    private MockObject&PDO $pdoMock;
    private MockObject&UserRepositoryInterface $userRepoMock;
    private MockObject&PasswordResetRepositoryInterface $passwordResetRepoMock;
    private MockObject&UserMailServiceInterface $userMailServiceMock;
    private MockObject&EmailRequestValidator $validatorMock;
    private MockObject&LoggerInterface $loggerMock;
    private ResendVerificationEmailAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Inyección de entorno para evitar RuntimeException en la Action
        $_ENV['FRONTEND_URL'] = 'https://ejemplo.com';

        $this->faker = Faker::create();
        $this->pdoMock = $this->createMock(PDO::class);
        $this->userRepoMock = $this->createMock(UserRepositoryInterface::class);
        $this->passwordResetRepoMock = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->userMailServiceMock = $this->createMock(UserMailServiceInterface::class);
        $this->validatorMock = $this->createMock(EmailRequestValidator::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->action = new ResendVerificationEmailAction(
            $this->pdoMock,
            $this->userRepoMock,
            $this->passwordResetRepoMock,
            $this->userMailServiceMock,
            $this->validatorMock,
            $this->loggerMock
        );
    }

    private function createRequestMock(array $body): MockObject&ServerRequestInterface
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn($body);

        return $requestMock;
    }

    public function testReenviaCorreoExitosamente(): void
    {
        $email = $this->faker->safeEmail();
        $userId = $this->faker->numberBetween(1, 1000);
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $data = ['email' => $email];

        $request = $this->createRequestMock($data);
        $response = new Response();

        $this->validatorMock->expects($this->once())
            ->method('validate')
            ->with($data)
            ->willReturn($data);

        $userRecord = [
            'id'                => $userId,
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'email'             => $email,
            'email_verified_at' => null,
        ];

        $this->userRepoMock->expects($this->once())
            ->method('findByEmail')
            ->with(mb_strtolower($email))
            ->willReturn($userRecord);

        // Verificación de flujo transaccional PDO
        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->passwordResetRepoMock->expects($this->once())
            ->method('save')
            ->with(
                $this->equalTo($userId),
                $this->callback(fn($token) => is_string($token) && strlen($token) === 64),
                $this->isType('string')
            )
            ->willReturn(true);
        $this->pdoMock->expects($this->once())->method('commit');

        // 2. Verificación del envío de correo usando la estructura del nuevo contexto
        $expectedFullName = trim(ucfirst($firstName) . ' ' . ucfirst($lastName));
        $this->userMailServiceMock->expects($this->once())
            ->method('send')
            ->with($this->callback(function (array $context) use ($email, $expectedFullName) {
                return $context['toEmail'] === mb_strtolower($email)
                    && $context['userFullName'] === $expectedFullName
                    && $context['viewTemplate'] === 'Emails.VerifyEmail'
                    && isset($context['actionUrl']);
            }))
            ->willReturn(true);

        $resultResponse = ($this->action)($request, $response);

        $this->assertInstanceOf(ResponseInterface::class, $resultResponse);
        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'Si el correo electrónico está registrado',
            (string) $resultResponse->getBody()
        );
    }

    public function testRetornaRespuestaOpacaSiElUsuarioNoExiste(): void
    {
        $email = $this->faker->safeEmail();
        $data = ['email' => $email];

        $request = $this->createRequestMock($data);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($data);
        $this->userRepoMock->method('findByEmail')->willReturn([]);

        $this->pdoMock->expects($this->never())->method('beginTransaction');
        $this->userMailServiceMock->expects($this->never())->method('send');

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'Si el correo electrónico está registrado',
            (string) $resultResponse->getBody()
        );
    }

    public function testRetornaRespuestaOpacaSiElUsuarioYaEstaVerificado(): void
    {
        $email = $this->faker->safeEmail();
        $data = ['email' => $email];

        $request = $this->createRequestMock($data);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($data);

        $userRecord = [
            'id'                => $this->faker->numberBetween(1, 1000),
            'first_name'        => $this->faker->firstName(),
            'last_name'         => $this->faker->lastName(),
            'email'             => $email,
            'email_verified_at' => $this->faker->date('Y-m-d H:i:s'),
        ];

        $this->userRepoMock->method('findByEmail')->willReturn($userRecord);

        $this->pdoMock->expects($this->never())->method('beginTransaction');
        $this->userMailServiceMock->expects($this->never())->method('send');

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'Si el correo electrónico está registrado',
            (string) $resultResponse->getBody()
        );
    }

    public function testHaceRollbackYEscribeLogSiFallaAlGuardarToken(): void
    {
        $email = $this->faker->safeEmail();
        $userId = $this->faker->numberBetween(1, 1000);
        $data = ['email' => $email];

        $request = $this->createRequestMock($data);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($data);

        $userRecord = [
            'id'                => $userId,
            'first_name'        => $this->faker->firstName(),
            'last_name'         => $this->faker->lastName(),
            'email'             => $email,
            'email_verified_at' => null,
        ];

        $this->userRepoMock->method('findByEmail')->willReturn($userRecord);

        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->passwordResetRepoMock->method('save')->willReturn(false);
        $this->pdoMock->expects($this->once())->method('rollBack');

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error al registrar el token'),
                $this->callback(function (array $context) use ($userId, $email): bool {
                    return $context['user_id'] === $userId
                        && $context['email'] === mb_strtolower($email);
                })
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error al registrar el nuevo token de verificación.');

        ($this->action)($request, $response);
    }
}
