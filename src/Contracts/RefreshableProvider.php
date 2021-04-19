<?php

namespace Konsulting\Butler\Contracts;

interface RefreshableProvider
{
    /**
     * Refresh the access token using the refresh token.
     *
     * @param $refreshToken
     * @return array
     */
    public function getRefreshResponse($refreshToken);
}
