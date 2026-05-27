<?php

declare(strict_types=1);

namespace Modules\Logeon\Quests;

final class QuestModuleBootstrap
{
    public static function registerHooks(): void
    {
        if (!class_exists('\\Core\\Hooks')) {
            return;
        }

        \Core\Hooks::add('twig.view_paths', static function ($paths) {
            if (!is_array($paths)) {
                $paths = [];
            }
            $viewPath = __DIR__ . '/../views';
            if (!in_array($viewPath, $paths, true)) {
                $paths[] = $viewPath;
            }
            return $paths;
        });

        \Core\Hooks::add('twig.slot.game.modals', static function ($fragments) {
            if (!is_array($fragments)) {
                $fragments = [];
            }
            $fragments[] = [
                'id' => 'quests-game-modals',
                'template' => 'quests/app/modals/quests/quests.twig',
                'after' => '',
                'before' => '',
                'data' => [],
            ];
            return $fragments;
        });

        \Core\Hooks::add('twig.slot.game.navbar.organizations.after_bank', static function ($fragments) {
            if (!is_array($fragments)) {
                $fragments = [];
            }
            $fragments[] = [
                'id' => 'quests-game-navbar-link',
                'template' => 'app/layout/navbar-quests-link.twig',
                'after' => '',
                'before' => '',
                'data' => [],
            ];
            return $fragments;
        });

        \Core\Hooks::add('twig.slot.game.offcanvas.mobile.quests', static function ($fragments) {
            if (!is_array($fragments)) {
                $fragments = [];
            }
            $fragments[] = [
                'id' => 'quests-game-offcanvas-link',
                'template' => 'app/offcanvas/mobile-quests-link.twig',
                'after' => '',
                'before' => '',
                'data' => [],
            ];
            return $fragments;
        });

        \Core\Hooks::add('twig.slot.game.offcanvas.mobile.organizations.after', static function ($fragments) {
            if (!is_array($fragments)) {
                $fragments = [];
            }
            $fragments[] = [
                'id' => 'quests-game-offcanvas-link',
                'template' => 'app/offcanvas/mobile-quests-link.twig',
                'after' => '',
                'before' => '',
                'data' => [],
            ];
            return $fragments;
        });

        \Core\Hooks::add('twig.slot.game.home.quick_actions', static function ($fragments) {
            if (!is_array($fragments)) {
                $fragments = [];
            }
            $fragments[] = [
                'id' => 'quests-game-home-quick-action',
                'template' => 'app/home/quick-action-quest.twig',
                'after' => '',
                'before' => '',
                'data' => [],
            ];
            return $fragments;
        });

        \Core\Hooks::add('twig.slot.game.narrative_events.source_filter_options', static function ($fragments) {
            if (!is_array($fragments)) {
                $fragments = [];
            }
            $fragments[] = [
                'id' => 'quests-game-narrative-events-source-option',
                'template' => 'app/modals/narrative-events/source-filter-option.twig',
                'after' => '',
                'before' => '',
                'data' => [],
            ];
            return $fragments;
        });

        \Core\Hooks::add('twig.slot.admin.dashboard.quests', static function ($fragments) {
            if (!is_array($fragments)) {
                $fragments = [];
            }
            $fragments[] = [
                'id' => 'quests-admin-dashboard-page',
                'template' => 'quests/admin/pages/quests.twig',
                'after' => '',
                'before' => '',
                'data' => [],
            ];
            return $fragments;
        });

        \Core\Hooks::add('twig.slot.admin.narrative_events.type_filter_options', static function ($fragments) {
            if (!is_array($fragments)) {
                $fragments = [];
            }
            $fragments[] = [
                'id' => 'quests-admin-narrative-events-filter-type-option',
                'template' => 'admin/modals/narrative-events/type-option-quest-update.twig',
                'after' => '',
                'before' => '',
                'data' => [],
            ];
            return $fragments;
        });

        \Core\Hooks::add('twig.slot.admin.narrative_tags.entity_type_options', static function ($fragments) {
            if (!is_array($fragments)) {
                $fragments = [];
            }
            $fragments[] = [
                'id' => 'quests-admin-narrative-tags-entity-option',
                'template' => 'admin/modals/narrative-tags/entity-type-option-quest.twig',
                'after' => '',
                'before' => '',
                'data' => [],
            ];
            return $fragments;
        });

        \Core\Hooks::add('landing.metrics', static function ($metrics, $db = null) {
            if (!is_array($metrics)) {
                $metrics = [];
            }

            $connection = $db;
            if (!$connection instanceof \Core\Database\DbAdapterInterface) {
                $connection = \Core\Database\DbAdapterFactory::createFromConfig();
            }

            try {
                $row = $connection->fetchOnePrepared(
                    'SELECT COUNT(*) AS cnt
                     FROM quest_instances
                     WHERE current_status = ?
                       AND completed_at IS NOT NULL
                       AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                    ['completed'],
                );
                $metrics['module_activity_week'] = (int) ($row->cnt ?? 0);
            } catch (\Throwable $e) {
                $metrics['module_activity_week'] = (int) ($metrics['module_activity_week'] ?? 0);
            }

            return $metrics;
        });

        \Core\Hooks::add('system_event.delete.related', static function ($eventId, $db = null) {
            $systemEventId = (int) $eventId;
            if ($systemEventId <= 0) {
                return;
            }

            $connection = $db;
            if (!$connection instanceof \Core\Database\DbAdapterInterface) {
                $connection = \Core\Database\DbAdapterFactory::createFromConfig();
            }

            try {
                $connection->executePrepared(
                    'DELETE FROM system_event_quest_links WHERE system_event_id = ?',
                    [$systemEventId],
                );
            } catch (\Throwable $e) {
                // no-op: cleanup best effort
            }
        });

        \Core\Hooks::add('narrative_tags.entity_type_aliases', static function ($aliases) {
            if (!is_array($aliases)) {
                $aliases = [];
            }
            $aliases['quest'] = 'quest_definition';
            $aliases['quests'] = 'quest_definition';
            $aliases['quest_definition'] = 'quest_definition';
            $aliases['quest_definitions'] = 'quest_definition';
            return $aliases;
        });

        \Core\Hooks::add('narrative_tags.entity_exists', static function ($exists, $entityType, $entityId, $db = null) {
            if ($exists === true) {
                return true;
            }
            if (strtolower(trim((string) $entityType)) !== 'quest_definition') {
                return $exists;
            }

            $id = (int) $entityId;
            if ($id <= 0) {
                return false;
            }

            $connection = $db;
            if (!$connection instanceof \Core\Database\DbAdapterInterface) {
                $connection = \Core\Database\DbAdapterFactory::createFromConfig();
            }

            try {
                $row = $connection->fetchOnePrepared('SELECT id FROM quest_definitions WHERE id = ? LIMIT 1', [$id]);
                return !empty($row);
            } catch (\Throwable $e) {
                return false;
            }
        });

        \Core\Hooks::add('narrative_tags.search_entities', static function ($rows, $entityType, $query = '', $limit = 20, $db = null) {
            if (strtolower(trim((string) $entityType)) !== 'quest_definition') {
                return $rows;
            }

            $needle = trim((string) $query);
            $max = max(1, min(50, (int) $limit));
            $connection = $db;
            if (!$connection instanceof \Core\Database\DbAdapterInterface) {
                $connection = \Core\Database\DbAdapterFactory::createFromConfig();
            }

            try {
                if ($needle !== '') {
                    $like = '%' . $needle . '%';
                    return $connection->fetchAllPrepared(
                        'SELECT q.id, q.title AS label, q.slug AS secondary
                         FROM quest_definitions q
                         WHERE q.title LIKE ? OR q.slug LIKE ?
                         ORDER BY q.title ASC LIMIT ?',
                        [$like, $like, $max],
                    );
                }

                return $connection->fetchAllPrepared(
                    'SELECT q.id, q.title AS label, q.slug AS secondary
                     FROM quest_definitions q
                     ORDER BY q.title ASC LIMIT ?',
                    [$max],
                );
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    public static function bootstrapTriggers(): void
    {
        if (class_exists('\\App\\Services\\QuestTriggerService')) {
            \App\Services\QuestTriggerService::bootstrap();
        }
    }
}
