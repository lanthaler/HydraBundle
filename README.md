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
production use yet.***

To participate in the development of this bundle, please file bugs and
issues in the issue tracker or submit pull requests. If you have questions
regarding Hydra in general, join the
[Hydra W3C Community Group](http://bit.ly/HydraCG).

You can find an online demo of this bundle as well as more information about
Hydra on my homepage:
http://www.markus-lanthaler.com/hydra


Installation
------------

You can install this bundle by running

    composer require ml/hydra-bundle dev-master

or by adding the package to your composer.json file directly

```json
{
    "require": {
        "ml/hydra-bundle": "dev-master"
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

and import the routes in your `routing.yml` file:

```yaml
hydra:
    resource: "@HydraBundle/Controller/"
    type:     annotation
    prefix:   /
```


Credits
------------

This bundle heavily uses the
[Doctrine Common project](http://www.doctrine-project.org/projects/common.html)
and is inspired by its
[object relational mapper](http://www.doctrine-project.org/projects/orm.html).
The code generation is based on Sensio's
[SensioGeneratorBundle](https://github.com/sensio/SensioGeneratorBundle).
