<?php

declare(strict_types=1);

namespace App\Services;

class MailRendererService
{
    private const VARIABLE_WHITELIST = [
        'user.username',
        'user.email',
        'character.name',
        'character.full_name',
        'app.name',
        'current_year',
        'unsubscribe_url',
        'preferences_url',
    ];

    public static function allowedVariables(): array
    {
        return self::VARIABLE_WHITELIST;
    }

    public static function extractVariables(string $content): array
    {
        if ($content === '') {
            return [];
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9._-]+)\s*\}\}/', $content, $matches);
        $variables = isset($matches[1]) && is_array($matches[1]) ? $matches[1] : [];
        $variables = array_map(static fn (string $name): string => trim($name), $variables);
        $variables = array_filter($variables, static fn (string $name): bool => $name !== '');

        return array_values(array_unique($variables));
    }

    public static function validateTemplate(string $subject, string $html, string $category): array
    {
        $errors = [];
        $allowed = self::allowedVariables();

        if (trim($subject) === '') {
            $errors[] = "L'oggetto email è obbligatorio.";
        }

        $variables = array_unique(array_merge(
            self::extractVariables($subject),
            self::extractVariables($html),
        ));

        foreach ($variables as $variable) {
            if (!in_array($variable, $allowed, true)) {
                $errors[] = 'Variabile template non supportata: {{ ' . $variable . ' }}.';
            }
        }

        if ($category === 'newsletter') {
            $hasUnsubscribe = in_array('unsubscribe_url', $variables, true);
            if (!$hasUnsubscribe) {
                $errors[] = 'Le newsletter devono includere {{ unsubscribe_url }}.';
            }
        }

        return $errors;
    }

    /**
     * Sostituisce i placeholder {{ var }} nel corpo HTML con i valori forniti.
     * Solo le variabili in whitelist vengono sostituite; le altre rimangono intatte.
     * I valori sono escaped per HTML.
     */
    public static function renderHtml(string $html, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (!in_array($key, self::VARIABLE_WHITELIST, true)) {
                continue;
            }
            $safe = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $html = str_replace('{{ ' . $key . ' }}', $safe, $html);
            $html = str_replace('{{' . $key . '}}', $safe, $html);
        }
        return $html;
    }

    /**
     * Sostituisce i placeholder nell'oggetto email (nessun escaping HTML).
     */
    public static function renderSubject(string $subject, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (!in_array($key, self::VARIABLE_WHITELIST, true)) {
                continue;
            }
            $subject = str_replace('{{ ' . $key . ' }}', (string) $value, $subject);
            $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
        }
        return $subject;
    }

    /**
     * Costruisce l'array di variabili di default per un utente/personaggio.
     * Dati non disponibili vengono lasciati come stringa vuota.
     *
     * @param string $userUsername
     * @param string $userEmail
     * @param string $characterName
     * @param string $characterFullName
     */
    public static function buildVars(
        string $userUsername = '',
        string $userEmail = '',
        string $characterName = '',
        string $characterFullName = '',
        array $overrides = [],
    ): array {
        $appName = defined('APP') ? (string) constant('APP')['name'] : 'Logeon';
        $baseUrl = defined('APP') ? rtrim((string) constant('APP')['baseurl'], '/') : '';

        $vars = [
            'user.username' => $userUsername,
            'user.email' => $userEmail,
            'character.name' => $characterName,
            'character.full_name' => $characterFullName,
            'app.name' => $appName,
            'current_year' => date('Y'),
            'unsubscribe_url' => $baseUrl . '/game/settings',
            'preferences_url' => $baseUrl . '/game/settings',
        ];

        foreach ($overrides as $key => $value) {
            if (!in_array((string) $key, self::VARIABLE_WHITELIST, true)) {
                continue;
            }
            $vars[(string) $key] = (string) $value;
        }

        return $vars;
    }

    /**
     * Genera il plain-text da HTML (strip tags + normalizzazione spazi).
     */
    public static function htmlToText(string $html): string
    {
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html));
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim((string) $text);
    }
}
