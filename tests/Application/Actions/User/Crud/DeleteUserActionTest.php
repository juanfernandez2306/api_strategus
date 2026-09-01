<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User\Crud;

use App\Shared\Http\HttpStatus;
use App\Users\Actions\Crud\DeleteUserAction;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Crud\DeleteUserValidator;
use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class DeleteUserActionTest extends TestCase
{
    private MockObject&UserRepositoryInterface $userRepoMock;
    private MockObject&LoggerInterface $loggerMock;
    private MockObject&DeleteUserValidator $validatorMock;
    private DeleteUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepoMock = $this->createMock(UserRepositoryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->validatorMock = $this->createMock(DeleteUserValidator::class);

        $this->action = new DeleteUserAction(
            $this->userRepoMock,
            $this->loggerMock,
            $this->validatorMock
        );
    }

    public function testEliminaUsuarioExitosamente(): void
    {
        $userId = 15;
        $args = ['id' => (string) $userId];
        $validatedData = ['id' => $userId];

        $request = $this->createMock(ServerRequestInterface::class);
        $response = new Response();

        $this->validatorMock
            ->expects($this->once())
            ->method('validate')
            ->with(['id' => (string) $userId])
            ->willReturn($validatedData);

        $currentUser = [
            'id'        => $userId,
            'email'     => 'usuario@ejemplo.com',
            'is_active' => 1,
        ];

        $this->userRepoMock
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($currentUser);

        $this->userRepoMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId)
            ->willReturn(true);

        $resultResponse = ($this->action)($request, $response, $args);

        $this->assertInstanceOf(ResponseInterface::class, $resultResponse);
        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'Usuario eliminado correctamente.',
            (string) $resultResponse->getBody()
        );
    }

    public function testRetornaNotFoundSiElUsuarioNoExiste(): void
    {
        $userId = 999;
        $args = ['id' => (string) $userId];
        $validatedData = ['id' => $userId];

        $request = $this->createMock(ServerRequestInterface::class);
        $response = new Response();

        $this->validatorMock
            ->expects($this->once())
            ->method('validate')
            ->willReturn($validatedData);

        $this->userRepoMock
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn([]);

        $resultResponse = ($this->action)($request, $response, $args);

        $this->assertEquals(HttpStatus::NOT_FOUND, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'El usuario especificado no existe.',
            (string) $resultResponse->getBody()
        );
    }

    public function testRetornaBadRequestSiLaEliminacionRetornaFalse(): void
    {
        $userId = 20;
        $args = ['id' => (string) $userId];
        $validatedData = ['id' => $userId];

        $request = $this->createMock(ServerRequestInterface::class);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($validatedData);
        $this->userRepoMock->method('findById')->willReturn(['id' => $userId]);

        $this->userRepoMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId)
            ->willReturn(false);

        $resultResponse = ($this->action)($request, $response, $args);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'No se pudo procesar la eliminación del usuario.',
            (string) $resultResponse->getBody()
        );
    }

    public function testRetornaConflictSiExisteViolacionDeClaveForanea(): void
    {
        $userId = 5;
        $args = ['id' => (string) $userId];
        $validatedData = ['id' => $userId];

        $request = $this->createMock(ServerRequestInterface::class);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($validatedData);
        $this->userRepoMock->method('findById')->willReturn(['id' => $userId]);

        $pdoException = new class ('Integrity constraint violation') extends PDOException {
            protected $code = '23000';
        };

        $this->userRepoMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId)
            ->willThrowException($pdoException);

        $resultResponse = ($this->action)($request, $response, $args);

        $this->assertEquals(HttpStatus::CONFLICT, $resultResponse->getStatusCode());
        $this->assertStringContainsString(
            'tiene registros de actividad vinculados en el sistema.',
            (string) $resultResponse->getBody()
        );
    }

    public function testEscribeLogYReLanzaExcepcionAnteOtroErrorPDO(): void
    {
        $userId = 8;
        $args = ['id' => (string) $userId];
        $validatedData = ['id' => $userId];

        $request = $this->createMock(ServerRequestInterface::class);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($validatedData);
        $this->userRepoMock->method('findById')->willReturn(['id' => $userId]);

        $pdoException = new PDOException('Database offline', 500);

        $this->userRepoMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId)
            ->willThrowException($pdoException);

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                'Error de base de datos al intentar eliminar usuario',
                $this->callback(fn(array $ctx) => $ctx['target_user_id'] === $userId)
            );

        $this->expectException(PDOException::class);

        ($this->action)($request, $response, $args);
    }
}
