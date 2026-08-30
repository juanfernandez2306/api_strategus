<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User\Register;

use App\Shared\Http\HttpStatus;
use App\Users\Actions\Auth\ResetPasswordAction;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Auth\ResetPasswordValidator;
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

class ResetPasswordActionTest extends TestCase
{
    private Generator $faker;
    private MockObject&PDO $pdoMock;
    private MockObject&PasswordResetRepositoryInterface $passwordResetRepoMock;
    private MockObject&UserRepositoryInterface $userRepoMock;
    private MockObject&ResetPasswordValidator $validatorMock;
    private MockObject&LoggerInterface $loggerMock;
    private ResetPasswordAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();
        $this->pdoMock = $this->createMock(PDO::class);
        $this->passwordResetRepoMock = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->userRepoMock = $this->createMock(UserRepositoryInterface::class);
        $this->validatorMock = $this->createMock(ResetPasswordValidator::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->action = new ResetPasswordAction(
            $this->pdoMock,
            $this->passwordResetRepoMock,
            $this->userRepoMock,
            $this->validatorMock,
            $this->loggerMock
        );
    }

    private function createRequestMock(array $parsedBody): MockObject&ServerRequestInterface
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn($parsedBody);

        return $requestMock;
    }

    public function testRestableceContrasenaExitosamente(): void
    {
        $email = $this->faker->safeEmail();
        $tokenPlain = $this->faker->regexify('[a-f0-9]{64}');
        $newPassword = 'Secret123!';
        $userId = $this->faker->numberBetween(1, 100);

        $bodyData = [
            'email'                 => $email,
            'token'                 => $tokenPlain,
            'password'              => $newPassword,
            'password_confirmation' => $newPassword,
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock
            ->expects($this->once())
            ->method('validate')
            ->with($bodyData)
            ->willReturn($bodyData);

        $resetRecord = [
            'id'         => $this->faker->numberBetween(1, 100),
            'user_id'    => $userId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        ];

        $this->passwordResetRepoMock
            ->expects($this->once())
            ->method('findByEmailAndToken')
            ->with(mb_strtolower($email), $tokenPlain)
            ->willReturn($resetRecord);

        $this->pdoMock->expects($this->once())->method('beginTransaction');

        $this->userRepoMock
            ->expects($this->once())
            ->method('updatePassword')
            ->with($userId, $newPassword)
            ->willReturn(true);

        $this->passwordResetRepoMock
            ->expects($this->once())
            ->method('deleteByUserId')
            ->with($userId)
            ->willReturn(true);

        $this->pdoMock->expects($this->once())->method('commit');

        $resultResponse = ($this->action)($request, $response);

        $this->assertInstanceOf(ResponseInterface::class, $resultResponse);
        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());

        $body = (string) $resultResponse->getBody();
        $this->assertStringContainsString('La contraseña ha sido actualizada con éxito.', $body);
    }

    public function testRetornaNotFoundSiElTokenOEmailNoExisten(): void
    {
        $email = $this->faker->safeEmail();
        $tokenPlain = $this->faker->regexify('[a-f0-9]{64}');
        $newPassword = 'Secret123!';

        $bodyData = [
            'email'                 => $email,
            'token'                 => $tokenPlain,
            'password'              => $newPassword,
            'password_confirmation' => $newPassword,
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($bodyData);

        $this->passwordResetRepoMock
            ->expects($this->once())
            ->method('findByEmailAndToken')
            ->with(mb_strtolower($email), $tokenPlain)
            ->willReturn([]);

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::NOT_FOUND, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'El enlace de restablecimiento es inválido o no existe.',
            (string) $resultResponse->getBody()
        );
    }

    public function testRetornaBadRequestSiElTokenHaExpirado(): void
    {
        $email = $this->faker->safeEmail();
        $tokenPlain = $this->faker->regexify('[a-f0-9]{64}');
        $newPassword = 'Secret123!';

        $bodyData = [
            'email'                 => $email,
            'token'                 => $tokenPlain,
            'password'              => $newPassword,
            'password_confirmation' => $newPassword,
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($bodyData);

        $resetRecord = [
            'id'         => 1,
            'user_id'    => 10,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ];

        $this->passwordResetRepoMock->method('findByEmailAndToken')->willReturn($resetRecord);

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'El enlace de restablecimiento ha expirado.',
            (string) $resultResponse->getBody()
        );
    }

    public function testHaceRollbackYEscribeLogSiFallaLaActualizacion(): void
    {
        $email = $this->faker->safeEmail();
        $tokenPlain = $this->faker->regexify('[a-f0-9]{64}');
        $newPassword = 'Secret123!';
        $userId = 10;

        $bodyData = [
            'email'                 => $email,
            'token'                 => $tokenPlain,
            'password'              => $newPassword,
            'password_confirmation' => $newPassword,
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($bodyData);

        $resetRecord = [
            'id'         => 1,
            'user_id'    => $userId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        ];

        $this->passwordResetRepoMock->method('findByEmailAndToken')->willReturn($resetRecord);

        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->userRepoMock->method('updatePassword')->with($userId, $newPassword)->willReturn(true);
        $this->passwordResetRepoMock->method('deleteByUserId')->with($userId)->willReturn(false);
        $this->pdoMock->expects($this->once())->method('rollBack');

        $assertContext = fn(array $context): bool =>
            $context['user_id'] === $userId && $context['email'] === mb_strtolower($email);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error al restablecer la contraseña del usuario'),
                $this->callback($assertContext)
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error al actualizar la contraseña.');

        ($this->action)($request, $response);
    }
}
