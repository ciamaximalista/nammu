<?php

namespace Nammu\Core;

class MarkdownConverter
{
    private const TOC_TOKEN = '__NAMMU_TOC_PLACEHOLDER__';

    public function toHtml(string $markdown, bool $renderToc = true): string
    {
        $document = $this->convertDocument($markdown, $renderToc);
        return $document['html'];
    }

    /**
     * @return array{html: string, headings: array<int, array<string, mixed>>, toc_html: string, has_manual_toc: bool}
     */
    public function convertDocument(string $markdown, bool $renderToc = true): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
        if ($markdown === '') {
            return [
                'html' => '',
                'headings' => [],
                'toc_html' => '',
                'has_manual_toc' => false,
            ];
        }

        $lines = explode("\n", $markdown);
        $html = [];
        $paragraphBuffer = [];
        $inCodeBlock = false;
        $inBlockquote = false;
        $codeBuffer = [];
        $codeLanguage = '';
        $blockquoteBuffer = [];
        $headings = [];
        $headingSlugCounts = [];
        $tocRequested = false;

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

            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
                $flushParagraph();
                $closeAllLists();
                $flushBlockquote();
                $html[] = '<hr />';
                continue;
            }

            $youtubeId = $this->extractYoutubeId($trimmed);
            if ($youtubeId !== null) {
                $flushParagraph();
                $closeAllLists();
                $flushBlockquote();
                $html[] = $this->renderYoutubeEmbed($youtubeId);
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                $closeAllLists();
                $flushBlockquote();
                $level = strlen($matches[1]);
                $content = $this->convertInline($matches[2]);
                $plainHeading = $this->plainTextFromHtml($content);
                $headingId = $this->generateHeadingId($plainHeading, $headingSlugCounts);
                $idAttr = $headingId !== '' ? ' id="' . htmlspecialchars($headingId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
                $html[] = "<h{$level}{$idAttr}>{$content}</h{$level}>";
                if ($level <= 4) {
                    $headingLabel = $plainHeading !== '' ? $plainHeading : 'Sección';
                    $headings[] = [
                        'level' => $level,
                        'text' => $headingLabel,
                        'id' => $headingId,
                    ];
                }
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

            if (strcasecmp($trimmed, '[toc]') === 0) {
                $flushParagraph();
                $closeAllLists();
                $tocRequested = true;
                $html[] = self::TOC_TOKEN;
                continue;
            }

            $paragraphBuffer[] = $line;
        }

        $flushParagraph();
        $closeAllLists();
        $flushBlockquote();
        $flushCodeBlock();

        $document = implode("\n", $html);
        $tocHtml = '';
        if ($tocRequested) {
            $tocHtml = $this->renderToc($headings);
            $replacement = $renderToc ? $tocHtml : '';
            $document = str_replace(self::TOC_TOKEN, $replacement, $document);
        }

        $document = $this->normalizeAssetPaths($document);

        return [
            'html' => $document,
            'headings' => $headings,
            'toc_html' => $tocHtml,
            'has_manual_toc' => $tocRequested,
        ];
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

            $placeholders = [];
            $placeholderIndex = 0;
            $storePlaceholder = function (string $html) use (&$placeholders, &$placeholderIndex): string {
                $token = '[[NAMMU|PLACEHOLDER|' . $placeholderIndex++ . ']]';
                $placeholders[$token] = $html;
                return $token;
            };

            $escaped = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($matches) use ($storePlaceholder) {
                $alt = $matches[1];
                $url = htmlspecialchars(trim($matches[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return $storePlaceholder('<img src="' . $url . '" alt="' . $alt . '">');
            }, $escaped);

            $escaped = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) use ($storePlaceholder) {
                $text = $this->applyInlineFormatting($matches[1]);
                $url = htmlspecialchars(trim($matches[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return $storePlaceholder('<a href="' . $url . '">' . $text . '</a>');
            }, $escaped);

            $escaped = preg_replace_callback('/`([^`]+)`/', function ($matches) use ($storePlaceholder) {
                return $storePlaceholder('<code>' . $matches[1] . '</code>');
            }, $escaped);

            $escaped = $this->applyInlineFormatting($escaped);

            // Auto-link plain URLs and emails
            $escaped = preg_replace_callback('/\bhttps?:\/\/[^\s<>"\']+/i', function ($matches) use ($storePlaceholder) {
                $url = $matches[0];
                return $storePlaceholder('<a href="' . $url . '">' . $url . '</a>');
            }, $escaped);

            $escaped = preg_replace_callback('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', function ($matches) use ($storePlaceholder) {
                $email = $matches[0];
                $mailto = 'mailto:' . $email;
                return $storePlaceholder('<a href="' . $mailto . '">' . $email . '</a>');
            }, $escaped);

            if (!empty($placeholders)) {
                $escaped = strtr($escaped, $placeholders);
            }

            $escaped = $this->convertSuperscript($escaped);

            $result .= $escaped;
        }

        return $result;
    }

    private function normalizeAssetPaths(string $html): string
    {
        return preg_replace('/((?:src|href)=["\'])(?!https?:|\/\/)(assets\/)/i', '$1/$2', $html) ?? $html;
    }

    private function applyInlineFormatting(string $text): string
    {
        $text = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/___(.+?)___/s', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/s', '<em>$1</em>', $text);
        $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text);

        return $text;
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

    private function plainTextFromHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text ?? '');
        return trim((string) $text);
    }

    private function generateHeadingId(string $text, array &$registry): string
    {
        $base = $this->slugifyHeading($text);
        if ($base === '') {
            $base = 'seccion';
        }
        $count = $registry[$base] ?? 0;
        $registry[$base] = $count + 1;
        return $count === 0 ? $base : $base . '-' . ($count + 1);
    }

    private function slugifyHeading(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (function_exists('transliterator_transliterate')) {
            $decoded = transliterator_transliterate('Any-Latin; Latin-ASCII', $decoded);
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $decoded);
            if ($converted !== false) {
                $decoded = $converted;
            }
        }
        $lower = function_exists('mb_strtolower') ? mb_strtolower($decoded, 'UTF-8') : strtolower($decoded);
        $lower = preg_replace('/[^a-z0-9]+/i', '-', $lower) ?? '';
        return trim($lower, '-');
    }

    public function buildToc(array $headings): string
    {
        return $this->renderToc($headings);
    }

    private function renderToc(array $headings): string
    {
        $filtered = array_values(array_filter($headings, static function ($heading) {
            return isset($heading['id'], $heading['text'], $heading['level'])
                && $heading['id'] !== ''
                && $heading['text'] !== ''
                && $heading['level'] >= 1
                && $heading['level'] <= 4;
        }));
        if (empty($filtered)) {
            return '';
        }

        $toc = '<nav class="nammu-toc" aria-label="Tabla de contenidos">';
        $currentLevel = 0;
        foreach ($filtered as $heading) {
            $level = max(1, min(4, (int) $heading['level']));
            if ($currentLevel === 0) {
                for ($i = 1; $i <= $level; $i++) {
                    $toc .= '<ol class="toc-level toc-level-' . $i . '">';
                }
            } elseif ($level > $currentLevel) {
                for ($i = $currentLevel + 1; $i <= $level; $i++) {
                    $toc .= '<ol class="toc-level toc-level-' . $i . '">';
                }
            } elseif ($level < $currentLevel) {
                for ($i = $currentLevel; $i > $level; $i--) {
                    $toc .= '</li></ol>';
                }
                $toc .= '</li>';
            } else {
                $toc .= '</li>';
            }
            $toc .= '<li><a href="#' . htmlspecialchars($heading['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                . htmlspecialchars($heading['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</a>';
            $currentLevel = $level;
        }

        for ($i = $currentLevel; $i >= 1; $i--) {
            $toc .= '</li></ol>';
        }

        $toc .= '</nav>';
        return $toc;
    }

    private function extractYoutubeId(string $text): ?string
    {
        $url = trim($text);
        if ($url === '') {
            return null;
        }
        $parsed = @parse_url($url);
        if ($parsed === false || !isset($parsed['host'])) {
            return null;
        }
        $host = strtolower($parsed['host']);
        $path = $parsed['path'] ?? '';
        $id = null;

        if (str_contains($host, 'youtube.com')) {
            if (strpos($path, '/watch') === 0 && isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
                if (!empty($query['v'])) {
                    $id = $query['v'];
                }
            } elseif (preg_match('#^/(embed|shorts)/([^/?]+)#', $path, $matches)) {
                $id = $matches[2];
            }
        } elseif ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        }

        if ($id === null || $id === '') {
            return null;
        }

        $id = preg_replace('/[^A-Za-z0-9_\-]/', '', $id);
        return $id !== '' ? $id : null;
    }

    private function renderYoutubeEmbed(string $videoId): string
    {
        $src = 'https://www.youtube.com/embed/' . htmlspecialchars($videoId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<div class="embedded-video"><iframe src="' . $src . '" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
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
