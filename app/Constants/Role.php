<?php

namespace App\Constants;

class Role
{
    public const ADMIN = 'ADMIN';
    public const UPLOADER = 'UPLOADER';
    public const VIEWER = 'VIEWER';

    public static function all(): array
    {
        return [
            self::ADMIN,
            self::UPLOADER,
            self::VIEWER,
        ];
    }
}
