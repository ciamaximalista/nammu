<?php

namespace Nammu\Core;

class MarkdownConverter
{
    public function toHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
        if ($markdown === '') {
            return '';
        }

        $lines = explode("\n", $markdown);
        $html = [];
        $paragraphBuffer = [];
        $inList = false;
        $inCodeBlock = false;
        $inBlockquote = false;
        $codeBuffer = [];
        $codeLanguage = '';
        $blockquoteBuffer = [];

        $flushParagraph = function () use (&$paragraphBuffer, &$html) {
            if (empty($paragraphBuffer)) {
                return;
            }

            $text = trim(implode(' ', $paragraphBuffer));
            if ($text !== '') {
                $html[] = '<p>' . $this->convertInline($text) . '</p>';
            }
            $paragraphBuffer = [];
        };

        $closeList = function () use (&$inList, &$html) {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
        };

        $flushBlockquote = function () use (&$blockquoteBuffer, &$html, &$inBlockquote) {
            if (!$inBlockquote) {
                return;
            }
            $content = implode("\n", $blockquoteBuffer);
            $content = trim($content);
            if ($content === '') {
                $blockquoteBuffer = [];
                $inBlockquote = false;
                return;
            }
            $segments = preg_split("/\n{2,}/", $content);
            $parts = [];
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }
                $parts[] = '<p>' . $this->convertInline($segment) . '</p>';
            }
            if (empty($parts)) {
                $parts[] = '<p>' . $this->convertInline($content) . '</p>';
            }
            $html[] = '<blockquote>' . implode("\n", $parts) . '</blockquote>';
            $blockquoteBuffer = [];
            $inBlockquote = false;
        };

        $flushCodeBlock = function () use (&$codeBuffer, &$codeLanguage, &$html, &$inCodeBlock) {
            if (!$inCodeBlock) {
                return;
            }
            $code = implode("\n", $codeBuffer);
            $escaped = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $classAttr = $codeLanguage !== '' ? ' class="language-' . htmlspecialchars($codeLanguage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
            $html[] = '<pre><code' . $classAttr . '>' . $escaped . '</code></pre>';
            $codeBuffer = [];
            $codeLanguage = '';
            $inCodeBlock = false;
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^```(?:(.+))?$/', $trimmed, $matches)) {
                if ($inCodeBlock) {
                    $flushCodeBlock();
                } else {
                    $flushParagraph();
                    $closeList();
                    $flushBlockquote();
                    $inCodeBlock = true;
                    $codeBuffer = [];
                    $codeLanguage = isset($matches[1]) ? trim($matches[1]) : '';
                }
                continue;
            }

            if ($inCodeBlock) {
                $codeBuffer[] = rtrim($line, "\n");
                continue;
            }

            if ($trimmed === '') {
                if ($inBlockquote) {
                    $blockquoteBuffer[] = '';
                    continue;
                }
                $flushParagraph();
                $closeList();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                $closeList();
                $flushBlockquote();
                $level = strlen($matches[1]);
                $content = $this->convertInline($matches[2]);
                $html[] = "<h{$level}>{$content}</h{$level}>";
                continue;
            }

            if (preg_match('/^[-*+]\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                $flushBlockquote();
                if (!$inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>' . $this->convertInline($matches[1]) . '</li>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $matches)) {
                $flushParagraph();
                $closeList();
                if (!$inBlockquote) {
                    $inBlockquote = true;
                    $blockquoteBuffer = [];
                }
                $blockquoteBuffer[] = $matches[1];
                continue;
            }

            if ($inBlockquote) {
                $flushBlockquote();
            }

            $paragraphBuffer[] = $line;
        }

        $flushParagraph();
        $closeList();
        $flushBlockquote();
        $flushCodeBlock();

        return implode("\n", $html);
    }

    private function convertInline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $escaped = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($matches) {
            $alt = $matches[1];
            $url = htmlspecialchars(trim($matches[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<img src="' . $url . '" alt="' . $alt . '">';
        }, $escaped);

        $escaped = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) {
            $text = $matches[1];
            $url = htmlspecialchars(trim($matches[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<a href="' . $url . '">' . $text . '</a>';
        }, $escaped);

        $escaped = preg_replace_callback('/`([^`]+)`/', function ($matches) {
            return '<code>' . $matches[1] . '</code>';
        }, $escaped);

        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped);
        $escaped = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/_(.+?)_/s', '<em>$1</em>', $escaped);

        return $escaped;
    }
}
