<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User\Crud;

use App\Shared\Http\HttpStatus;
use App\Users\Actions\Crud\UpdateUserAction;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Services\Mail\UserMailServiceInterface;
use App\Users\Validators\Crud\UpdateUserValidator;
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

class UpdateUserActionTest extends TestCase
{
    private Generator $faker;
    private MockObject&PDO $pdoMock;
    private MockObject&UserRepositoryInterface $userRepoMock;
    private MockObject&UpdateUserValidator $validatorMock;
    private MockObject&UserMailServiceInterface $mailServiceMock;
    private MockObject&LoggerInterface $loggerMock;
    private UpdateUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();
        $this->pdoMock = $this->createMock(PDO::class);
        $this->userRepoMock = $this->createMock(UserRepositoryInterface::class);
        $this->validatorMock = $this->createMock(UpdateUserValidator::class);
        $this->mailServiceMock = $this->createMock(UserMailServiceInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->action = new UpdateUserAction(
            $this->pdoMock,
            $this->userRepoMock,
            $this->validatorMock,
            $this->mailServiceMock,
            $this->loggerMock
        );
    }

    private function createRequestMock(array $parsedBody): MockObject&ServerRequestInterface
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn($parsedBody);

        return $requestMock;
    }

    public function testActualizaUsuarioYEnviaCorreoSiSeDesactiva(): void
    {
        $userId = 10;
        $args = ['id' => (string) $userId];

        $bodyData = [
            'first_name' => 'Carlos',
            'last_name'  => 'Mendoza',
            'is_active'  => 0,
        ];

        $validatedData = array_merge($bodyData, ['id' => $userId]);

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock
            ->expects($this->once())
            ->method('validate')
            ->with(array_merge($bodyData, ['id' => (string) $userId]))
            ->willReturn($validatedData);

        $currentUser = [
            'id'         => $userId,
            'first_name' => 'carlos',
            'last_name'  => 'mendoza',
            'email'      => 'carlos@ejemplo.com',
            'is_active'  => 1,
        ];

        $this->userRepoMock
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($currentUser);

        $this->pdoMock->expects($this->once())->method('beginTransaction');

        $this->userRepoMock
            ->expects($this->once())
            ->method('update')
            ->with($validatedData)
            ->willReturn(true);

        $this->pdoMock->expects($this->once())->method('commit');


        $this->mailServiceMock
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (array $mailData) {
                return $mailData['toEmail'] === 'carlos@ejemplo.com'
                    && $mailData['viewTemplate'] === 'Emails.AccountStatusChanged'
                    && $mailData['context']['isActive'] === false;
            }));

        $resultResponse = ($this->action)($request, $response, $args);

        $this->assertInstanceOf(ResponseInterface::class, $resultResponse);
        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());

        $body = (string) $resultResponse->getBody();
        $this->assertStringContainsString('Usuario actualizado exitosamente.', $body);
    }

    public function testActualizaUsuarioSinEnviarCorreoSiElEstadoNoCambia(): void
    {
        $userId = 5;
        $args = ['id' => (string) $userId];

        $bodyData = [
            'first_name' => 'Ana',
            'last_name'  => 'Gomez',
            'is_active'  => 1,
        ];

        $validatedData = array_merge($bodyData, ['id' => $userId]);

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($validatedData);

        $currentUser = [
            'id'         => $userId,
            'first_name' => 'ana',
            'last_name'  => 'gomez',
            'email'      => 'ana@ejemplo.com',
            'is_active'  => 1, // Permanece activo
        ];

        $this->userRepoMock->method('findById')->willReturn($currentUser);

        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->userRepoMock->method('update')->willReturn(true);
        $this->pdoMock->expects($this->once())->method('commit');

        $this->mailServiceMock->expects($this->never())->method('send');

        $resultResponse = ($this->action)($request, $response, $args);

        $this->assertEquals(HttpStatus::OK, $resultResponse->getStatusCode());
    }

    public function testRetornaNotFoundSiElUsuarioNoExiste(): void
    {
        $userId = 999;
        $args = ['id' => (string) $userId];

        $bodyData = [
            'first_name' => 'Juan',
            'last_name'  => 'Perez',
            'is_active'  => 1,
        ];

        $validatedData = array_merge($bodyData, ['id' => $userId]);

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($validatedData);

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

    public function testHaceRollbackYEscribeLogSiFallaLaActualizacion(): void
    {
        $userId = 12;
        $args = ['id' => (string) $userId];

        $bodyData = [
            'first_name' => 'Pedro',
            'last_name'  => 'Picapiedra',
            'is_active'  => 1,
        ];

        $validatedData = array_merge($bodyData, ['id' => $userId]);

        $request = $this->createRequestMock($bodyData);
        $response = new Response();

        $this->validatorMock->method('validate')->willReturn($validatedData);

        $currentUser = [
            'id'         => $userId,
            'first_name' => 'pedro',
            'last_name'  => 'picapiedra',
            'email'      => 'pedro@ejemplo.com',
            'is_active'  => 1,
        ];

        $this->userRepoMock->method('findById')->willReturn($currentUser);

        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->userRepoMock->method('update')->willReturn(false); // Simula fallo en BD
        $this->pdoMock->expects($this->once())->method('rollBack');

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                'Error al actualizar usuario por el administrador',
                $this->callback(fn(array $ctx) => $ctx['target_user_id'] === $userId)
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudieron actualizar los datos del usuario.');

        ($this->action)($request, $response, $args);
    }
}
