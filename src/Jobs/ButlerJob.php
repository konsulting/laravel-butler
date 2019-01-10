<?php

namespace Konsulting\Butler\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Konsulting\Butler\SocialIdentity;

abstract class ButlerJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $butlerTryLimit = 2;

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
     * @param string $token
     * @return bool
     */
    abstract protected function doAction($token);

    /**
     * Handle the exception caught in the process of executing the action or refreshing the token.
     *
     * @param \Exception $e
     * @throws \Exception
     * @return mixed
     */
    protected function handleException(\Exception $e)
    {
        throw $e;
    }

    /**
     * Handle the job.
     *
     * @return bool
     * @throws \Exception
     */
    public function handle()
    {
        try {
            $socialIdentity = $this->getSocialIdentityFromUser();
            $token = $this->getToken($socialIdentity);

            return $this->actionLoop($token, $socialIdentity);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get the access token for the social identity, refreshing if necessary.
     *
     * @param SocialIdentity $socialIdentity
     * @param bool           $forceRefresh
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
     * Refresh the social identity and get the access token.
     *
     * @param SocialIdentity $socialIdentity
     * @return string
     */
    private function getRefreshedToken(SocialIdentity $socialIdentity)
    {
        return $this->getToken($socialIdentity, true);
    }

    /**
     * Refresh the tokens on the social identity model.
     *
     * @param SocialIdentity $socialIdentity
     * @return SocialIdentity
     */
    private function refreshSocialIdentityTokens($socialIdentity)
    {
        return \Butler::driver($this->getSocialProviderName())->refresh($socialIdentity);
    }

    /**
     * Loop through the action for the specified number of tries, or until successful.
     *
     * @param string         $token
     * @param SocialIdentity $socialIdentity
     * @return bool
     */
    private function actionLoop($token, $socialIdentity)
    {
        $tries = 1;
        do {
            $success = $this->doAction($token);

            if ($success || $tries >= $this->butlerTryLimit) {
                break;
            }

            $token = $this->getRefreshedToken($socialIdentity);
            $tries++;
        } while (true);

        return $success;
    }

    /**
     * Retrieve the social identity from the user model.
     *
     * @return SocialIdentity|Model
     * @throws \Exception
     */
    private function getSocialIdentityFromUser()
    {
        $socialIdentity = SocialIdentity::query()->where('user_id', $this->user->getAuthIdentifier())
            ->where('provider', $this->getSocialProviderName())->first();

        if (! $socialIdentity) {
            throw new \Exception('Identity not found');
        }

        return $socialIdentity;
    }
}
