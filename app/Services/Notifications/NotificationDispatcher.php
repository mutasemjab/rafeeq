<?php

namespace App\Services\Notifications;

use App\Models\RafiqNotification;
use App\Models\User;
use App\Models\UserDevice;

class NotificationDispatcher
{
    private FirebaseMessagingService $firebaseMessaging;

    public function __construct(FirebaseMessagingService $firebaseMessaging)
    {
        $this->firebaseMessaging = $firebaseMessaging;
    }

    public function sendToUsers(iterable $users, string $type, string $title, string $body, array $data = [], bool $sendPush = true): array
    {
        $firebaseConfigured = $sendPush && $this->firebaseMessaging->isConfigured();
        $summary = [
            'user_count' => 0,
            'notification_count' => 0,
            'device_count' => 0,
            'push_success_count' => 0,
            'push_failure_count' => 0,
            'push_skipped' => ! $sendPush || ! $firebaseConfigured,
        ];

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $summary['user_count']++;

            RafiqNotification::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);

            $summary['notification_count']++;

            if (! $firebaseConfigured) {
                continue;
            }

            $tokens = UserDevice::query()
                ->where('user_id', $user->id)
                ->whereNotNull('push_token')
                ->pluck('push_token')
                ->filter()
                ->unique()
                ->values();

            foreach ($tokens as $token) {
                $summary['device_count']++;

                $result = $this->firebaseMessaging->sendToToken((string) $token, $title, $body, $data);

                if (($result['ok'] ?? false) === true) {
                    $summary['push_success_count']++;
                } else {
                    $summary['push_failure_count']++;
                }
            }
        }

        return $summary;
    }
}
