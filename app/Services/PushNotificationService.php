<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
public static function send(
    int $userId,
    string $title,
    string $body,
    string $channel = 'general'
    ): bool {

        $user = User::find($userId);

        if (!$user || !$user->push_token) {

            Log::warning(
                "Usuario sin push token",
                ["user_id" => $userId]
            );

            return false;
        }

        $response = Http::post(
            'https://exp.host/--/api/v2/push/send',
            [
                'to' => $user->push_token,
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'priority' => 'high',
'channelId' => $channel,
            ]
        );

        Log::info(
            "PUSH ENVIADA",
            [
                "user_id" => $userId,
                "title" => $title,
                "response" => $response->json()
            ]
        );

        return true;
    }
}
