<?php

declare(strict_types=1);

namespace Core;

class HtmlSanitizer
{
    private static function defaultAllowedTags(): array
    {
        return [
            'p' => ['style'],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            's' => [],
            'code' => [],
            'pre' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'blockquote' => ['style'],
            'h1' => ['style'],
            'h2' => ['style'],
            'h3' => ['style'],
            'h4' => ['style'],
            'h5' => ['style'],
            'h6' => ['style'],
            'hr' => [],
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'title', 'style'],
            'span' => ['class', 'style'],
            'div' => ['class', 'style'],
        ];
    }

    private static function defaultAllowedStyleProperties(): array
    {
        return [
            'background-color',
            'border-radius',
            'color',
            'display',
            'float',
            'font-style',
            'font-weight',
            'height',
            'margin',
            'margin-bottom',
            'margin-inline',
            'margin-left',
            'margin-right',
            'margin-top',
            'max-height',
            'max-width',
            'min-height',
            'min-width',
            'object-fit',
            'text-align',
            'text-decoration',
            'width',
        ];
    }

    private static function normalizeAllowedTags($options = []): array
    {
        $allowed = self::defaultAllowedTags();
        if (!empty($options['allowed_tags']) && is_array($options['allowed_tags'])) {
            $allowed = self::normalizeAllowedTagList($options['allowed_tags']);
        }
        if (isset($options['allow_images']) && $options['allow_images'] === false) {
            unset($allowed['img']);
        }

        return self::normalizeAllowedTagList($allowed);
    }

    public static function sanitize($html, $options = []): string
    {
        if ($html === null) {
            return '';
        }

        $allowed = self::normalizeAllowedTags($options);
        $html = (string) $html;
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        $html = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $html);

        if (!class_exists('\DOMDocument')) {
            return self::fallbackSanitize($html, $allowed);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $wrapped = '<div id="__root__">' . $html . '</div>';
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $dom->documentElement;
        if (!$root) {
            return self::fallbackSanitize($html, $allowed);
        }

        self::sanitizeNode($root, $allowed, $options);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return trim((string) $result);
    }

    private static function sanitizeNode($node, $allowed, $options): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if (!$child) {
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            if (!array_key_exists($tag, $allowed)) {
                self::unwrapNode($child);
                continue;
            }

            self::sanitizeAttributes($child, $tag, $allowed, $options);
            self::sanitizeNode($child, $allowed, $options);
        }
    }

    private static function unwrapNode($node): void
    {
        if (!$node || !$node->parentNode) {
            return;
        }

        while ($node->firstChild) {
            $node->parentNode->insertBefore($node->firstChild, $node);
        }
        $node->parentNode->removeChild($node);
    }

    private static function sanitizeAttributes($node, $tag, $allowed, $options): void
    {
        $allowedAttrs = $allowed[$tag] ?? [];
        if (!is_array($allowedAttrs)) {
            $allowedAttrs = [];
        }
        if (!$node->hasAttributes()) {
            return;
        }

        $toRemove = [];
        foreach ($node->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            $value = (string) $attr->nodeValue;

            if (strpos($name, 'on') === 0) {
                $toRemove[] = $name;
                continue;
            }

            if (!in_array($name, $allowedAttrs, true)) {
                $toRemove[] = $name;
                continue;
            }

            if ($name === 'href' || $name === 'src') {
                $safeUrl = self::sanitizeUrl($value, $options);
                if ($safeUrl === '') {
                    $toRemove[] = $name;
                } else {
                    $node->setAttribute($name, $safeUrl);
                }
                continue;
            }

            if ($name === 'class') {
                $safeClass = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $value);
                if ($safeClass === null || trim($safeClass) === '') {
                    $toRemove[] = $name;
                } else {
                    $node->setAttribute($name, trim($safeClass));
                }
                continue;
            }

            if ($name === 'style') {
                $safeStyle = self::sanitizeStyle($value, $tag, $options);
                if ($safeStyle === '') {
                    $toRemove[] = $name;
                } else {
                    $node->setAttribute($name, $safeStyle);
                }
            }
        }

        foreach ($toRemove as $attrName) {
            $node->removeAttribute($attrName);
        }

        if ($tag === 'a') {
            $target = strtolower((string) $node->getAttribute('target'));
            if ($target === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private static function sanitizeUrl($url, $options = []): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if ($url[0] === '/' || $url[0] === '#') {
            return $url;
        }
        if (strpos($url, './') === 0 || strpos($url, '../') === 0) {
            return $url;
        }

        $allowDataImage = !empty($options['allow_data_image']);
        if ($allowDataImage && stripos($url, 'data:image/') === 0) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null) {
            return '';
        }

        $scheme = strtolower($scheme);
        if (in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return $url;
        }

        return '';
    }

    private static function sanitizeStyle(string $style, string $tag, array $options = []): string
    {
        $style = trim($style);
        if ($style === '') {
            return '';
        }

        $allowed = self::normalizeAllowedStyleProperties($options);
        $declarations = preg_split('/\s*;\s*/', $style) ?: [];
        $safeDeclarations = [];

        foreach ($declarations as $declaration) {
            $declaration = trim((string) $declaration);
            if ($declaration === '' || strpos($declaration, ':') === false) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim((string) $property));
            if ($property === '' || !in_array($property, $allowed, true)) {
                continue;
            }

            $safeValue = self::sanitizeStyleValue($property, $value, $tag);
            if ($safeValue === '') {
                continue;
            }

            $safeDeclarations[] = $property . ': ' . $safeValue;
        }

        return implode('; ', $safeDeclarations);
    }

    private static function normalizeAllowedStyleProperties(array $options = []): array
    {
        $allowed = self::defaultAllowedStyleProperties();
        if (!empty($options['allowed_style_properties']) && is_array($options['allowed_style_properties'])) {
            $allowed = [];
            foreach ($options['allowed_style_properties'] as $property) {
                $property = strtolower(trim((string) $property));
                if ($property !== '') {
                    $allowed[] = $property;
                }
            }
        }

        return array_values(array_unique($allowed));
    }

    private static function sanitizeStyleValue(string $property, string $value, string $tag = ''): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/(?:expression|javascript:|behavior:|url\s*\()/i', $value)) {
            return '';
        }

        switch ($property) {
            case 'text-align':
                $value = strtolower($value);
                return in_array($value, ['left', 'center', 'right', 'justify', 'start', 'end'], true) ? $value : '';
            case 'float':
                $value = strtolower($value);
                return in_array($value, ['left', 'right', 'none'], true) ? $value : '';
            case 'display':
                $value = strtolower($value);
                return in_array($value, ['block', 'inline', 'inline-block', 'none'], true) ? $value : '';
            case 'font-style':
                $value = strtolower($value);
                return in_array($value, ['normal', 'italic', 'oblique'], true) ? $value : '';
            case 'font-weight':
                $value = strtolower($value);
                return preg_match('/^(normal|bold|bolder|lighter|[1-9]00)$/', $value) ? $value : '';
            case 'text-decoration':
                $value = strtolower(preg_replace('/\s+/', ' ', $value) ?? '');
                if ($value === '') {
                    return '';
                }
                $parts = explode(' ', $value);
                foreach ($parts as $part) {
                    if (!in_array($part, ['underline', 'line-through', 'overline', 'none'], true)) {
                        return '';
                    }
                }
                return implode(' ', array_values(array_unique($parts)));
            case 'color':
            case 'background-color':
                return self::sanitizeCssColor($value);
            case 'width':
            case 'height':
            case 'max-width':
            case 'max-height':
            case 'min-width':
            case 'min-height':
            case 'margin-top':
            case 'margin-bottom':
            case 'margin-left':
            case 'margin-right':
                return self::sanitizeCssLength($value);
            case 'margin':
            case 'margin-inline':
                return self::sanitizeCssSpacingShorthand($value);
            case 'border-radius':
                return self::sanitizeCssBorderRadius($value);
            case 'object-fit':
                $value = strtolower($value);
                return in_array($value, ['fill', 'contain', 'cover', 'none', 'scale-down'], true) ? $value : '';
        }

        return '';
    }

    private static function sanitizeCssColor(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }

        if (in_array($value, ['transparent', 'currentcolor', 'inherit', 'initial', 'unset'], true)) {
            return $value;
        }

        if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)) {
            return $value;
        }

        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\(\s*[-0-9.%\s,]+\)$/i', $value)) {
            return $value;
        }

        return preg_match('/^[a-z][a-z\-]{0,30}$/', $value) ? $value : '';
    }

    private static function sanitizeCssLength(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }

        if (in_array($value, ['auto', 'inherit', 'initial', 'unset'], true)) {
            return $value;
        }

        if ($value === '0') {
            return '0';
        }

        return preg_match('/^-?(?:\d+|\d*\.\d+)(?:px|%|em|rem|vw|vh)$/', $value) ? $value : '';
    }

    private static function sanitizeCssSpacingShorthand(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $value) ?: [];
        if (count($parts) < 1 || count($parts) > 4) {
            return '';
        }

        $safeParts = [];
        foreach ($parts as $part) {
            $safePart = self::sanitizeCssSpacingValue($part);
            if ($safePart === '') {
                return '';
            }
            $safeParts[] = $safePart;
        }

        return implode(' ', $safeParts);
    }

    private static function sanitizeCssSpacingValue(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }

        if (in_array($value, ['auto', 'inherit', 'initial', 'unset'], true)) {
            return $value;
        }

        if ($value === '0') {
            return '0';
        }

        return preg_match('/^-?(?:\d+|\d*\.\d+)(?:px|%|em|rem|vw|vh)$/', $value) ? $value : '';
    }

    private static function sanitizeCssBorderRadius(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $value) ?: [];
        if (count($parts) < 1 || count($parts) > 4) {
            return '';
        }

        $safeParts = [];
        foreach ($parts as $part) {
            if (in_array($part, ['inherit', 'initial', 'unset'], true)) {
                $safeParts[] = $part;
                continue;
            }

            if ($part === '0') {
                $safeParts[] = '0';
                continue;
            }

            if (!preg_match('/^(?:\d+|\d*\.\d+)(?:px|%|em|rem)$/', $part)) {
                return '';
            }

            $safeParts[] = $part;
        }

        return implode(' ', $safeParts);
    }

    private static function fallbackSanitize($html, $allowed): string
    {
        $tagList = '';
        foreach (array_keys($allowed) as $tag) {
            $tagList .= '<' . $tag . '>';
        }

        $clean = strip_tags($html, $tagList);
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $clean);
        $clean = preg_replace_callback(
            '/\s+style\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
            static function (array $matches): string {
                $value = $matches[2] ?? $matches[3] ?? $matches[4] ?? '';
                $safe = self::sanitizeStyle((string) $value, '');
                return $safe === '' ? '' : ' style="' . htmlspecialchars($safe, ENT_QUOTES, 'UTF-8') . '"';
            },
            $clean,
        );
        $clean = preg_replace('/javascript:/i', '', $clean);

        return trim((string) $clean);
    }

    private static function normalizeAllowedTagList(array $allowed): array
    {
        $normalized = [];
        foreach ($allowed as $tag => $attrs) {
            if (is_int($tag)) {
                $tagName = strtolower(trim((string) $attrs));
                if ($tagName !== '') {
                    $normalized[$tagName] = [];
                }
                continue;
            }

            $tagName = strtolower(trim((string) $tag));
            if ($tagName === '') {
                continue;
            }

            if (!is_array($attrs)) {
                $normalized[$tagName] = [];
                continue;
            }

            $attrList = [];
            foreach ($attrs as $attr) {
                $attrName = strtolower(trim((string) $attr));
                if ($attrName !== '') {
                    $attrList[] = $attrName;
                }
            }

            $normalized[$tagName] = array_values(array_unique($attrList));
        }

        return $normalized;
    }
}
