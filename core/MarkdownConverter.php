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

        $listStack = [];

        $closeAllLists = function () use (&$listStack, &$html) {
            while (!empty($listStack)) {
                $list = array_pop($listStack);
                $html[] = "</{$list['tag']}>";
            }
        };

        $closeListsDeeperThan = function (int $indentLevel) use (&$listStack, &$html) {
            while (!empty($listStack)) {
                $lastIndex = array_key_last($listStack);
                if ($lastIndex === null) {
                    break;
                }
                if ($listStack[$lastIndex]['indent'] > $indentLevel) {
                    $list = array_pop($listStack);
                    $html[] = "</{$list['tag']}>";
                } else {
                    break;
                }
            }
        };

        $openList = function (string $type, int $indentLevel, ?int $startNumber = null) use (&$listStack, &$html) {
            $attributes = '';
            if ($type === 'ol') {
                if ($startNumber !== null && $startNumber !== 1) {
                    $attributes .= ' start="' . $startNumber . '"';
                }
                $typeAttr = $this->orderedListTypeForLevel($indentLevel);
                if ($typeAttr !== '1') {
                    $attributes .= ' type="' . $typeAttr . '"';
                }
            }
            $html[] = '<' . $type . $attributes . '>';
            $listStack[] = [
                'tag' => $type,
                'indent' => $indentLevel,
            ];
        };

        $ensureList = function (string $type, int $indentLevel, ?int $startNumber = null) use (&$listStack, $closeListsDeeperThan, $openList, &$html) {
            $currentIndent = empty($listStack) ? -1 : $listStack[array_key_last($listStack)]['indent'];
            $maxIndent = $currentIndent + 1;
            if (empty($listStack)) {
                $maxIndent = 0;
            }
            if ($indentLevel > $maxIndent) {
                $indentLevel = $maxIndent;
            }

            $closeListsDeeperThan($indentLevel);

            if (!empty($listStack)) {
                $lastIndex = array_key_last($listStack);
                if ($lastIndex !== null && $listStack[$lastIndex]['indent'] === $indentLevel && $listStack[$lastIndex]['tag'] !== $type) {
                    $list = array_pop($listStack);
                    $html[] = "</{$list['tag']}>";
                }
            }

            if (empty($listStack) || $listStack[array_key_last($listStack)]['indent'] < $indentLevel || $listStack[array_key_last($listStack)]['tag'] !== $type) {
                $openList($type, $indentLevel, $type === 'ol' ? $startNumber : null);
            }

            return $indentLevel;
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
                    $closeAllLists();
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
                $closeAllLists();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                $closeAllLists();
                $flushBlockquote();
                $level = strlen($matches[1]);
                $content = $this->convertInline($matches[2]);
                $html[] = "<h{$level}>{$content}</h{$level}>";
                continue;
            }

            if (preg_match('/^(\s*)(\d+)[\.\)]\s+(.+)$/', $line, $matches)) {
                $flushParagraph();
                $flushBlockquote();
                $indentLevel = $this->calculateIndentLevel($matches[1]);
                $indentLevel = $ensureList('ol', $indentLevel, (int) $matches[2]);
                $html[] = '<li>' . $this->convertInline($matches[3]) . '</li>';
                continue;
            }

            if (preg_match('/^(\s*)[-*+]\s+(.+)$/', $line, $matches)) {
                $flushParagraph();
                $flushBlockquote();
                $indentLevel = $this->calculateIndentLevel($matches[1]);
                $indentLevel = $ensureList('ul', $indentLevel);
                $html[] = '<li>' . $this->convertInline($matches[2]) . '</li>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $matches)) {
                $flushParagraph();
                $closeAllLists();
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
        $closeAllLists();
        $flushBlockquote();
        $flushCodeBlock();

        return implode("\n", $html);
    }

    private function convertInline(string $text): string
    {
        $segments = preg_split('/(<![^>]*>|<\/?[A-Za-z][^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false) {
            $segments = [$text];
        }

        $result = '';

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $isTag = isset($segment[0]) && $segment[0] === '<' && substr($segment, -1) === '>';
            if ($isTag) {
                $result .= $segment;
                continue;
            }

            $escaped = htmlspecialchars($segment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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

            $escaped = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $escaped);
            $escaped = preg_replace('/___(.+?)___/s', '<strong><em>$1</em></strong>', $escaped);
            $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
            $escaped = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $escaped);
            $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped);
            $escaped = preg_replace('/_(.+?)_/s', '<em>$1</em>', $escaped);
            $escaped = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $escaped);

            $escaped = $this->convertSuperscript($escaped);

            $result .= $escaped;
        }

        return $result;
    }

    private function convertSuperscript(string $text): string
    {
        $parts = preg_split('/(<code\b[^>]*>.*?<\/code>)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            $parts = [$text];
        }

        $result = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^<code\b/i', $part)) {
                $result .= $part;
                continue;
            }

            $converted = preg_replace_callback('/\^([^\^]+)\^/u', function ($matches) {
                return '<sup>' . $matches[1] . '</sup>';
            }, $part);

            $converted = preg_replace_callback('/\^([0-9]+|[A-Za-zÁÉÍÓÚáéíóúÑñºª]{1,3})/u', function ($matches) {
                return '<sup>' . $matches[1] . '</sup>';
            }, $converted);

            $result .= $converted;
        }

        return $result;
    }

    private function orderedListTypeForLevel(int $level): string
    {
        $types = ['1', 'a', 'i', 'A', 'I'];
        return $types[$level % count($types)];
    }

    private function calculateIndentLevel(string $whitespace): int
    {
        $indent = 0;
        $spaceCount = 0;
        $length = strlen($whitespace);
        for ($i = 0; $i < $length; $i++) {
            $char = $whitespace[$i];
            if ($char === "\t") {
                $indent++;
                $spaceCount = 0;
            } elseif ($char === ' ') {
                $spaceCount++;
                if ($spaceCount === 4) {
                    $indent++;
                    $spaceCount = 0;
                }
            } else {
                break;
            }
        }

        return $indent;
    }
}
