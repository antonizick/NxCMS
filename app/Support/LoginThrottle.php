<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;

final class LoginThrottle
{
    private const WINDOW_MINUTES = 15;
    private const IP_LIMIT = 10;       // across all usernames from one IP
    private const USERNAME_LIMIT = 5;  // across all IPs against one username — the known 'nick' account is a fixed target

    public static function blocked(string $ip, string $username): bool
    {
        return ActivityLog::recentFailuresByIp($ip, self::WINDOW_MINUTES) >= self::IP_LIMIT
            || ActivityLog::recentFailuresByUsername($username, self::WINDOW_MINUTES) >= self::USERNAME_LIMIT;
    }
}
