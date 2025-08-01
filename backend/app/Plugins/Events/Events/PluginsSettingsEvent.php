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

namespace App\Plugins\Events\Events;

use App\Plugins\Events\PluginEvent;

class PluginsSettingsEvent implements PluginEvent
{
    public static function onPluginSettingUpdate(): string
    {
        return 'plugins:settings:update';
    }

    public static function onPluginSettingDelete(): string
    {
        return 'plugins:settings:delete';
    }
}
