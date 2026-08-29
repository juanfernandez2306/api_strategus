<?php

use App\Shared\View\TwigTemplateRenderer;

if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        static $renderer = null;

        if ($renderer === null) {
            $renderer = new TwigTemplateRenderer();
        }

        return $renderer->render($view, $data);
    }
}
