<?php

/*
 * This file is part of App.
 * Please view the LICENSE file that was distributed with this source code.
 *
 * # MythicalSystems License v2.0
 *
 * ## Copyright (c) 2021–2025 MythicalSystems and Cassian Gherman
 *
 * Breaking any of the following rules will result in a permanent ban from the MythicalSystems community and all of its services.
 */

namespace App\Controllers\User\Auth;

use App\Chat\User;
use App\Chat\Activity;
use App\Helpers\ApiResponse;
use App\CloudFlare\CloudFlareRealIP;
use App\Plugins\Events\Events\AuthEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthLogoutController
{
    public function get(Request $request): Response
    {
        global $eventManager;
        if (!isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600 - 1500 * 120);

            return ApiResponse::success([], 'Logged out and we did not find a remember_token', 200);
        }
        /**
         * If the user has a remember_token, we need to update it and block 2FA if it's enabled.
         */
        $user = User::getUserByRememberToken($_COOKIE['remember_token']);
        if ($user == null) {
            return ApiResponse::success([], 'Logged out no user found', 200);
        }
        if (isset($user['remember_token'])) {
            $newRememberToken = bin2hex(random_bytes(32));
            User::updateUser(
                $user['uuid'],
                [
                    'remember_token' => $newRememberToken,
                ]
            );
            if (isset($user['two_fa_enabled']) && $user['two_fa_enabled'] == 'true') {
                User::updateUser(
                    $user['uuid'],
                    [
                        'two_fa_blocked' => 'true',
                    ]
                );
            }
            $eventManager->emit(
                AuthEvent::onAuthLogout(),
            );
            Activity::createActivity([
                'user_uuid' => $user['uuid'],
                'name' => 'logout',
                'context' => 'User logged out',
                'ip_address' => CloudFlareRealIP::getRealIP(),
            ]);
        }
        setcookie('remember_token', '', time() - 3600 - 1500 * 120);

        return ApiResponse::success([], 'Logged out', 200);
    }
}
