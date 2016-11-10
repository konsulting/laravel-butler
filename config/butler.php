<?php

return
[
    /**
     * The UserProvider - responsible for finding and creating users when required.
     */
    'user_provider' => \Konsulting\Butler\EloquentUserProvider::class,

    /**
     * The user class for mapping identities to.
     */
    'user_class' => \App\User::class,

    /**
     * A list of providers to use. These map to Socialite Drivers.
     */
    'providers' => [
        // 'google' => ['name' => 'Google', 'icon' => 'fa fa-google', 'class' => 'btn-google'],
        // 'facebook' => ['name' => 'Facebook', 'icon' => 'fa fa-facebook', 'class' => 'btn-facebook'],
    ],

    /**
     * Can Butler create users if social login is requested for non-exiting user?
     */
    'can_create_users' => false,

    /**
     * Should we ask the user to confirm the identity if it is their first one and they just created the account?
     */
    'confirm_identity_for_new_user' => true,

    /**
     * The class of the notification to send when adding a social identity
     */
    'confirm_identity_notification' => \Konsulting\Butler\Notifications\ConfirmSocialIdentity::class,

    /**
     * The application routes for us to use when redirecting the user after actions
     */
    'route_map' => [
        'login' => 'login',
        'authenticated' => 'home',
        'profile' => 'profile',
    ]
];
