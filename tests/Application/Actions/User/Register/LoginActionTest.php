<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User\Register;

use App\Shared\Http\HttpStatus;
use App\Users\Actions\Auth\LoginAction;
use App\Users\Repositories\Auth\TokenRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Auth\LoginValidator;
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

class LoginActionTest extends TestCase
{
    private Generator $faker;
    private MockObject&PDO $pdoMock;
    private MockObject&UserRepositoryInterface $userRepoMock;
    private MockObject&TokenRepositoryInterface $tokenRepoMock;
    private MockObject&LoginValidator $validatorMock;
    private MockObject&LoggerInterface $loggerMock;
    private LoginAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();
        $this->pdoMock = $this->createMock(PDO::class);
        $this->userRepoMock = $this->createMock(UserRepositoryInterface::class);
        $this->tokenRepoMock = $this->createMock(TokenRepositoryInterface::class);
        $this->validatorMock = $this->createMock(LoginValidator::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->action = new LoginAction(
            $this->pdoMock,
            $this->userRepoMock,
            $this->tokenRepoMock,
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

    public function testIniciaSesionExitosamente(): void
    {
        $email = $this->faker->safeEmail();
        $password = 'Secret123!';
        $userId = $this->faker->numberBetween(1, 100);

        $bodyData = [
            'email'    => $email,
            'password' => $password,
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock
            ->expects($this->once())
            ->method('validate')
            ->with($bodyData)
            ->willReturn($bodyData);

        $user = [
            'id'                => $userId,
            'role_id'           => 1,
            'first_name'        => 'juan',
            'last_name'         => 'perez',
            'email'             => mb_strtolower($email),
            'password'          => password_hash($password, PASSWORD_BCRYPT),
            'is_active'         => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ];

        $this->userRepoMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with(mb_strtolower($email))
            ->willReturn($user);

        $this->pdoMock->expects($this->once())->method('beginTransaction');

        $this->tokenRepoMock
            ->expects($this->once())
            ->method('save')
            ->with(
                $userId,
                'auth_token',
                $this->callback(fn(string $token) => strlen($token) === 64)
            )
            ->willReturn(true);

        $this->pdoMock->expects($this->once())->method('commit');

        $resultResponse = ($this->action)($request, $response);

        $this->assertInstanceOf(ResponseInterface::class, $resultResponse);
        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());

        $body = (string) $resultResponse->getBody();
        $this->assertStringContainsString('Inicio de sesión exitoso.', $body);
        $this->assertStringContainsString('Juan Perez', $body);
    }

    public function testRetornaUnauthorizedSiElUsuarioNoExiste(): void
    {
        $bodyData = [
            'email'    => $this->faker->safeEmail(),
            'password' => 'Secret123!',
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($bodyData);

        $this->userRepoMock
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn([]);

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::UNAUTHORIZED, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'Las credenciales ingresadas son incorrectas.',
            (string) $resultResponse->getBody()
        );
    }

    public function testRetornaUnauthorizedSiLaContrasenaEsIncorrecta(): void
    {
        $email = $this->faker->safeEmail();

        $bodyData = [
            'email'    => $email,
            'password' => 'WrongPassword!',
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($bodyData);

        $user = [
            'id'                => 1,
            'email'             => mb_strtolower($email),
            'password'          => password_hash('Secret123!', PASSWORD_BCRYPT),
            'is_active'         => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ];

        $this->userRepoMock->method('findByEmail')->willReturn($user);

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::UNAUTHORIZED, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'Las credenciales ingresadas son incorrectas.',
            (string) $resultResponse->getBody()
        );
    }

    public function testRetornaForbiddenSiElUsuarioEstaInactivo(): void
    {
        $email = $this->faker->safeEmail();
        $password = 'Secret123!';

        $bodyData = [
            'email'    => $email,
            'password' => $password,
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($bodyData);

        $user = [
            'id'                => 1,
            'email'             => mb_strtolower($email),
            'password'          => password_hash($password, PASSWORD_BCRYPT),
            'is_active'         => 0,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ];

        $this->userRepoMock->method('findByEmail')->willReturn($user);

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::FORBIDDEN, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'Su cuenta se encuentra inactiva.',
            (string) $resultResponse->getBody()
        );
    }

    public function testRetornaForbiddenSiElEmailNoEstaVerificado(): void
    {
        $email = $this->faker->safeEmail();
        $password = 'Secret123!';

        $bodyData = [
            'email'    => $email,
            'password' => $password,
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($bodyData);

        $user = [
            'id'                => 1,
            'email'             => mb_strtolower($email),
            'password'          => password_hash($password, PASSWORD_BCRYPT),
            'is_active'         => 1,
            'email_verified_at' => null,
        ];

        $this->userRepoMock->method('findByEmail')->willReturn($user);

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::FORBIDDEN, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'Debe verificar su correo electrónico antes de iniciar sesión.',
            (string) $resultResponse->getBody()
        );
    }

    public function testHaceRollbackYEscribeLogSiFallaElGuardadoDelToken(): void
    {
        $email = $this->faker->safeEmail();
        $password = 'Secret123!';
        $userId = 15;

        $bodyData = [
            'email'    => $email,
            'password' => $password,
        ];

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($bodyData);

        $user = [
            'id'                => $userId,
            'email'             => mb_strtolower($email),
            'password'          => password_hash($password, PASSWORD_BCRYPT),
            'is_active'         => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ];

        $this->userRepoMock->method('findByEmail')->willReturn($user);

        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->tokenRepoMock->method('save')->willReturn(false);
        $this->pdoMock->expects($this->once())->method('rollBack');

        $assertContext = fn(array $context): bool =>
            $context['user_id'] === $userId && $context['email'] === mb_strtolower($email);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error durante el inicio de sesión del usuario'),
                $this->callback($assertContext)
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error al guardar el token de acceso.');

        ($this->action)($request, $response);
    }
}
