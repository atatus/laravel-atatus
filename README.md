# Atatus Laravel Middleware

Atatus Laravel Middleware allows for the automatic capture of API calls and sends them to [Atatus API analytics](https://www.atatus.com).

## How to install

Via Composer

```bash
$ composer require atatus/laravel-atatus
```
or add 'atatus/laravel-atatus' to your composer.json file accordingly.

## How to use

### Add Service Provider

```php

// In config/app.php

'providers' => [
  /*
   * Application Service Providers...
   */
    Atatus\Middleware\AtatusLaravelServiceProvider::class,
];
```

### Add to Middleware

If website root is your API, add to the root level:

```php

// In App/Http/Kernel.php

protected $middleware = [
  /*
   * The application's global HTTP middleware stack.
   *
   * These middleware are run during every request to your application.
   */
   \Atatus\Middleware\AtatusLaravel::class,
];

```

If you only want to add tracking for APIs under specific route group, add to your route group, but be sure to remove from the global
middleware stack from above global list.

```php
// In App/Http/Kernel.php

protected $middlewareGroups = [
  /**
   * The application's API route middleware group.
   */
   'api' => [
        //
        \Atatus\Middleware\AtatusLaravel::class,
    ],
];
```

To track only certain routes, use route specific middleware setup.


### Publish the package config file

```bash
$ php artisan vendor:publish --provider="Atatus\Middleware\AtatusLaravelServiceProvider"
```

### Setup config

Edit `config/atatus.php` file.

```php

// In config/atatus.php

return [
    'logBody' => true,
    // 'debug' => false,
    // 'configClass' => 'MyApp\\MyConfigs\\CustomAtatusConfig'
];
```

For other configuration options, see below.

## Configuration options

You can define Atatus configuration options in the `config/atatus.php` file.

#### __`logBody`__
Type: `Boolean`
Optional, Default true, Set to false to remove logging request and response body to Atatus.

#### __`debug`__
Type: `Boolean`
Optional, Default false, Set to true to print debug messages using Illuminate\Support\Facades\Log

#### __`configClass`__
Type: `String`
Optional, a string for the full path (including namespaces) to a class containing additional functions.
The class can reside in any namespace, as long as the full namespace is provided.

example:

```php
return [
    'logBody' => true,
    'debug' => false,
    'configClass' => 'MyApp\\MyConfigs\\CustomAtatusConfig'
];
```

## Configuration class **(Optional)**

Because configuration hooks and functions cannot be placed in the `config/atatus.php` file, these reside in a PHP class that you create.
Set the path to this class using the `configClass` option. You can define any of the following hooks:

#### __`identifyUserId`__
Type: `($request, $response) => String`
Optional, a function that takes a $request and $response and return a string for userId. Atatus automatically obtains end userId via $request->user()['id'], In case you use a non standard way of injecting user into $request or want to override userId, you can do so with identifyUserId.

#### __`identifyCompanyId`__
Type: `($request, $response) => String`
Optional, a function that takes a $request and $response and return a string for companyId.

#### __`maskRequestBody`__
Type: `$body => $body`
Optional, a function that takes a $body, which is an associative array representation of JSON, and
returns an associative array with any information removed.

#### __`maskResponseBody`__
Type: `$body => $body`
Optional, same as above, but for Responses.


Example config class

```php
namespace MyApp\MyConfigs;

class CustomAtatusConfig
{

    public function maskRequestBody($body) {
      return $body;
    }

    public function maskResponseBody($body) {
      return $body;
    }

    public function identifyUserId($request, $response) {
      if (is_null($request->user())) {
        return null;
      } else {
        $user = $request->user();
        return $user['id'];
      }
    }

    public function identifyCompanyId($request, $response) {
      return "comp_acme_corporation";
    }

}
```

- In your `config/atatus.php` file:

```php

return [
    'logBody' => true,
    'debug' => false,
    'configClass' => 'MyApp\\MyConfigs\\CustomAtatusConfig'
]

```

## Be sure to update cache after changing config:

If you enabled config cache, after you update the configuration, please be sure to run `php artisan config:cache` again to ensure configuration is updated.

## Credits

- Mixpanel's PHP client
- Moesif Laravel Middlware
- Jonny Pickett

### The PHP JSON extension is required.

Make sure you install PHP with the JSON Extension enabled [More Info](https://stackoverflow.com/questions/7318191/enable-json-encode-in-php).

