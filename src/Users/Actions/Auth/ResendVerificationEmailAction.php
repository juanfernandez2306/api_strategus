<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Shared\DTOs\SendTemplatedMailDTO;
use App\Shared\Services\Mail\TemplatedMailServiceInterface;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Auth\EmailRequestValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;
use RuntimeException;
use App\Users\DTOs\Auth\UserDTO;

class ResendVerificationEmailAction
{
    private const GENERIC_SUCCESS_MESSAGE = 'Si el correo electrónico está registrado'
            . ' y pendiente de activación, recibirá un nuevo enlace de verificación.';

    private PDO $pdo;
    private UserRepositoryInterface $userRepo;
    private PasswordResetRepositoryInterface $passwordResetRepo;
    private TemplatedMailServiceInterface $templatedMailService;
    private EmailRequestValidator $validator;
    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        EmailRequestValidator $validator,
        LoggerInterface $logger,
        TemplatedMailServiceInterface $templatedMailService
    ) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->templatedMailService = $templatedMailService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $validatedData = $this->validator->validate($data);

        $email = mb_strtolower($validatedData['email']);

        $user = $this->userRepo->findByEmail($email);

        $userDTO = UserDTO::fromArray($user);

        if (empty($userDTO) || $userDTO->emailVerifiedAt !== null) {
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
                $userDTO->id,
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
                'user_id' => $userDTO->id,
                'email'   => $userDTO->email,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            throw $e;
        }

        $this->resendEmail(userDTO: $userDTO, tokenPlain: $tokenPlain);

        
        return ApiResponse::json(
            $response,
            HttpStatus::OK,
            self::GENERIC_SUCCESS_MESSAGE
        );
    }

    public function resendEmail(UserDTO $userDTO, string $tokenPlain): bool{

        $frontendUrl = $_ENV['FRONTEND_URL'];

        (empty($frontendUrl)) && throw new RuntimeException(
            "La variable de entorno 'FRONTEND_URL' no está definida o está vacía."
        );

        $verificationUrl = sprintf(
            '%s/verify/email?email=%s&token=%s',
            rtrim($frontendUrl, '/'),
            urlencode($userDTO->email),
            urlencode($tokenPlain)
        );

        

        $mailDTO = new SendTemplatedMailDTO(
            toEmail: $userDTO->email,
            toName: $userDTO->getFullName(),
            subject: 'Verifica tu cuenta - GESTIÓN PALMA DIGITAL',
            viewTemplate: 'Emails.VerifyEmail',
            viewData: [
                'userFullName' => $userDTO->getFullName(),
                'actionUrl'    => $verificationUrl,
            ]
        );

        return $this->templatedMailService->send($mailDTO);
    }
}
