HydraBundle
==============

Hydra is a lightweight vocabulary to create hypermedia-driven Web APIs. By
specifying a number of concepts commonly used in Web APIs it renders the
creation of generic API clients possible.

This is a [Symfony2](http://www.symfony.com/) bundle which shows how easily
Hydra can be integrated in modern Web frameworks. It acts as a proof of
concept to show how Hydra can simplify the implementation of interoperable
and evolvable RESTful APIs.

***WARNING: This is highly experimental stuff that isn't ready for
production use. It is probably also the ugliest code I ever wrote.***

To participate in the development, please file bugs and issues in the
issue tracker or submit pull requests. If there's enough interest I'll
create a dedicated mailing list in the future.

You can find more information about Hydra on my homepage:
http://www.markus-lanthaler.com/hydra


Installation
------------

You can install this bundle by running

    composer require ml/hydra-bundle

or adding the package to your composer.json file directly:

```json
{
    "minimum-stability": "dev",
    "require": {
        "ml/hydra-bundle": "@dev"
    }
}
```

After you have installed the package, you just need to add the bundle
to your `AppKernel.php` file:

```php
// in AppKernel::registerBundles()
$bundles = array(
    // ...
    new ML\HydraBundle\HydraBundle(),
    // ...
);
```

and import the routes into your `routing.yml` file:

```yaml
hydra:
    resource: "@HydraBundle/Controller/"
    type:     annotation
    prefix:   /
```


Credits
------------

The code of this bundle is heavily inspired by Sensio's
[SensioGeneratorBundle](https://github.com/sensio/SensioGeneratorBundle) and
Nelmio's [NelmioApiDocBundle](https://github.com/nelmio/NelmioApiDocBundle).
Parts of the source code were copied directly from those two bundles.
