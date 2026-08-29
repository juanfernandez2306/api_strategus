<?php

declare(strict_types=1);

namespace App\Shared\View;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigTemplateRenderer
{
    private Environment $twig;

    public function __construct(?string $viewsPath = null)
    {
        $path = $viewsPath ?? dirname(__DIR__, 2) . '/Views';
        $loader = new FilesystemLoader($path);

        $this->twig = new Environment($loader, [
            'cache' => false,
            'auto_reload' => true,
        ]);
    }

    public function render(string $view, array $data = []): string
    {
        $normalizedView = str_replace('.', '/', $view);

        if (!str_ends_with($normalizedView, '.twig') && !str_ends_with($normalizedView, '.html')) {
            $normalizedView .= '.html.twig';
        }

        return $this->twig->render($normalizedView, $data);
    }
}
