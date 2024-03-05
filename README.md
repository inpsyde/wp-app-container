# WP App Container

_DI Container and related tools to be used at website level_.

![PHP Quality Assurance](https://github.com/inpsyde/wp-app-container/workflows/PHP%20Quality%20Assurance/badge.svg)

---


## Table of Contents

- [What is and what is not](#what-is-and-what-is-not)
- [Concepts overview](#concepts-overview)
    - [App](#app)
    - [Service provider](#service-provider)
    - [Container](#container)
    - [Env config](#env-config)
    - [Context](#context)
- [Decisions](#decisions)
- [Usage at website level](#usage-at-website-level)
    - [Customizing site config](#customizing-site-config)
    - [Hosting provider](#posting-provider)
    - [Locations](#locations)
        - [Access locations](#access-locations)
        - [Adjust locations](#adjust-locations)
        - [Custom locations](#custom-locations)
        - [Set locations via environment variables](#set-locations-via-environment-variables)
- [Usage at package level](#usage-at-package-level)
    - [Contextual registration](#contextual-registration)
    - [Package-dependant registration](#package-dependant-registration)
    - [Providers workflow](#providers-workflow)
        - [Available service provider abstract classes](#available-service-provider-abstract-classes)
        - [The case for delayed registration](#the-case-for-delayed-registration)
    - [Service providers ID](#service-providers-id)
    - [Composing the container](#composing-the-container)
    - [Simple service provider example](#simple-service-provider-example)
    - [Service provider example using any PSR-11 container](#service-provider-example-using-any-psr-11-container)
    - [Website-level providers](#website-level-providers)
    - [Providers Package](#providers-package)
- [Advanced topics](#advanced-topics)
    - [Custom last boot hook](#custom-last-boot-hook)
    - [Building custom container upfront](#building-custom-container-upfront)
    - [Resolve objects outside providers](#resolve-objects-outside-providers)
    - [Debug info](#debug-info)
- [Installation](#installation)
- [Crafted by Inpsyde](#crafted-by-inpsyde)
- [License](#license)
- [Contributing](#contributing)

---



## What is and what is not

This is a package aimed to solve dependency injection container, service providers, and application "bootstrapping", at **application, i.e. website, level**.

The typical use case is when building a website for a client, for which we foresee to write several "packages": library, plugins, and theme(s), that will be then "glued" together using Composer.

Thanks to this package will be possible to have a centralized dependency resolution, and a quite standardized and consistent structure for the backend of those packages.

Technically speaking, right now, there's nothing that prevents the use at package level, however for several reasons, that is a no-goal of this package and no code will be added here to comply with that.

This package was not written to be "just a standard", i.e. provide just the abstraction leaving the implementations to consumers, but instead had been written to be a ready-to-use implementation.

However, an underlying support for [PSR-11](<https://www.php-fig.org/psr/psr-11/>) allows for very flexible usage.



## Concepts overview

### App

 This is the central class of the package. It is the place where "application bootstrapping" happen, where `Service Providers` are registered, and it is very likely the only object that need to be used from the website "package" (that one that "glues" other packages/plugins/themes via Composer).

### Service provider

The package provides a single service provider interface (plus several abstract classes that partially implement it). The objects are used to "compose" the Container. Moreover, in this package implementation, service providers are (or better, could be) responsible to tell how to *use* the registered services. In WordPress world that very likely means to "add hooks".

### Container

This is a "storage" that is capable of storing, and retrieve objects by an unique identifier. On retrieval (oftentimes just the first time they are retrieved), objects are "resolved", meaning that any other object that is required for the target object to be constructed, will first recursively resolved in the container, and then injected in the target object before it is returned. The container implementation shipped here is an extension of Pimple, with added PSR-11 support, with the capability to act as a "proxy" to several other PSR-11 containers. Which means that Service Providers can "compose" the dependency tree in the Container either by directly [adding services factories to the underlying Pimple container](<https://pimple.symfony.com/#defining-services>) or they can "append" to the main container a ready-made PSR-11 container.

### Env config

As stated above, this package targets websites development, and something that is going to be required at website level is configuration. Working with WordPress configuration often means PHP constants, but when using Composer at website level, in combination with, for example, WP Starter it also also means environment variables. The package ships an `SiteConfig` interface with an `EnvConfig` implementation that does nothing in the regard of _storing_ configuration, but offers a very flexible way to _read_ configuration both from constants and env vars.
`Container::config()` method returns and instance of `SiteConfig`.

### Context

Service providers job is to both add services in the container and add the hooks that make use of them, however in WordPress it often happens that services are required under a specific "context". For example, a service provider responsible to register and enqueue assets for the front-end is not required in backoffice (dashboard), nor in AJAX or REST requests, and so on. Using the proper hooks to execute code is something that can be often addressed, but often not. E.g. distinguish a REST request is not very easy at an early hook, or there's no function or constants that tell us when we are on a login page and so on. Moreover, even storing objects factories in the Container for things we are _sure_ are not going to be used is  waste of memory we can avoid. The `Context ` class of this package is a centralized service that provides info on the current request.
`Container::context()` method returns and instance of Context.



## Decisions

Because we wanted a ready-to-use package, we needed to pick a DI container _implementation_, and we went for [Pimple](<https://pimple.symfony.com/>), for the very reason that it is one of the simplest implementation out there.

However, as shown later, anyone who want to use a different PSR-11 container will be very able to do so.

In the "Concepts Overview" above, the last two concepts ("Env Config" and "Context") are not really something that are naturally coupled with the other three, however, the assumption that this package will be used for WordPress _websites_ allow us to introduce this "coupling" without many risks (or sense of guilt): assuming we would ship these packages separately, when building websites (which, again, is the only goal of this package) we will very likely going to require those separate packages anyway, making this the perfect example for the _Common-Reuse Principle_: _classes that tend to be reused together belong in the same package together._



## Usage at website level

The "website" package, that will glue together all packages, needs to only interact with the `App` class, in a very simple way:

```php
<?php
namespace AcmeInc;

add_action('muplugins_loaded', [\Inpsyde\App\App::new(), 'boot']);
```

That's it. This code is assumed to be placed in MU plugin, but as better explained later, it is possible to do it outside any MU plugin or plugin, either wrapping the `App::boot()` call in a hook or not.

This one-liner will create the relevant objects and will fire actions that will enable other packages to register service providers.

### Customizing site config

By creating an instance of `App` via `App::new()`, it will take care of creating an instance of `EnvConfig` that will later returned when calling `Container::config()`.
`EnvConfig` is an object that allows to retrieve information regarding current environment (e.g. *production*, *staging*, *development*...) and also to get settings stored as PHP constants or environment variables.

Information regarding running environment are auto-discovered from env variables supported by [WP Starter](https://github.com/wecodemore/wpstarter) or from configurations defined in well-known hosting like Automattic VIP or WP Engine.
There's a fallback in case no environment can be determined: if `WP_DEBUG`: is true, `development` environment is assumed, otherwise `production`.

In *any* case, a filter: `"wp-app-environment"` is available for customization of the determined environment.

Regarding PHP constants, `EnvConfig` is capable to search for constants defined in the root namespace, but also inside other namespaces.
For the latter case, the class has to configured to let it know which "alternative" namespaces are supported.

That can be done by creating an instance of `Container` that uses a custom `EnvConfig` instance, and then pass it to `App::new()`. For example:

```php
<?php
namespace AcmeInc;

use Inpsyde\App;

$container = new App\Container(new App\EnvConfig('AcmeInc\Config', 'AcmeInc'));
App\App::new($container)->boot();
```

With the code in the above snippet, the created `EnvConfig` instance (that will be available via `Container::config()` method) can return settings in `AcmeInc\Config` or `AcmeInc` namespaces (besides root namespace).

For example, if some configuration file contains:

```php
<?php
define('AcmeInc\Config\ONE', 1);
define('AcmeInc\TWO', 2);
```

it will be possible to do:

```php
<?php
/** @var Inpsyde\App\Container $container */
$container->config()->get('ONE'); // 1
$container->config()->get('TWO'); // 2
```

Note that `EnvConfig::get()` accepts an optional second `$default` parameter to be returned in case no constant and no matching environment variable is set for given name:

```php
<?php
/** @var Inpsyde\App\Container $container */
$container->config()->get('SOMETHING_NOT_DEFINED', 3); // 3
```

### Hosting provider

`EnvConfig::hosting()` returns the current Hosting provider. Currently we're automatically detecting following:

- `EnvConfig::HOSTING_VIP` - WordPress VIP Go
- `EnvConfig::HOSTING_WPE` - WP Engine
- `EnvConfig::HOSTING_SPACES` - Mittwald Spaces
- `EnvConfig::HOSTING_OTHER` - If none of those above is detected

Custom hosting can be setup via a `HOSTING` env variable or constant.

To check in code which is the current solution, there's a  `EnvConfig::hostingIs()` method that
accepts an hosting name string and returns true when the given hosting matches the current hosting.


### Locations

#### Access locations

`EnvConfig::locations()` returns an instance of `Inpsyde\App\Location\Locations` which allows to 
resolve following directories and URLs:

- mu-plugins
- plugins
- themes
- languages
- vendor
 
On VIP Go (`HOSTING` value will be `EnvConfig::HOSTING_VIP`), additional locations can be obtained:

- private
- config
- vip-config
- images

In fact, `Locations` is an interface, and currently there are three implementation of it, one for
"generic" hosting, one for VIP Go and one for WP Engine.

An example:

```php
/** @var Inpsyde\App\EnvConfig $envConfig */
$location = $envConfig->locations();

$vendorPath = $location->vendorDir();                   // vendor directory path
$wonologPath = $location->vendorDir('inpsyde/wonolog'); // specific package path

$pluginsUrl = $location->pluginsUrl();                   // plugins directory URL
$yoastSeoUrl = $location->pluginsUrl('/wordpress-seo/'); // specific plugin URL
```

#### Adjust locations

In case the package is not capable of discovering paths and URLs automatically (e.g. because a very custom setup)
they can be set by using a `LOCATIONS` constant that is an an array with two top-level elements, one for
URLs and one for paths, each being a map in form of array with location name as keys and location
URL / path as value:

For example:

```php
namespace AwesomeWebsite\Config;
 
use Inpsyde\App\Location\Locations;
use Inpsyde\App\Location\LocationResolver;

const LOCATIONS = [
    LocationResolver::URL => [
        Locations::VENDOR => 'http://example.com/wp/wp-content/composer/vendor/',
        Locations::ROOT => __DIR__,
        Locations::CONTENT => 'http://content.example.com/',
    ],
    LocationResolver::DIR => [
        Locations::VENDOR => '/var/www/wp/wp-content/composer/vendor/',
        Locations::ROOT => dirname(__DIR__),
        Locations::CONTENT => '/var/www/content/',
    ],
];
```

As array key, besides `Locations::VENDOR`, `Locations::ROOT`, and `Locations::CONTENT`, it is also possible
to use any other `Locations` constant, e.g. `Locations::MU_PLUGINS` or `Locations::LANGUAGES` and so on.

The config provided is merged with defaults that can be fine-tuned depending on hosting.

#### Custom locations

Besides the `Locations` constants, it is also possible to use custom keys, and retrieve them using 
the `Locations::resolveDir()` and `Locations::resolveUrl()` methods.

For example:

```php
namespace AwesomeWebsite\Config;
 
use Inpsyde\App\Location\LocationResolver;

const LOCATIONS = [
    LocationResolver::DIR => [
        'logs' => '/var/www/logs/',
    ],
];
```

and then:

```php
/** @var Inpsyde\App\EnvConfig $envConfig */
/** @var Inpsyde\App\Location\Locations $locations */
$locations = $envConfig->locations();

echo $locations->resolveDir('logs', '2019/10/08.log');

"/var/www/logs/2019/10/08.log"
```

In the example above, calling `$locations->resolveUrl('logs')` will return `null` because
no URL was set for the key `'logs'` in the `LOCATIONS` constant.

#### Set locations via environment variables

In the examples above, both default and custom locations are customized using the `LOCATIONS` constant
that, for obvious reasons, can only be set in PHP configuration files.

For websites that rely on environment variables to set configuration, the package provides a different approach.

Environment variables in the format `WP_APP_{$location}_DIR` and `WP_APP_{$location}_URL` can be used
to set location directories and URLs.

For example, vendor path can be set via `WP_APP_VENDOR_DIR` and vendor URL via `WP_APP_VENDOR_URL`,
just like root path can be set via `WP_APP_ROOT_DIR` and root URL via `WP_APP_ROOT_URL`.

This works also for custom paths.

For example, by setting environment variables like this:

```bash
WP_APP_VENDOR_DIR="/var/www/shared/vendor/"
WP_APP_LOGS_DIR="/var/www/logs/"
```

it is then possible to retrieve them like this:

```php
/** @var Inpsyde\App\EnvConfig $envConfig */
/** @var Inpsyde\App\Location\Locations $locations */
$locations = $envConfig->locations();

echo $locations->vendorDir('inpsyde/wp-app-container');
"/var/www/shared/vendor/inpsyde/wp-app-container"


echo $locations->resolveDir('logs', '2019/10');
"/var/www/logs/2019/10"
```

Please note that if _both_ `WP_APP_`* env variable and value in `LOCATIONS` constant are set for the 
same location, the env variable takes precedence.

## Usage at package level

At package level there are two ways to register services (will be shown later), but first providers need to be added to the App:

```php
<?php
namespace AcmeInc\Foo;

use Inpsyde\App\App;
use Inpsyde\WpContext;

add_action(
    App::ACTION_ADD_PROVIDERS,
    function (App $app) {
        $app
            ->addProvider(new MainProvider(), WpContext::CORE)
            ->addProvider(new CronRestProvider(), WpContext::CRON, WpContext::REST)
            ->addProvider(new AdminProvider(), WpContext::BACKOFFICE);
    }
);
```

The hook `App::ACTION_ADD_PROVIDERS` can actually more than once (more on this soon), but for now is relevant that even if the hook is fired more than once, the `App` class will be clever enough to add the provider only once.



### Contextual registration

As shown in the example above, `App::addProvider()`, besides the service provider itself, accepts a variadic number of "Context" constants, that tell the App the given provider should be only used in the listed contexts.

The full list of possible constants is:

- `CORE`, which is basically means "always", or at least _"if WordPress is loaded"_
- `FRONTOFFICE`
- `BACKOFFICE` ("admin" requests, excluding AJAX request )
- `AJAX`
- `REST`
- `CRON`
- `LOGIN`
- `CLI` (in the context of WP CLI)



### Package-dependant registration

Besides `App::ACTION_ADD_PROVIDERS` there's another hook that packages can use to add service providers to the App. It is: `App::ACTION_REGISTERED_PROVIDER`.

This hook is fired right after any provider is registered. Using this hook it is possible to register providers only if a given package is registered, allowing to ship libraries / plugins that will likely do nothing if other library / plugin are not available.

```php
<?php
namespace AcmeInc\Foo\Extension;

use Inpsyde\App\App;
use Inpsyde\WpContext;
use AcmeInc\Foo\MainProvider;

add_action(
    App::ACTION_REGISTERED_PROVIDER,
    function (string $providerId, App $app) {
        if ($providerId === MainProvider::class) {
            $app->addProvider(new ExtensionProvider(), WpContext::CORE);
        }
    },
    10,
    2
);
```

The just-registered package ID is passed as first argument by the hook. By default the package ID is the FQCN of the provider class, but that can be easily changed, so to be dependant on a package it is necessary to know the ID it uses.

One think important to note is that `App::ACTION_REGISTERED_PROVIDER` hook is fired only if the target service provider `register()` method returns `true`. If e.g. the provider is a "booted only" provider (more on this below) the hook will not be fired.

In that case it is possible to use  `App::ACTION_ADDED_PROVIDER` hook, which works similarly and it is fired in the moment the provider is _added_, so before registration is ever attempted.



### Providers workflow

As already stated multiple times, the scope of the library is to provide a common ground for service registration and bootstrapping of all packages that compose a website.

This means that it is necessary to allow generic libraries, MU plugins, plugins, and themes, to register their services, which means that, in theory, application should "wait" for all of those packages to be available.
However, at same time, it is very possible that some packages will need to run at an early stage in the WordPress loading workflow.

To satisfy both these requirements, the `App` class runs its "bootstrapping procedure" **from one to three times**, depending on when `App::boot()` is called for first time.

If `App::boot()` is called first time **before `plugins_loaded`** hook, it will automatically called again at `plugins_loaded` and again at `init`. For a total of 3 times.

If `App::boot()` is called first time **after (or during) `plugins_loaded` , but before `init`** it will automatically called again at `init`. For a total of 2 times.

If `App::boot()` is called first time **during `init`** it will not be called again, so will run once in total.

If `App::boot()` is called first time **after `init`** an exception will be thrown.

Each time `App::boot()` is called, the `App::ACTION_ADD_PROVIDERS` action is fired allowing packages to add service providers.

Added service providers `register()` method, that add services in the container, is normally _immediately_ called, unless the just added service provider declares to support "delayed registration" (more on this soon).

Added service providers `boot()` method, that makes use of the registered services, is normally delayed until last time `App::boot()` is called (WP is at `init` hook), but service providers can declare to support "early booting" (more on this soon), in which case their `boot()` method is called after the `register` method, without waiting `boot()` to be called for last time at `init`.

In the case a service provider supports both _delayed registration_ and _early booting_, its `register()` method will still be called before its `boot()` method, but _after_ having called the `register()` method of all non-delayed providers that are going to be booted in the same `boot()` cycle.

Considering the case in which `App::boot()` is ran 3 times, (before `plugins_loaded`, on `plugins_loaded`, and on `init`) the order of events is the following:

- Core is at _before_ `plugins_loaded`
    1. added service providers _without_ support for _delayed registration_ are registered
    2. added service providers _with_ support for _delayed registration_ and also _with_ support for _early booting_ are registered
    3. added service providers _with_ support for _early booting_ are booted
    
- Core is _at_ `plugins_loaded`
    1. added service providers _without_ support for _delayed registration_ are registered
    2. added service providers _with_ support for _delayed registration_ and also _with_ support for _early booting_ are registered
    3. added service providers _with_ support for _early booting_ are booted
    
- Core is _at_ `init`
    1. all added service providers _without_ support for _delayed registration_ which are not registered yet, are registered
    2. all added service providers _with_ support for _delayed registration_ which are not registered yet, are registered
    3. all added service providers which are not booted yet, are booted

To understand if a provider has support for _delayed registration_ or for _early booting_, we have to look at two methods of the `ServiceProvider` interface, respectively `registerLater()` and `bootEarly()`, both returns a boolean.

The `ServiceProvider` interface has a total of 5 methods.
Besides the two already mentioned there's also an `id()` method, and then the two most relevant: `register()` and `boot()`.

The package ships several abstract classes that provides definitions for some of the methods. All of them as an `id()` method that by default returns the name of the class (more on this soon) and define different combination of `registerLater()` and `bootEarly()`. Some of theme also register empty `boot()` or `register()` for provider that needs to, respectively, only register services or only bootstrap them.


#### Available service provider abstract classes

- **`Provider\Booted`** is a provider that requires both `register()` and `boot()` methods to be implemented. It has **no support for delayed registration** and **no support for early booting**.
- **`Provider\BootedOnly`** is a provider that requires only `boot()` method to be implemented (`register()` is implemented with no body). It has **no support for early booting**.
- **`Provider\EarlyBooted`** is a provider that requires both `register()` and `boot()` methods to be implemented. It has **no support for delayed registration**, but **supports early booting**.
- **`Provider\EarlyBootedOnly`** is a provider that requires only `boot()` method to be implemented (`register()` is implemented with no body). It **supports early booting**.
- **`Provider\RegisteredLater`** is a provider that requires both `register()` and `boot()` methods to be implemented. It has **support for delayed registration**, but **no support for early booting**.
- **`Provider\RegisteredLaterEarlyBooted`** is a provider that requires both `register()` and `boot()` methods to be implemented. It has both **support for delayed registration** and **for early booting**.
- **`Provider\RegisteredLaterOnly`** is a providers that requires only `register()` method to be implemented (`boot()` is implemented with no body). It has **support for delayed registration**.
- **`Provider\RegisteredOnly`** is a providers that requires only `register()` method to be implemented (`boot()` is implemented with no body). It has **no support for delayed registration**.

By extending one of these classes, consumers can focus only on the methods that matter.


#### The case for delayed registration

If the reason behind "normal" _VS_ "early" booted providers has been already mentioned (some providers _needs_ to run early, but some other will not be available early) that's not the case for the "delayed registration" that providers can support.

To explain why this is a thing, let's do an example.

Let's assume a _Acme Advanced Logger_ plugin ships a service provider that registers an `Acme\Logger` service.

Then, let's assume a separate plugin _Acme Authentication_ ships a service provider that registers several other services that require `Acme\Logger` service.

The _Acme Authentication_ service provider will need to make sure that the `Acme\Logger` service is available. One common strategy is to **check the container for its availability**, and in case of missing (e.g.  _Acme Advanced Logger_ plugin is deactivated), _Acme Authentication_ registers an alternative logger that could replace the missing service.

For that check for availability to be effective, it must be done **after** _Acme Advanced Logger_ service provider has been registered. By supporting delayed registration, _Acme Authentication_ service provider will surely be registered after _Acme Advanced Logger_ is eventually registered (assuming that is not delayed as well) and so on its `register` method can reliably check if  `Acme\Logger` service is already available or not.



### Service providers ID

`ServiceProvider` interface `id()` method returns an identifier used in several places.

For example, as shown in the ["Package-dependant registration"](#package-dependant-registration) section above, it is passed as argument to the `App::ACTION_REGISTERED_PROVIDER` to allow packages to depend on other packages.

The service provider ID can also be passed to the `Container::hasProvider()` method to know if the given provider has been registered.

All the abstract service provider classes shipped with the package use a trait which, in order:

- checks for the existence of a `$id` public property in the class, and use it if so.
- in case no `$id` public property, checks for the existence of a public `ID` constant in the class, and use it if so.
- if none of the previous apply, uses the class fully qualified name as ID.

So by extending one of the abstract classes and doing nothing else there's already an ID defined, which is the class name.

In case this is not fine for some reason, e.g. the same service provider class is used for several providers, it is possible to define the property, or just override the `id()` method.

**Note**: Provider IDs must be unique. Trying to add a provider with an ID that was already used will just skip the addition, doing nothing else.

### Composing the container

`ServiceProvider::register()` is where providers add services to the Container, so that they will be available to be "consumed" in the `ServiceProvider::boot()` method.

`ServiceProvider::register()` signature is the following:

```php
public function register(Container $container): void;
```

Receiving an instance of the `Container` service providers can _add_ things to it in two ways:

- directly using Pimple `\ArrayAccess` method
- using `Container::addContainer()` which accepts any PSR-11 compatible container and make all the services available in it accessible through the application Container



### Simple service provider example

The container shipped with the package is a PSR-11 container with basic features for _adding_ services that use [Pimple](https://pimple.symfony.com/) behind the scenes.

Besides the two PSR-11 methods, the container has the methods:

- **`Container::addService()`** to add service factory callbacks by ID. Factories passed to this method will be called only once, and then every time `Container::get()` is called, same instance is returned. Uses `Pimple\Container::offsetSet()` behind the scenes.
-  **`Container::addFactory()`** to add service factory callbacks by ID, but factories passed to this method will always be called when  `Container::get()` is called, returning a difference instance. Uses `Pimple\Container::factory()` behind the scenes.
- **`Container::extendService()`** to add a callback that receives a service previously added to the container and the container and return a modified version of the same service. Uses `Pimple\Container::extend()` behind the scenes.

```php
<?php
namespace AcmeInc\Redirector;

use Inpsyde\App\Container;
use Inpsyde\App\Provider\Booted;

final class Provider extends Booted {
    
    private const CONFIG_KEY = 'REDIRECTOR_CONFIG';
   
    public function register(Container $container): bool
    {
        // class names are used as service ids...
      
        $container->addService(
            Config::class,
            static function (Container $container): Config {
                return Config::load($container->config()->get(self::CONFIG_KEY));
            }
        );
        
        $container->addService(
            Redirector::class,
            static function (Container $container): Redirector {
                return new Redirector($container->get(Config::class));
            }
        );
        
        return true;
    }
    
    public function boot(Container $container): bool
    {
        return add_action(
            'template_redirect',
            static function () use ($container) {
                /** @var AcmeInc\Redirector\Redirector $redirector */
                $redirector = $container->get(Redirector::class);
                $redirector->redirect();
            }
        );
    }
}
```



### Service provider example using any PSR-11 container

In the following example I will use [PHP-DI](http://php-di.org), but any PSR-11-compatible container will do.

```php
<?php
namespace AcmeInc\Redirector;

use Inpsyde\App\Provider\Booted;
use Inpsyde\App\Container;

final class Provider extends Booted {
   
    public function register(Container $container): bool
    {
        $diBuilder = new \DI\ContainerBuilder();
        
        if ($container->config()->isProduction()) {
            $cachePath = $container->config()->get('ACME_INC_CACHE_PATH');
            $diBuilder->enableCompilation($cachePath);
        }
        
        $defsPath = $container->config()->get('ACME_INC_DEFS_PATH');
        $diBuilder->addDefinitions("{$defsPath}/redirector/defs.php");
        
        $container->addContainer($diBuilder->build());
        
        return true;
    }
    
    public function boot(Container $container): bool
    {
        return add_action(
            'template_redirect',
            static function () use ($container) {
                /** @var AcmeInc\Redirector\Redirector $redirector */
                $redirector = $container->get(Redirector::class);
                $redirector->redirect();
            }
        );
    }
}
```

Please refer to [PHP-DI documentation](http://php-di.org/doc/) to better understand the code, but again, any PSR-11 compatible Container can be "pushed" to the library Container.



### Website-level providers

`App::new()` returns an instance of the `App` so that it is possible to add providers on the spot, without having to hook `App::ACTION_ADD_PROVIDERS`.

This allow to immediately add service providers shipped at website level.

```php
namespace AcmeInc;

\Inpsyde\App\App::new()
    ->addProvider(new SomeWebsiteProvider())
    ->addProvider(new AnotherWebsiteProvider());
```



### Providers Package

Often times, when using this package, there's need of creating a "package" that is no more than a "collection" of providers.
Not being a plugin or MU plugin, such package will need to be "loaded" manually, because WordPress will not load it, and using autoload for the purpose is not really doable, because using a "file" autoload strategy, the file would be loaded too early, before WP environment is loaded.

The suggested way to deal with this issue is to "load" the package from the same MU plugin that bootstrap the application.
To ease this workflow, the package provides a `ServiceProviders` class, which resemble a collection of providers.

For example, let's assume we are creating a package to provide an authorization system to our application.

The reason why we will create a "library" and not a plugin is that there should be no way to "deactivate" it, being a core feature of the website, and also other plugins and libraries will require it as a dependency.

What we would do **in the package** is to create a package class, that will implement `Inpsyde\App\Provider\Package`: an interface with a single method: `Package::providers()`.

```php
<?php
namespace AcmeInc\Auth;

use Inpsyde\App\Provider;
use Inpsyde\WpContext;

class Auth implements Provider\Package
{
    public function providers(): Provider\ServiceProviders
    {
        return Provider\ServiceProviders::new()
            ->add(new CoreProvider(), WpContext::CORE)
            ->add(new AdminProvider(), WpContext::BACKOFFICE, WpContext::AJAX)
            ->add(new RestProvider(), WpContext::REST, WpContext::AJAX)
            ->add(new FrontProvider(), WpContext::FRONTOFFICE, WpContext::AJAX);
    }
}
```

With such class in place (and autoloadable), in the MU plugin that bootstrap the application we could do:

```php
<?php
namespace AcmeInc;

\Inpsyde\App\App::new()->addPackage(new Auth\Auth());
```



## Advanced topics



### Custom last boot hook

In several places in this README has been said that the last time `App::boot()` is called is `init`.

But reality is that is just the default, and even if this is fine in many cases, it is actually possible to use _any_ hook that runs after `plugins_loaded` for the last "cycle", just keep in mind that:

- using anything earlier that `after_setup_theme` means that themes will not be able to add providers.
- using a late hook, the added providers `boot()` method will not be able to add hooks to anything that happen before the chosen hook, reducing a lot their possibilities

In any case, the way to customize the "last step" hook is to call `App::runLastBootAt()` method:

```php
<?php
namespace AcmeInc;

\Inpsyde\App\App::new()
    ->runLastBootAt('after_setup_theme')
    ->boot();
```

Please note that `App::runLastBootAt()` must be called **before** `App::boot()` is called for first time, or an exception will be thrown.



### Building custom container upfront

Sometimes might be desirable to use a pre-built container to be used for the App. This for example allows for easier usage of a different `SiteConfig` instance (of which `EnvConfig` is an implementation) or adding an arbitrary PSR-11 container _before_ the container is passed to Service Providers.

This is possible by passing a creating an instance of `App\Container`, adding one (or more) PSR-11 container s to it (via the `Container::addContainer` method), then finally passing it to `App\App::new`. For example:

```php
<?php
namespace AcmeInc;

use Inpsyde\App;

// An helper to create App on first call, then always access same instance
function app(): App\App
{
    static $app;
    
    if (!$app) {
        $env = new App\EnvConfig(__NAMESPACE__ . '\\Config', __NAMESPACE__); 
        
        // Build the App container using custom config class
        $container = new App\Container($env);
        
        // Create PSR-11 container and push into the App container
        $diBuilder = new \DI\ContainerBuilder();
        $diBuilder->addDefinitions('./definitions.php');
        $container->addContainer($diBuilder->build());
        
        // Instantiate the app with the container
        $app = App\App::new($container);
    }
    
    return $app;
}

// Finally create and bootstrap app
add_action('muplugins_loaded', [app(), 'boot']);
```


### Resolve objects outside providers

`App` class has a static `App::make()` method that can be used to access objects from container outside any provider.

This can be used in plugins that just want to "quickly" access a service in the Container without writing a provider.

```php
$someService = App::make(AcmeInc\SomeService::class);
```

Because the method is static, it needs to refer to a booted instance of `App`. The one that will be used is **the first `App` that is instantiated** during a request.

Considering that the great majority of times there will be a single application, that is fine and convenient, because allows to resolve services in the container having no access to the container nor to the `App` instance.

If `App::make()` is called before any App has been created at all, an exception will be thrown.

In the case, for any reason, more instances of `App` are created, to resolve a service in a specific `App` instance it is necessary to have access to it and call `resolve()` method on it.

Assuming the code in the previous section, where we defined the `app()` function, we could do something like this to resolve a service:

```php
$someService = app()->resolve(AcmeInc\SomeService::class);
```



### Debug info

The `App` class collects information on the added providers and their status when `WP_DEBUG` is `true`.

`App::debugInfo()`, when debug is on, will return an array that could be something like this:

```
[
    'status' => 'Done with themes'
    'providers' => [
        'AcmeInc\FooProvider' => 'Registered (Registered when registering early),
        'AcmeInc\BarProvider' => 'Booted (Registered when registering early, Booted when booting early),
        'AcmeInc\CliProvider' => 'Skipped (Skipped when registering plugins)',
        'AcmeInc\LoremProvider' => 'Booted (Booted when booting plugins)',
        'AcmeInc\IpsumProvider' => 'Booted (Registered when registering plugins, Booted when booting themes),
        'AcmeInc\DolorProvider' => 'Booted (Registered when registering themes, Booted when booting themes),
        'AcmeInc\SicProvider' => 'Registered (Registered when registering themes),
        'AcmeInc\AmetProvider' => 'Booted (Registered with delay when registering themes, Booted when booting themes),
    ]
]
```

When debug is off, `App::debugInfo()` returns `null`.

To force enabling debug even if `WP_DEBUG` is false, it is possible to call `App::enableDebug()`.

It is also possible to force debug to be disabled, even if `WP_DEBUG` is true, via `App::disableDebug()`.

```php
<?php
namespace AcmeInc;

\Inpsyde\App\App::new()->enableDebug();
```



## Installation

The best way to use this package is through Composer:

```BASH
$ composer require inpsyde/wp-app-container
```


## License

This repository is a free software, and is released under the terms of the GNU General Public License version 2 or (at your option) any later version. See [LICENSE](./LICENSE) for complete license.


## Contributing

All feedback / bug reports / pull requests are welcome.

Before sending a PR make sure that `composer run qa` will output no errors.

It will run, in turn:

- [PHPCS](https://github.com/squizlabs/PHP_CodeSniffer) checks
- [Psalm](https://psalm.dev/) checks
- [PHPUnit](https://phpunit.de/) tests
