<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Application\Settings\SettingsInterface;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Repositories\Auth\TokenRepositoryInterface;
use App\Users\Validators\Auth\LogoutValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;

class LogoutAction
{
    private PDO $pdo;
    private TokenRepositoryInterface $tokenRepo;
    private LogoutValidator $validator;
    private LoggerInterface $logger;

    /** @var array<string, mixed> */
    private array $cookieSettings;

    public function __construct(
        PDO $pdo,
        TokenRepositoryInterface $tokenRepo,
        LogoutValidator $validator,
        LoggerInterface $logger,
        SettingsInterface $settings
    ) {
        $this->pdo = $pdo;
        $this->tokenRepo = $tokenRepo;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->cookieSettings = $settings->get('cookie');
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $cookies = $request->getCookieParams();
        $cookieName = $this->cookieSettings['name'];
        $tokenPlain = $cookies[$cookieName] ?? null;

        if (empty($tokenPlain)) {
            $data = (array) ($request->getParsedBody() ?? []);
            $validatedData = $this->validator->validate($data);
            $tokenPlain = $validatedData['token'] ?? null;
        }

        if (!empty($tokenPlain)) {
            $this->pdo->beginTransaction();
            try {
                $this->tokenRepo->delete($tokenPlain);

                $this->pdo->commit();
            } catch (Exception $e) {
                $this->pdo->rollBack();

                $this->logger->error('Error durante el cierre de sesión del usuario', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $cookieExpire = time() - 3600;

        $cookieParts = [
            sprintf('%s=', $cookieName),
            'Expires=' . gmdate('D, d-M-Y H:i:s T', $cookieExpire),
            'Path=/',
        ];

        $domain = $this->cookieSettings['domain'] ?? '';
        if (!empty($domain)) {
            $cookieParts[] = 'Domain=' . $domain;
        }

        $isSecure = $this->cookieSettings['secure'] ?? false;
        if (!empty($isSecure)) {
            $cookieParts[] = 'Secure';
        }

        $cookieParts[] = 'HttpOnly';
        $cookieParts[] = 'SameSite=' . ($this->cookieSettings['samesite'] ?? 'Lax');

        $response = $response->withAddedHeader('Set-Cookie', implode('; ', $cookieParts));

        return ApiResponse::json(
            response: $response,
            statusCode: HttpStatus::OK,
            message: 'Cierre de sesión exitoso.'
        );
    }
}
