<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User\Register;

use App\Shared\Http\HttpStatus;
use App\Users\Actions\Auth\VerifyEmailAction;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Auth\VerifyEmailValidator;
use Faker\Factory as Faker;
use Faker\Generator;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class VerifyEmailActionTest extends TestCase
{
    private Generator $faker;
    private MockObject&PDO $pdoMock;
    private MockObject&PasswordResetRepositoryInterface $passwordResetRepoMock;
    private MockObject&UserRepositoryInterface $userRepoMock;
    private MockObject&VerifyEmailValidator $validatorMock;
    private VerifyEmailAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();
        $this->pdoMock = $this->createMock(PDO::class);
        $this->passwordResetRepoMock = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->userRepoMock = $this->createMock(UserRepositoryInterface::class);
        $this->validatorMock = $this->createMock(VerifyEmailValidator::class);

        $this->action = new VerifyEmailAction(
            $this->pdoMock,
            $this->passwordResetRepoMock,
            $this->userRepoMock,
            $this->validatorMock
        );
    }

    private function createRequestMock(array $queryParams): MockObject&ServerRequestInterface
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn($queryParams);

        return $requestMock;
    }

    public function testVerificaCorreoExitosamente(): void
    {
        $email = $this->faker->safeEmail();
        $tokenPlain = $this->faker->regexify('[a-f0-9]{64}');
        $userId = $this->faker->numberBetween(1, 100);

        $queryParams = ['email' => $email, 'token' => $tokenPlain];
        $request = $this->createRequestMock($queryParams);
        $response = new Response();

        $this->validatorMock
            ->expects($this->once())
            ->method('validate')
            ->with($queryParams)
            ->willReturn($queryParams);

        $resetRecord = [
            'id'                => $this->faker->numberBetween(1, 100),
            'user_id'           => $userId,
            'expires_at'        => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'email_verified_at' => null,
        ];

        $this->passwordResetRepoMock
            ->expects($this->once())
            ->method('findByEmailAndToken')
            ->with($email, $tokenPlain)
            ->willReturn($resetRecord);


        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->userRepoMock->expects($this->once())->method('markEmailAsVerified')->with($userId)->willReturn(true);
        $this->passwordResetRepoMock->expects($this->once())->method('deleteByUserId')->with($userId)->willReturn(true);
        $this->pdoMock->expects($this->once())->method('commit');

        $resultResponse = ($this->action)($request, $response);

        $this->assertInstanceOf(ResponseInterface::class, $resultResponse);
        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());

        $body = (string) $resultResponse->getBody();
        $this->assertStringContainsString('Correo electrónico verificado con éxito.', $body);
    }

    public function testRetornaNotFoundSiElTokenNoExiste(): void
    {
        $email = $this->faker->safeEmail();
        $tokenPlain = $this->faker->regexify('[a-f0-9]{64}');
        $queryParams = ['email' => $email, 'token' => $tokenPlain];

        $request = $this->createRequestMock($queryParams);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($queryParams);

        $this->passwordResetRepoMock
            ->expects($this->once())
            ->method('findByEmailAndToken')
            ->with($email, $tokenPlain)
            ->willReturn([]);

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::NOT_FOUND, $resultResponse->getStatusCode());
        $this->assertStringContainsString('El enlace de verificación es inválido o no existe.', (string) $resultResponse->getBody());
    }

    public function testRetornaOkSiElCorreoYaHabiaSidoVerificado(): void
    {
        $email = $this->faker->safeEmail();
        $tokenPlain = $this->faker->regexify('[a-f0-9]{64}');
        $queryParams = ['email' => $email, 'token' => $tokenPlain];

        $request = $this->createRequestMock($queryParams);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($queryParams);

        $resetRecord = [
            'id'                => 1,
            'user_id'           => 10,
            'expires_at'        => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'email_verified_at' => $this->faker->date('Y-m-d H:i:s'),
        ];

        $this->passwordResetRepoMock->method('findByEmailAndToken')->willReturn($resetRecord);

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());
        $this->assertStringContainsString('El correo electrónico ya ha sido verificado anteriormente.', (string) $resultResponse->getBody());
    }

    public function testRetornaBadRequestSiElTokenHaExpirado(): void
    {
        $email = $this->faker->safeEmail();
        $tokenPlain = $this->faker->regexify('[a-f0-9]{64}');
        $queryParams = ['email' => $email, 'token' => $tokenPlain];

        $request = $this->createRequestMock($queryParams);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($queryParams);

        $resetRecord = [
            'id'                => 1,
            'user_id'           => 10,
            'expires_at'        => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'email_verified_at' => null,
        ];

        $this->passwordResetRepoMock->method('findByEmailAndToken')->willReturn($resetRecord);

        $resultResponse = ($this->action)($request, $response);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $resultResponse->getStatusCode());
        $this->assertStringContainsString('El enlace de verificación ha expirado.', (string) $resultResponse->getBody());
    }

    public function testHaceRollbackSiFallaLaEliminacionDeTokens(): void
    {
        $email = $this->faker->safeEmail();
        $tokenPlain = $this->faker->regexify('[a-f0-9]{64}');
        $userId = 10;
        $queryParams = ['email' => $email, 'token' => $tokenPlain];

        $request = $this->createRequestMock($queryParams);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($queryParams);

        $resetRecord = [
            'id'                => 1,
            'user_id'           => $userId,
            'expires_at'        => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'email_verified_at' => null,
        ];

        $this->passwordResetRepoMock->method('findByEmailAndToken')->willReturn($resetRecord);

        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->userRepoMock->method('markEmailAsVerified')->with($userId)->willReturn(true);
        $this->passwordResetRepoMock->method('deleteByUserId')->with($userId)->willReturn(false); // Falla borrado
        $this->pdoMock->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error al procesar la verificación.');

        ($this->action)($request, $response);
    }
}
