<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Modules\\Logeon\\Novelty\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = __DIR__ . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

return static function ($moduleRuntime = null, $moduleManifest = null): void {
    if (!class_exists('\\Core\\Hooks')) {
        return;
    }

    \Core\Hooks::add('twig.view_paths', static function ($paths) {
        if (!is_array($paths)) {
            $paths = [];
        }
        $viewPath = __DIR__ . '/views';
        if (!in_array($viewPath, $paths, true)) {
            $paths[] = $viewPath;
        }
        return $paths;
    });

    \Core\Hooks::add('twig.slot.admin.dashboard.news', static function ($fragments) {
        if (!is_array($fragments)) {
            $fragments = [];
        }
        $fragments[] = [
            'id' => 'novelty-admin-dashboard-page',
            'template' => 'novelty/admin/pages/news.twig',
            'after' => '',
            'before' => '',
            'data' => [],
        ];
        return $fragments;
    });

    \Core\Hooks::add('twig.slot.game.modals', static function ($fragments) {
        if (!is_array($fragments)) {
            $fragments = [];
        }
        $fragments[] = [
            'id' => 'novelty-game-modal-news',
            'template' => 'novelty/app/modals/news/news.twig',
            'after' => '',
            'before' => '',
            'data' => [],
        ];
        return $fragments;
    });

    \Core\Hooks::add('twig.slot.game.navbar.news_link', static function ($fragments) {
        if (!is_array($fragments)) {
            $fragments = [];
        }
        $fragments[] = [
            'id' => 'novelty-game-navbar-news-link',
            'template' => 'app/partials/news-navbar-link.twig',
            'after' => '',
            'before' => '',
            'data' => [],
        ];
        return $fragments;
    });

    \Core\Hooks::add('twig.slot.game.offcanvas.mobile.news_link', static function ($fragments) {
        if (!is_array($fragments)) {
            $fragments = [];
        }
        $fragments[] = [
            'id' => 'novelty-game-offcanvas-news-link',
            'template' => 'app/partials/news-offcanvas-link.twig',
            'after' => '',
            'before' => '',
            'data' => [],
        ];
        return $fragments;
    });

    \Core\Hooks::add('app.module_endpoints', static function ($endpoints) {
        if (!is_array($endpoints)) {
            $endpoints = [];
        }
        $endpoints['newsList'] = '/list/news';
        return $endpoints;
    });

    \Core\Hooks::add('landing.module_feed', static function ($feed, $limit = 6) {
        if (!is_array($feed)) {
            $feed = [];
        }
        $entries = [];
        try {
            $entries = (new \Modules\Logeon\Novelty\Services\NoveltyService())
                ->listForHomepageFeed((int) $limit);
        } catch (\Throwable $e) {
            $entries = [];
        }

        foreach ($entries as $entry) {
            $row = is_array($entry) ? $entry : (is_object($entry) ? (array) $entry : []);
            $feed[] = [
                'id' => (int) ($row['id'] ?? 0),
                'type' => 'news',
                'title' => (string) ($row['title'] ?? ''),
                'excerpt' => (string) ($row['excerpt'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
                'image' => (string) ($row['image'] ?? ''),
                'published_at' => (string) ($row['date_publish'] ?? ''),
                'detail_label' => 'News',
            ];
        }

        return $feed;
    });

    \Core\Hooks::add('character.settings.notification_preferences', static function ($preferences) {
        if (!is_array($preferences)) {
            $preferences = [];
        }
        $preferences[] = [
            'key' => 'news',
            'label' => 'News',
            'column' => 'notify_news',
        ];
        return $preferences;
    });

    \Core\Hooks::add('notifications.preference_column', static function ($column, $topic, $sourceType = null) {
        $topic = strtolower(trim((string) $topic));
        $sourceType = strtolower(trim((string) $sourceType));
        if ($topic === 'news_publish' || $sourceType === 'news') {
            return 'notify_news';
        }
        return $column;
    });

    \Core\Hooks::add('novelty.homepage_feed', static function ($feed, $limit = 6) {
        $fallback = is_array($feed) ? $feed : [];
        try {
            return (new \Modules\Logeon\Novelty\Services\NoveltyService())
                ->listForHomepageFeed((int) $limit);
        } catch (\Throwable $e) {
            return $fallback;
        }
    });
};
