# Butler

*Butler manages multiple [Socialite](https://github.com/laravel/socialite) authentication for your [Laravel](https://laravel.com) app.*

In a few simple steps you can allow users to log in to your site using multiple providers. 

Butler is built to work alongside standard Laravel authentication, and uses Socialite to connect to authentication providers.

## Requirements

* Install [Socialite](https://github.com/laravel/socialite) - follow the documentation on the site.

* Setup your Socialite providers (you may need to install extra [Socialite Providers](https://socialiteproviders.github.io/))

## Installation

* Install Butler using composer: `composer require konsulting/laravel-butler`

* Add Butler's Service Provider to `config/app.php`

```php
'providers' => [
    // Other service providers...

    Konsulting\Butler\ButlerServiceProvider::class,
],
	
```

* Add Butler's Facade to `config/app.php`

```php
'aliases' => [
    // Other aliases...

    'Butler' => Konsulting\Butler\ButlerFacade::class,
],

```

* Add Butler's routes to `app/Providers/RouteServiceProvider.php`

```php
class RouteServiceProvider ...

public function map()
{
    $this->mapApiRoutes();

    $this->mapWebRoutes();

    \Butler::routes();
}

```

* Publish configuration and adjust for your site
`php artisan vendor:publish --provider=Konsulting\\Butler\\ButlerServiceProvider --tag=config`

Adjust in `config/butler.php`. Add the providers you want to make available for Authentication - these map directly to Socialite drivers. There are examples in the published file.

```php
    // Other Settings
  
    'providers' => [
        'google' => [
            'name' => 'Google',
            'icon' => 'fa fa-google',
            'class' => 'btn-google',
        ],
        ...
    ]

```

See configuration options for more information.

* _Optionally_ add list of Oauth buttons to your login page, and status feedback.

	Add the following includes to your blade template for the login page. 

```
@include('butler::status')

@include('butler::list')
```

## Configuration Options

There is a small set of configuration options. 

Option | What it’s for
-------|--------------
user_provider | It finds and creates users for Butler. Only creating if it is allowed to.
user_class | The applications user model, used by the user provider, and the SocialIdentity class to set up the user relation.
providers | The list of Social Login providers that Butler needs to be aware of. (These are essentially the Socialite Drivers you are using). It is used to populate the Notification to users and the list view.
can_create_users | Sets whether Butler is allowed to create users when a new Social Identity is received and there is no matching user account.
confirm_identity_for_new_user | Sets whether to ask the user to confirm the identity if it is their first one and they just created the account.
confirm_identity_notification | The Notification class to use when telling the user that we had a Social Identification request. Sent when using the AuthController.
login_immediately_after_confirm | Should we log someone in straight after confirming via email, default is false.
route_map | Defines the mapping of Butler's name for special routes in the application to actual application values. We’ve set some common ones out of the box.

## Security

We have not encrypted any of the retrieved tokens at this time, since the tokens are intended to be short-lived. We’re happy to receive views on this decision.

If you find any security issues, or have any concerns, please email [keoghan@klever.co.uk](keoghan@klever.co.uk), rather than using the issue tracker.

## Contributing

Contributions are welcome and will be fully credited. We will accept contributions by Pull Request. 

Please:

* Use the PSR-2 Coding Standard
* Add tests, if you’re not sure how, please ask.
* Document changes in behaviour, including readme.md.

## Testing
We use [PHPUnit](https://phpunit.de) and the excellent [orchestral/testbench](https://github.com/orchestral/testbench) 

Run tests using PHPUnit: `vendor/bin/phpunit`