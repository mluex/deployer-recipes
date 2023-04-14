# mluex/deployer-recipes

Collection of custom [deployer.org](https://deployer.org/) recipes.

## Installation

The major version of this package describes with which major version of deployer the recipes are compatible.
Use the release line 6.x when you use deployer 6.x.

```shell
composer require mluex/deployer-recipes ^6.0 --dev
```

To include a certain recipe (e.g. recipe-name) in your deployment pipeline:

```php
require 'vendor/mluex/deployer-recipes/recipe/recipe-name.php';
```

## Recipes

| Recipe          | Docs                                                                                        |
|-----------------|---------------------------------------------------------------------------------------------|
| docker          | [click here](https://github.com/mluex/deployer-recipes/blob/6.x/recipe/docker.php)          |
| symfony6-webapp | [click here](https://github.com/mluex/deployer-recipes/blob/6.x/recipe/symfony6-webapp.php) |

## License

Licensed under the MIT license.
