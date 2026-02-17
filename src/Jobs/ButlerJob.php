<?php

namespace Konsulting\Butler\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Konsulting\Butler\Exceptions\SocialIdentityNotFound;
use Konsulting\Butler\SocialIdentity;

abstract class ButlerJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job should be retried, refreshing the token after each unsuccessful attempt. Already
     * expired tokens will be refreshed before the job is started, so do not count against this try limit.
     *
     * @var int
     */
    protected $butlerTryLimit = 1;

    /**
     * @var Authenticatable
     */
    protected $user;

    public function __construct(Authenticatable $user)
    {
        $this->user = $user;
    }

    /**
     * Get the name of the socialite provider.
     *
     * @return string
     */
    abstract protected function getSocialProviderName();

    /**
     * The main body of the job. This will be attempted multiple times following token refreshes if $butlerTryLimit > 1.
     *
     * @param  string  $token
     * @return bool
     */
    abstract protected function doAction($token);

    /**
     * Handle the exception caught in the process of executing the action or refreshing the token.
     *
     * @param  \Exception  $e
     * @return mixed
     *
     * @throws \Exception
     */
    protected function handleException(\Throwable $e)
    {
        throw $e;
    }

    /**
     * Invalidate the access token. Useful if the response indicates that the access token is invalid or expired, and
     * should be refreshed upon retry.
     *
     * @return bool
     */
    protected function invalidateAccessToken()
    {
        return $this->getSocialIdentityFromUser()->invalidateAccessToken();
    }

    /**
     * Handle the job.
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function handle()
    {
        try {
            $socialIdentity = $this->getSocialIdentityFromUser();
            $token = $this->getToken($socialIdentity);

            return $this->doAction($token);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get the access token for the social identity, refreshing if necessary.
     *
     * @param  bool  $forceRefresh
     * @return string The access token
     */
    private function getToken(SocialIdentity $socialIdentity, $forceRefresh = false)
    {
        if ($forceRefresh || $socialIdentity->accessTokenIsExpired()) {
            $socialIdentity = $this->refreshSocialIdentityTokens($socialIdentity);
        }

        return $socialIdentity->access_token;
    }

    /**
     * Refresh the tokens on the social identity model.
     *
     * @param  SocialIdentity  $socialIdentity
     * @return SocialIdentity
     */
    private function refreshSocialIdentityTokens($socialIdentity)
    {
        return \Butler::driver($this->getSocialProviderName())->refresh($socialIdentity);
    }

    /**
     * Retrieve the social identity from the user model.
     *
     * @return SocialIdentity|Model
     *
     * @throws \Exception
     */
    private function getSocialIdentityFromUser()
    {
        $socialIdentity = SocialIdentity::query()->where('user_id', $this->user->getAuthIdentifier())
            ->where('provider', $this->getSocialProviderName())->first();

        if (! $socialIdentity) {
            throw new SocialIdentityNotFound('User '.$this->user->getAuthIdentifier().
                ' does not have a social identity for '.$this->getSocialProviderName());
        }

        return $socialIdentity;
    }
}
