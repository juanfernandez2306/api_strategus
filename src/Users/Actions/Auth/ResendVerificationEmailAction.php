<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Services\Mail\MailRegisterInterface;
use App\Users\Validators\Auth\EmailRequestValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;
use RuntimeException;

class ResendVerificationEmailAction
{
    private const GENERIC_SUCCESS_MESSAGE = 'Si el correo electrónico está registrado"
                                            . " y pendiente de activación, recibirá un nuevo enlace de verificación.';

    private PDO $pdo;
    private UserRepositoryInterface $userRepo;
    private PasswordResetRepositoryInterface $passwordResetRepo;
    private MailRegisterInterface $mailRegisterService;
    private EmailRequestValidator $validator;
    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        MailRegisterInterface $mailRegisterService,
        EmailRequestValidator $validator,
        LoggerInterface $logger
    ) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->mailRegisterService = $mailRegisterService;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $validatedData = $this->validator->validate($data);

        $email = mb_strtolower($validatedData['email']);

        $user = $this->userRepo->findByEmail($email);

        if (empty($user) || $user['email_verified_at'] !== null) {
            return ApiResponse::json(
                $response,
                HttpStatus::OK,
                self::GENERIC_SUCCESS_MESSAGE
            );
        }

        $tokenPlain = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+12 hours'));

        $this->pdo->beginTransaction();
        try {
            $saved = $this->passwordResetRepo->save(
                (int) $user['id'],
                $tokenPlain,
                $expiresAt
            );

            if (!$saved) {
                throw new RuntimeException('Error al registrar el nuevo token de verificación.');
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            $this->logger->error('Error al registrar el token de verificación de correo', [
                'user_id' => $user['id'],
                'email'   => $email,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            throw $e;
        }

        $fullName = trim("{$user['first_name']} {$user['last_name']}");
        $this->mailRegisterService->send($email, $fullName, $tokenPlain);

        return ApiResponse::json(
            $response,
            HttpStatus::OK,
            self::GENERIC_SUCCESS_MESSAGE
        );
    }
}
