<?php

namespace Nammu\Core;

use RuntimeException;

class TemplateRenderer
{
    private string $templateDir;
    private array $globals = [];

    public function __construct(string $templateDir, array $globals = [])
    {
        if (!is_dir($templateDir)) {
            throw new RuntimeException("Template directory '{$templateDir}' not found");
        }

        $this->templateDir = rtrim($templateDir, '/');
        $this->globals = $globals;
    }

    public function setGlobal(string $key, mixed $value): void
    {
        $this->globals[$key] = $value;
    }

    public function render(string $template, array $data = []): string
    {
        $path = "{$this->templateDir}/{$template}.php";
        if (!is_file($path)) {
            throw new RuntimeException("Template '{$template}' not found in {$this->templateDir}");
        }

        $vars = array_merge($this->globals, $data);
        extract($vars, EXTR_SKIP);

        ob_start();
        include $path;
        return (string) ob_get_clean();
    }
}

