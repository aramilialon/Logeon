<?php

declare(strict_types=1);

namespace App\Services;

class MailRuntimeService
{
    public static function maybeTick(int $limit = 2, int $oneEvery = 5): void
    {
        $oneEvery = max(1, $oneEvery);

        try {
            if ($oneEvery > 1 && random_int(1, $oneEvery) !== 1) {
                return;
            }

            $campaigns = new MailCampaignService();
            $campaigns->processScheduled();
            MailQueueService::tick($limit);
        } catch (\Throwable) {
            // Il runtime mail non deve interrompere la request principale.
        }
    }
}
