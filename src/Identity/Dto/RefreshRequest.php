<?php

declare(strict_types=1);

namespace App\Identity\Dto;

/**
 * DTO for refresh token request
 */
class RefreshRequest
{
    public string $refreshToken;

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }
}
