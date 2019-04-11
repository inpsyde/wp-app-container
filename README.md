# WP App Container

> DI Container and related tools to be used at website level.


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

This package was not written to be "just a standard", i.e. provide just the abstraction, leaving the implementations to consumers, but instead had been written to be a ready-to-use implementation.

However, an underlying support for [PSR-11](<https://www.php-fig.org/psr/psr-11/>) allows for very flexible usage.


## Concepts overview

### App

 this is the central class of the package. It is the place where "application bootstrapping" happen, where `Service Providers` are registered, and it is very likely the only object that need to be used from the website "package" (that one that "glues" other packages/plugins/themes via Composer).

### Service provider

The package provides a single service provider interface (plus several abstract classes that partially implement it). The objects are used to "compose" the Container. Moreover, in this package implementation, service providers are (or better, could be) responsible to tell how to use the registered services. In WordPress worlds that very likely means to "add hooks".

### Container

This is a "storage" that is capable of storing, and retrieve objects by an unique identifier. On retrieval (oftentimes just the first time they are retrieved), objects are "resolved", meaning that any other object that is required for the target object to be constructed, will first recursively resolved in the container, and then injected in the target object before it is returned. The container implementation shipped here is an extension of Pimple, with added PSR-11 support, with the capability to act as a "proxy" to several other PSR-11 containers. Which means that Service Providers can "compose" the dependency three in the Container either by directly [adding services factories to the underlying Pimple container](<https://pimple.symfony.com/#defining-services>) or they can "append" to the main container a ready-made PSR-11 container.

### Env config

As stated above, this package targets websites development, and something that is going to be required at website level is configuration. Working with WordPress configuration often means PHP constants, but when using Composer at website level, in combination with, for example, WP Starter it also also means environment variables. The package ships an `SiteConfig` interface with an `EnvConfig` implementation that does nothing in the regard of _storing_ configuration, but offers a very flexible way to _read_ configuration both from constants and env vars.
`Container::env()` method returns and instance of `SiteConfig`.

### Context

Service providers job is to both to add services in the container and add the hooks that make use of them, however in WordPress it often happen that services are required under a specific "context". For example, a service provider responsible to register and enqueue assets for the front-end is not required in backoffice (dashboard), nor in AJAX or REST requests and so on. Using the proper hooks to execute code that is something that can be often addressed, but often not. E.g. distinguish a REST request is not very easy at an early hook, or there's no function or constants that tell us when we are on a login page and so on. The scope of this class is to provide a centralized service that provide info on the current request. `Container::context()` method returns and instance of Context.


## Decisions

Because we wanted a ready-to-use package, we needed to pick a DI container _implementation_, and we went for [Pimple](<https://pimple.symfony.com/>), for the very reason that it is one of the simplest implementation out there.

However, as shown later, anyone who want to use a different PSR-11 container will be very able to do so.

In the "Concepts Overview" above, the last two concepts ("Env Config" and "Context") are not really something that are naturally coupled with the other three, however, the assumption that this package will be used for WordPress _websites_ allow us to introduce this "coupling" without many risks (or sense of guilt): assuming we would ship these packages separately, when building websites (which, again, is the only goal of this package) we will very likely going to require those separate packages anyway, making this the perfect example for the _Common-Reuse Principle_: _classes that tend to be reused together belong in the same package together._


## Usage at website level

The "website" package, that will glue together all packages, needs to only interact with the `App` class, in a very simple way:

```php
<?php
namespace AcmeInc;

add_action('muplugins_loaded', [\Inpsyde\App\App::new(__NAMESPACE__), 'boot']);
```

That's it. This code is assumed to be placed in MU plugin, but as better explained later, it is possible to do it outside any MU plugin or plugin, wrapping the `App::boot()` call in a hook or not.

The parameter passed to the static constructor `App::new()` should be the "main" namespace that we will use for the packages in the websites, e.g. for the fictional website where the snippet above is used, we will probably have packages with sub-namespace like `AcmeInc\Theme` or `AcmeInc\SomePlugin`.

Technically it is used on in the `EnvConfig` class which via its `EnvConfig::get()` method, will look for constants in the `AcmeInc` and `AcmeInc\Config` namespace.

This one-liner will create the relevant objects and will fire actions that will enable other packages to register service providers.


## Usage at package level

At package level there are two ways to register services (will be shown later), but first add providers need to be added to the App:

```php
<?php
namespace AcmeInc\Foo;

use Inpsyde\App\App;
use Inpsyde\App\Context;

add_action(
    App::ACTION_ADD_PROVIDERS,
    function (App $app) {
        $app
            ->addProvider(new MainProvider(), Context::CORE)
            ->addProvider(new CronRestProvider(), Context::CRON, Context::REST)
            ->addProvider(new AdminProvider(), Context::BACKOFFICE);
    }
);
```

The hook `App::ACTION_ADD_PROVIDERS` can actually be fired from one to three times, depending on when `App::boot()` is called for the first time.

The entire workflow will be explained below, but for now is relevant that even if the hook is fired more times, the `App` class will be clever enough to add the provider only once.



### Contextual registration

As shown in the example above, `App::addProvider()` besides the service provider itself, accepts a variadic number of context constants, that tell the App that the given provider should be only used in the listed contexts.

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

Besides `App::ACTION_ADD_PROVIDERS` there's another hook that packages can use to register service providers. It is: `App::ACTION_REGISTERED_PROVIDER`.

This hook is fired right after any provider is registered. Using this hook it is possible to register providers only if a given package is registered, allowing to ship libraries / plugins that will likely do nothing if other library / plugin are not available.

```php
<?php
namespace AcmeInc\Foo\Extension;

use Inpsyde\App\App;
use Inpsyde\App\Context;
use AcmeInc\Foo\MainProvider;

add_action(
    App::ACTION_REGISTERED_PROVIDER,
    function (string $providerId, App $app) {
        if ($providerId === MainProvider::class) {
            $app->addProvider(new ExtensionProvider(), Context::CORE);
        }
    },
    10,
    2
);
```

The just-registered package ID is passed as first argument by the hook. By default the package ID is the same as the class, but that can be easily changed, so to be dependant on a package it is necessary to know the ID it uses.


### Providers workflow

As already stated multiple times, the scope of the library is to provide a common ground for service registration and bootstrapping of all packages that compose a website.

This means that it is necessary to allow generic libraries, MU plugins, plugins, and themes, to register their services, which means that application should "wait" for all of those packages to be available.
However, at same time, it is very possible that some packages will need at an early stage in the WordPress loading workflow.

To satisfy both these requirements, the application _might_ run its "bootstrapping procedure" **from one to three times**, depending on when `App::boot()` is called for first time.

If `App::boot()` is called first time **before `plugins_loaded`** hook, it will automatically called again at `plugins_loaded` and `init`.
For a total of 3 times.

If `App::boot()` is called first time **after (or during) `plugins_loaded`  and before `init`** it will automatically called again at `init`.
For a total of 2 times.

If `App::boot()` is called first time **during `init`** it will not be called again, so will run once in total.

If `App::boot()` is called first time **after `init`** an exception will be thrown.

Each time `App::boot()` is called, the `App::ACTION_ADD_PROVIDERS` action is fired allowing packages to add service providers.

Added service providers `register()` method, that add services in the container, is normally _immediately_ called, unless the service provider declare to support "dalayed registration" (more on this soon).

Added service providers `boot()` method, that makes use of the registered services, is normally delayed until last time `App::boot()` is called (WP is at `init` hook), but service providers can declare to support "early booting" (more on this soon), in which case their `boot()` meethod is called immediately after the `register` method.

In case a service provider declare to support both _delayed registration_ and _early booting_ its `register()` method will still be called before its `boot()` method, but _after_ having called the `register()` method of all non-delayed providers that are going to be run.

Considering the case in which `App::boot()` is ran 3 times, (before `plugins_loaded`, on `plugins_loaded`, and on `init`) the order of events is the following:

- Core is _before_ `plugins_loaded`
    1. added service providers _without_ support for _delayed registration_ are registered
    2. added service providers _with_ support for _delayed registration_ and also  _with_ support for _early booting_ are registered
    3. added service providers _with_ support for _early booting_ are booted
    
- Core is _at_ `plugins_loaded`
    1. added service providers _without_ support for _delayed registration_ are registered
    2. added service providers _with_ support for _delayed registration_ and also  _with_ support for _early booting_ are registered
    3. added service providers _with_ support for _early booting_ are booted
    
- Core is _at_ `init`
    1. all added service providers _without_ support for _delayed registration_ which are not registered yet, are registered
    2. all added service providers _with_ support for _delayed registration_ which are not registered yet, are registered
    3. all added service providers which are not booted yet, are booted

To understand if a provider has support for _delayed registration_ or for _early booting_, we have to look at two methods of the ServiceProvider` interface, respectively `registerLater()` and `bootEarly()`, both returns a boolean.

The interface has a total of 5 methods.
Besides the two already mentioned there's also an `id()` method, and then the most relevant: `register()` and `boot()`.

The package ships several abstract classes that provides definitions for many of the methods.


#### Available service provider abstract classes

- **`Provider\Booted`** is a providers that requires both `register()` and `boot()` method to be implemented. It has **no support for delayed registration** and **no support for early booting**.
- **`Provider\BootedOnly`** is a providers that requires only `boot()` method to be implemented (`register()` is implemented with no body). It has **no support for early booting**.
- **`Provider\EarlyBooted`** is a providers that requires both `register()` and `boot()` method to be implemented. It has **no support for delayed registration** but has **supports for early booting**.
- **`Provider\EarlyBootedOnly`** is a providers that requires only `boot()` method to be implemented (`register()` is implemented with no body). It has **support for early booting**.
- **`Provider\RegisteredLater`** is a providers that requires both `register()` and `boot()` method to be implemented. It has **support for delayed registration** but **no support for early booting**.
- **`Provider\RegisteredLaterEarlyBooted`** is a providers that requires both `register()` and `boot()` method to be implemented. It has both **support for delayed registration** and **for early booting**.
- **`Provider\RegisteredLaterOnly`** is a providers that requires only `register()` method to be implemented (`boot()` is implemented with no body). It has **support for delayed registration**.
- **`Provider\RegisteredOnly`** is a providers that requires only `register()` method to be implemented (`boot()` is implemented with no body). It has **no support for delayed registration**.

By extending one of these classes, consumers can focus only on the methods that matter.


#### The case for delayed registration

If the reason behind "normal" _VS_ "early" booted providers should be quite evident, and has been already mentioned (some providers _needs_ to run early but some other will not be available early) that's not the case for the "delayed registration" that providers can support.

To explain why this is a thing, let's do an example.

Let's assume a _Acme Advanced Logger_ plugin has a service provider that registers an `Acme\Logger` service.

Then, let's assume a separate plugin _Acme Authentication_ has a service provider that registers several other services that require `Acme\Logger` service.

The _Acme Authentication_ service provider will need to make sure that the `Acme\Logger` service is available, so it will probably check the container for its availability, and in case it is missing (e.g.  _Acme Advanced Logger_ plugin is deactivated), _Acme Authentication_ will probably register an alternative logger that could replace it.

For that check for availability to be effective, it must be done **after** _Acme Advanced Logger_ service provider has been registered. By supporting delayed registration, _Acme Authentication_ service provider will surely be registered after _Acme Advanced Logger_ is eventually registered (assuming that is not delayed as well) and so on its `register` method can reliably check if  `Acme\Logger` service is already available or not.


### Service providers ID

`ServiceProvider` interface has an `id()` method that returns an identifier used in several places.

For example, as shown in the ["Package-dependant registration"](#package-dependant-registration) section above, it is passed as argument to the `App::ACTION_REGISTERED_PROVIDER` to allow packages to depend on other packages.

The service provider ID can also be passed to the `Container::hasProvider()` method to know if a give provider has been registered.

All the abstract service provider classes shipped with the package use a trait which, in order:

- checks for the existence of a `$id` public property in the class, and use it if so.
- checks for the existence of a `ID` constant in the class, and use it if so.
- uses the class name as ID.

So by just extending one of the abstract classes and doing nothing else there's already an ID defined, which is the class name.

In case this is not fine for some reason, e.g. the same service provider class is used for several providers, it is possible to define the property, or just override the `id()` method.


### Composing the container

`ServiceProvider::register()` is where providers add services to the Container, so that they will be available to be "consumed" in the `ServiceProvider::boot()` method.

`ServiceProvider::register()` signature is the following:

```php
public function register(Container $container): void;
```

Receiving an instance of the `Conatiner` service providers can _add_ things to it in two ways:

- directly using Pimple `\ArrayAccess` method
- using `Container::addContainer()` which accept any PSR-11 compatible container and make all the services available in it accessible through the package Container


### Simple service provider example

```php
<?php
namespace AcmeInc\Redirector;

use Inpsyde\App\Container;
use Inpsyde\App\Provider\Booted;

final class Provider extends Booted {
    
    private const CONFIG_KEY = 'REDIRECTOR_CONFIG';
   
    public function register(Container $container): void
    {
        $container[Config::class] = static function (Container $container) {
            return Config::load($container->env()->get(self::CONFIG_KEY));
        };
        
        $container[Redirector::class] = static function (Container $container) {
            return new Redirector($container->get(Config::class));
        };
    }
    
    public function boot(Container $container): void
    {
        add_action(
            'template_redirect',
            static function () use ($container) {
                /** @var AcmeInc\Redirector\Redirector $redirector */
                $redirector = $container[Redirector::class];
                $redirector->redirect();
            }
        );
    }
}
```

Please refers to [Pimple docs](https://pimple.symfony.com/) for more details on its usage.


### Service provider example using any PSR-11 container

In the following example I will use [PHP-DI](http://php-di.org) but really any PSR-11-compatible container will do.

```php
<?php
namespace AcmeInc\Redirector;

use Inpsyde\App\Provider\Booted;
use Inpsyde\App\Container;

final class Provider extends Booted {
   
    public function register(Container $container): void
    {
        $diBuilder = new \DI\ContainerBuilder();
        
        if ($container->env()->isProduction()) {
            $cachePath = $container->env()->get('ACME_INC_CACHE_PATH');
            $diBuilder->enableCompilation($cachePath);
        }
        
        $defsPath = $container->env()->get('ACME_INC_DEFS_PATH');
        $diBuilder->addDefinitions("{$defsPath}/redirector/defs.php");
        
        $container->addContainer($diBuilder->build());
    }
    
    public function boot(Container $container): void
    {
        add_action(
            'template_redirect',
            static function () use ($container) {
                /** @var AcmeInc\Redirector\Redirector $redirector */
                $redirector = $container[Redirector::class];
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

\Inpsyde\App\App::createAndBoot(__NAMESPACE__)
    ->addProvider(new SomeWebsiteProvider())
    ->addProvider(new AnotherWebsiteProvider());
```


### Providers Package

Often times, when using this package, we found in the need of creating a "package" that is no more than a "collection" of providers.
Not being a plugin or MU plugin, this package needs to be "loaded" manually, because WordPress will not load it, and using autoload
for the purpose is not really doable, because using a "file" autoload strategy, the file would be loaded too early, before WP environment is loaded.

The suggested way to deal with this issue is to "load" the package from the same MU plugin that bootstrap the application.
To ease this workflow, the package provides a `ServiceProviders` class, which resemble a collection of providers.

For example, let's assume we are creating a package to provide an authorization system to our application.

The reason why we will create a "library" and not a plugin is that there should be no way to "deactivate" it, being a core feature of the app, and also more plugins will require it as a dependency.

What we would do in the package is to create a package class, that will implement `Inpsyde\App\Provider\Package`: a very simple interface with a single method: `Package::providers()`.

```php
<?php
namespace AcmeInc\Auth;

use Inpsyde\App\Provider;
use Inpsyde\App\Context;

class Auth implements Provider\Package
{
    public function providers(): Provider\ServiceProviders
    {
        return Provider\ServiceProviders::new()
            ->add(new CoreProvider(), Context::CORE)
            ->add(new AdminProvider(), Context::BACKOFFICE, Context::AJAX)
            ->add(new RestProvider(), Context::REST, Context::AJAX)
            ->add(new FrontProvider(), Context::FRONTOFFICE, Context::AJAX);
    }
}
```

With such class in place (and autoloadable), in the MU plugin that bootstrap the application we could do:

```php
<?php
namespace AcmeInc;

\Inpsyde\App\App::new(__NAMESPACE__)->addPackage(new Auth\Auth());
```


## Advanced topics


### Custom last boot hook

Above in several places have been said that the last time `App::boot()` is called is `init`. Even if this is fine in many cases,
it is actually possible to use _any_ hook that runs after `plugins_loaded`, just keep in mind that:

- using anything earlier that `after_setup_theme` means that themes will not be able to add providers.
- using anything later that `init` providers that will booted so late will not be able to do much.

In any case the way to customize the hook is to call `App::runLastBootAt()` method:

```php
<?php
namespace AcmeInc;

\Inpsyde\App\App::new(__NAMESPACE__)
    ->runLastBootAt('after_setup_theme')
    ->boot();
```

Please not that `App::runLastBootAt()`  must be called **before** `App::boot()` is called for first time, or an exception will be thrown.


### Building custom container upfront

Sometimes might be desirable to use a pre-built container to be used for the App. This for example allow for easier usage of a different `SiteConfig` instance (of which `EnvConfig` is an implementation) or pushing PSR-11 container _before_ the container is passed to Service Providers.

```php
<?php
namespace AcmeInc;

use Inpsyde\App;

// An utility to create App on first call, then always access same instance
function app(): App\App
{
	static $app;
    
    if (!$app) {
        // Something that implements App\SiteConfig
        $env = new CustomConfig(); 
        
        // Build the App container using custom config class
        $container = App\Container($env, Context::create());
        
        // Create PSR-11 container and push into the App container
        $diBuilder = new \DI\ContainerBuilder();
		$diBuilder->addDefinitions('./definitions.php');
        $container->addContainer($diBuilder->build());
        
        // Instantiate the app with the container
        $app = App\App::newWithContainer($container);
    }
    
    return $app;
}

// Finally create and bootstrap app
add_action('muplugins_loaded', [app(), 'boot']);
```


### Resolve objects outside providers

`App` class provide a static `App::make()` method that can be used to access objects from container outside any provider.

This can be used in plugins that just want to access a service in the Container without writing a provider.

```php
$someService = App::make(AcmeInc\SomeService::class);
```

Because the method is static, it needs to refer to a booted instance of `App`.
The one that will be used in the first `App` that is instantiated during a request.

Considering that the great majority of times there will be a single application, that is fine and convenient because allow to resolve services in the container having no access to the container nor to the `App` instance.

If `App::make()` is called without any app has been created at all, an exception will be thrown.

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
    'namespace' => 'AcmeInc'
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

\Inpsyde\App\App::new(__NAMESPACE__)->enableDebug();
```


## Installation

The best way to use this package is through Composer:

```BASH
$ composer require inpsyde/wp-app-container
```


## Crafted by Inpsyde

The team at [Inpsyde](https://inpsyde.com) is engineering the Web since 2006.


## License

Copyright (c) 2019 Inpsyde GmbH

Good news, this library is free for everyone! Since it's released under the [MIT License](LICENSE) you can use it free of charge on your personal or commercial website.


## Contributing

All feedback / bug reports / pull requests are welcome.

Before sending a PR make sure that `composer run qa` will output no errors.

It will run, in turn:

- [PHPCS](https://github.com/squizlabs/PHP_CodeSniffer) checks
- [Phan](https://github.com/phan/phan) checks
- [PHPUnit](https://phpunit.de/) tests