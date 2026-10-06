<?php

/**
 * Renderizado de plantillas (/templates, AGENTS.md §2).
 */

function templatePath(string $template): string
{
    return dirname(__DIR__, 2) . '/templates/' . $template;
}

function render(string $template, array $data = []): void
{
    $path = templatePath($template);

    if (!is_file($path)) {
        throw new RuntimeException('Plantilla no encontrada: ' . $template);
    }

    extract($data, EXTR_SKIP);
    require $path;
}

function partial(string $template, array $data = []): void
{
    render($template, $data);
}
