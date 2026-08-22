<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User;

use App\Users\Actions\Auth\RegisterAction;
use App\Users\Validators\Auth\RegisterValidator;
use App\Users\Repositories\Crud\UserCrudRepositoryInterface;
use App\Users\Repositories\Auth\InterfacePasswordResetRepository;
use App\Users\Services\Mail\InterfaceMailRegister;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use PDO;

class RegisterActionTest extends TestCase
{
    private PDO&MockObject $pdoMock;
    private RegisterValidator&MockObject $validatorMock;
    private UserCrudRepositoryInterface&MockObject $userCrudRepoMock;
    private InterfacePasswordResetRepository&MockObject $passwordResetRepoMock;
    private InterfaceMailRegister&MockObject $mailRegisterServiceMock;
    private ServerRequestInterface&MockObject $requestMock;
    private ResponseInterface&MockObject $responseMock;
    private StreamInterface&MockObject $streamMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdoMock = $this->createMock(PDO::class);
        $this->validatorMock = $this->createMock(RegisterValidator::class);
        $this->userCrudRepoMock = $this->createMock(UserCrudRepositoryInterface::class);
        $this->passwordResetRepoMock = $this->createMock(InterfacePasswordResetRepository::class);
        $this->mailRegisterServiceMock = $this->createMock(InterfaceMailRegister::class);

        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->responseMock = $this->createMock(ResponseInterface::class);
        $this->streamMock = $this->createMock(StreamInterface::class);
    }

    public function testExecuteRegisterActionSuccessfully(): void
    {
        $inputData = [
            'first_name'            => 'Juan',
            'last_name'             => 'Pérez',
            'email'                 => 'juan.perez@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!'
        ];

        // Configuración del validador mock[cite: 4]
        $this->validatorMock
            ->expects($this->once())
            ->method('validate')
            ->with($inputData)
            ->willReturn($inputData);

        // Expectativa de la transacción en BD
        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->pdoMock->expects($this->once())->method('commit');

        // Configuración de la creación del usuario (devuelve ID 15)[cite: 5, 6]
        $this->userCrudRepoMock
            ->expects($this->once())
            ->method('create')
            ->with($inputData)
            ->willReturn(15);

        // Configuración del guardado del token en password_resets[cite: 7, 8]
        $this->passwordResetRepoMock
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->equalTo(15),
                $this->callback(fn($token) => is_string($token) && strlen($token) === 64),
                $this->isType('string')
            )
            ->willReturn(true);

        // Configuración del envío de correo[cite: 9]
        $this->mailRegisterServiceMock
            ->expects($this->once())
            ->method('send')
            ->with(
                'juan.perez@example.com',
                'Juan Pérez',
                $this->callback(fn($token) => is_string($token) && strlen($token) === 64)
            )
            ->willReturn(true);

        // Mocking del Request y Response HTTP
        $this->requestMock
            ->method('getParsedBody')
            ->willReturn($inputData);

        $this->responseMock
            ->method('getBody')
            ->willReturn($this->streamMock);

        $this->responseMock
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $this->responseMock
            ->method('withStatus')
            ->with(201)
            ->willReturnSelf();

        // Instanciación y ejecución de la Action
        $action = new RegisterAction(
            $this->pdoMock,
            $this->validatorMock,
            $this->userCrudRepoMock,
            $this->passwordResetRepoMock,
            $this->mailRegisterServiceMock
        );

        $resultResponse = $action($this->requestMock, $this->responseMock);

        // Aserciones
        $this->assertInstanceOf(ResponseInterface::class, $resultResponse);
    }
}
