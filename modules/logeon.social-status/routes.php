<?php

declare(strict_types=1);

/** @var \Core\Router $route */
$ctrl = \Modules\Logeon\SocialStatus\Controllers\SocialStatuses::class;

$route->group('/admin', function ($route) use ($ctrl) {
    $route->apiPost('/characters/social-status', $ctrl . '@setCharacterSocialStatus');
    $route->apiPost('/social-status/list', $ctrl . '@listCharacterSocialStatus');
    $route->apiPost('/social-status/admin-list', $ctrl . '@adminList');
    $route->apiPost('/social-status/create', $ctrl . '@adminCreate');
    $route->apiPost('/social-status/update', $ctrl . '@adminUpdate');
    $route->apiPost('/social-status/delete', $ctrl . '@adminDelete');
});
