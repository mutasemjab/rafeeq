<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RafiqNotification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notifications\FirebaseMessagingService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class NotificationsController extends Controller
{
    public function index(FirebaseMessagingService $firebaseMessaging)
    {
        $recentNotifications = RafiqNotification::query()
            ->with('user')
            ->latest()
            ->paginate($this->paginationCount());

        $users = User::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email']);

        $registeredDevicesCount = UserDevice::query()
            ->whereNotNull('push_token')
            ->count();

        return view('admin.notifications.index', [
            'recentNotifications' => $recentNotifications,
            'users' => $users,
            'registeredDevicesCount' => $registeredDevicesCount,
            'firebaseConfigured' => $firebaseMessaging->isConfigured(),
            'firebaseProjectId' => config('firebase.project_id'),
        ]);
    }

    public function store(Request $request, NotificationDispatcher $dispatcher): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['all', 'user'])],
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::requiredIf(fn () => $request->input('audience') === 'user'),
            ],
            'type' => 'nullable|string|max:100',
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:1000',
            'data_json' => 'nullable|string',
            'send_push' => 'nullable|boolean',
        ]);

        $notificationData = $this->parseNotificationData($data['data_json'] ?? null);
        $type = $data['type'] ?? 'admin_broadcast';
        $sendPush = $request->boolean('send_push');

        $users = $data['audience'] === 'all'
            ? User::query()->where('status', 'active')->cursor()
            : User::query()->whereKey($data['user_id'])->cursor();

        $summary = $dispatcher->sendToUsers(
            $users,
            $type,
            $data['title'],
            $data['body'],
            $notificationData,
            $sendPush
        );

        $message = 'Sent '.$summary['notification_count'].' in-app notifications.';

        if ($sendPush && ! $summary['push_skipped']) {
            $message .= ' Push sent to '.$summary['push_success_count'].' device(s)';

            if ($summary['push_failure_count'] > 0) {
                $message .= ' with '.$summary['push_failure_count'].' failure(s).';
            } else {
                $message .= '.';
            }
        } elseif ($sendPush && $summary['push_skipped']) {
            $message .= ' Firebase push was skipped because server credentials are not configured.';
        }

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', $message);
    }

    private function parseNotificationData(?string $dataJson): array
    {
        if ($dataJson === null || trim($dataJson) === '') {
            return [];
        }

        try {
            $decoded = json_decode($dataJson, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded) || ! $this->isAssociativeArray($decoded)) {
                throw ValidationException::withMessages([
                    'data_json' => ['Notification data must be a JSON object.'],
                ]);
            }

            return $decoded;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'data_json' => ['Notification data must be valid JSON.'],
            ]);
        }
    }

    private function isAssociativeArray(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
