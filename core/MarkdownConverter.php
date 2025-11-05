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

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $flushParagraph();
                $closeList();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                $closeList();
                $level = strlen($matches[1]);
                $content = $this->convertInline($matches[2]);
                $html[] = "<h{$level}>{$content}</h{$level}>";
                continue;
            }

            if (preg_match('/^[-*+]\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                if (!$inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>' . $this->convertInline($matches[1]) . '</li>';
                continue;
            }

            $paragraphBuffer[] = $line;
        }

        $flushParagraph();
        $closeList();

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
