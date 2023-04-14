<?php
/*
 * This recipe deploys the application into a Docker instance on a server.
 *
 * ! Please note that this solution is not particularly elegant. There is no release history for the Docker environment
 * this way, which means that a defective Docker setup in the release stops the production environment.
 * So far, I would not recommend using this recipe for productive deployments. It can still be greatly improved.
 *
 * The recipe allows the configuration of the production Docker environment to be bundled in the repository and updated
 * on deployment.
 *
 * How it works:
 * - At the beginning of each deployment, the productive environment is first shut down.
 * - The updated Docker configuration is copied from the repository to the server.
 * - The Docker environment is started up again and the further deployment steps are carried out.
 * - PHP & Composer calls are executed in the Docker container.
 */
/*
## Installing

To include recipe in your deploy.php add this line:

```php
require_once __DIR__ . '/vendor/mluex/deployer-recipes/recipe/docker.php';
```

The recipe uses tasks from the common recipe of deployer. Please make sure that you also include this in your deploy.php:

```php
require_once __DIR__ . '/vendor/deployer/deployer/recipe/common.php';
```

Then inject the Docker pipeline into your deployment pipeline at a suitable point, e.g. after updating the codebase on
the server.

```php
after('deploy:update_code', 'deploy:docker');
```

If you want to execute Composer commands in your pipeline, please inject the following step in deploy.php:

```php
before('deploy:vendors', 'deploy:composer:download');
```

Your Docker environment probably needs a few configuration files, which you have also stored in the repo.
You can install these on the production server and update them with each deployment, for example, as follows:

```php
task('deploy:docker:config:update', function() {
    run('mkdir -p {{deploy_path}}/.docker/prod/config/');
    run('cp -Rf {{release_path}}/.docker/prod/config/* {{deploy_path}}/.docker/prod/config/');
});
before('deploy:docker:setup', 'deploy:docker:config:update');
```

## Configuration

 - `docker-compose.yml` - Filename of your docker-compose.yml file for production.
   ```
   set('docker-compose.yml', 'docker-compose.prod.yml');
   ```

 - `docker_account_root` - Web root inside docker container, usually the parent directory of your project root.
   ```
   set('docker_account_root', '/var/www/html');
   ```

 - `docker_deploy_path` - Web root inside docker container, usually the parent directory of your project root.
   ```
   set('docker_deploy_path', '{{docker_account_root}}/my-app');
   ```

 - `docker_options` - Options that are added to the docker exec command, e.g. to execute the command as a specific user.
   ```
   set('docker_options', ' -u myapp:myapp');
   ```

 - `docker_php_container_name` - Name of your PHP container
   ```
   set('docker_php_container_name', 'myapp_php');
   ```

 - `docker/bin/php` - PHP binary path in your PHP container
   ```
   set('docker/bin/php', 'php');
   ```
*/

namespace Deployer;

set('docker-compose.yml', 'docker-compose.yml');
set('bin/docker-compose', function() {
    return locateBinaryPath('docker-compose');
});

set('docker_account_root', '/var/www/html');
set('docker_deploy_path', '{{docker_account_root}}/my-app');
set('docker_release_path', '{{docker_deploy_path}}/release');

set('docker_php_container_name', 'myapp_php');
set('docker_options', '-u myapp:myapp');
set('docker/bin/php', 'php');
set('bin/php', 'docker exec {{docker_options}} {{docker_php_container_name}} {{docker/bin/php}}');

set('composer_phar_url', 'https://getcomposer.org/download/latest-stable/composer.phar');
set('host/bin/composer.phar', '{{deploy_path}}/bin/composer.phar');
set('docker/bin/composer.phar', '{{docker_deploy_path}}/bin/composer.phar');
set('composer_phar_host_dir', function() {
    return dirname(parse('host/bin/composer.phar'));
});
set('bin/composer', '{{bin/php}} {{docker/bin/composer.phar}} -d {{docker_release_path}}');

task('deploy:docker:shutdown', function() {
    $setUp = test("[ -f {{deploy_path}}/{{docker-compose.yml}} ]");
    if ($setUp) {
        run('cd {{deploy_path}} && {{bin/docker-compose}} -f {{docker-compose.yml}} down');
    }
});

task('deploy:docker:setup', function() {
    run('cp -f {{release_path}}/{{docker-compose.yml}} {{deploy_path}}/{{docker-compose.yml}}');
});

task('deploy:docker:boot', function() {
    run('cd {{deploy_path}} && {{bin/docker-compose}} -f {{docker-compose.yml}} up -d');
});

// Download Composer to be available in Docker env
task('deploy:composer:download', function() {
    run('mkdir -p {{composer_phar_host_dir}}');
    run('rm -f {{host/bin/composer.phar}}');
    run('curl -o {{host/bin/composer.phar}} {{composer_phar_url}}');
    run('chmod +x {{host/bin/composer.phar}}');
});

desc('Deploy and update Docker env');
task('deploy:docker', [
    'deploy:docker:shutdown',
    'deploy:docker:setup',
    'deploy:docker:boot',
]);
