<?php
/*
 * This recipe contains all steps to deploy a Symfony 6 webapp (i.e. Symfony with Doctrine ORM & Webpack Encore).
 *
 * It is based on the official symfony4 recipe from deployer.
 * @see https://github.com/deployphp/deployer/blob/6.x/recipe/symfony4.php
 *
 * Please note: The build process is executed locally on the machine that also runs deployer.
 * Therefore, yarn + NodeJS must be installed on this machine!
 *
 * The following additions have been made here:
 *  -  Execute Doctrine Migrations.
 *  -  Build pipeline for Webpack Encore assets incl. upload.
 */
/*
## Installing

To include recipe in your deploy.php add this line:

```php
require_once __DIR__ . '/vendor/mluex/deployer-recipes/recipe/symfony6-webapp.php';
```
*/

namespace Deployer;

use Deployer\Exception\Exception;
use Deployer\Host\Localhost;
use Deployer\Task\Context;

set('shared_dirs', ['var/log', 'var/sessions']);
set('shared_files', ['.env.local.php', '.env.local']);
set('writable_dirs', ['var/cache', 'var/log', 'var/sessions']);
set('migrations_config', '');
set('build_path', '/'); # TODO

set('bin/yarn', function () {
    return locateBinaryPath('yarn');
});

set('bin/console', function () {
    return parse('{{release_path}}/bin/console');
});

set('console_options', function () {
    return '--no-interaction';
});

if (!function_exists('parse_home_dir')) {
    /**
     * Expand leading tilde (~) symbol in given path.
     * @author Anton Medvedev <anton@medv.io>
     */
    function parse_home_dir(string $path): string
    {
        if ('~' === $path || 0 === strpos($path, '~/')) {
            if (isset($_SERVER['HOME'])) {
                $home = $_SERVER['HOME'];
            } elseif (isset($_SERVER['HOMEDRIVE'], $_SERVER['HOMEPATH'])) {
                $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
            } else {
                return $path;
            }

            return $home . substr($path, 1);
        }

        return $path;
    }
}

if (!function_exists('resolveCliArguments')) {
    function resolveCliArguments(string $arguments): string
    {
        return preg_replace_callback(
            '/(\s)(~\/.+?)(\s)/',
            function($matches) {
                return $matches[1] . parse_home_dir($matches[2]) . $matches[3];
            },
            $arguments
        );
    }
}

if (!function_exists('rsyncUpload')) {
    /**
     * Upload file or directory to host
     * Unlike deployer's upload function, the parameters for rsync correct are escaped here to support relative paths
     * for the SSH config and keys.
     *
     * @param string $source
     * @param string $destination
     * @param array  $config
     *
     * @throws Exception\Exception
     */
    function rsyncUpload($source, $destination, array $config = []): void
    {
        $rsync = Deployer::get()->rsync;
        $host = Context::get()->getHost();
        $source = parse($source);
        $destination = parse($destination);

        if ($host instanceof Localhost) {
            $rsync->call($host->getHostname(), $source, $destination, $config);
        } else {
            if (!isset($config['options']) || !is_array($config['options'])) {
                $config['options'] = [];
            }

            $sshArguments = resolveCliArguments($host->getSshArguments()->getCliArguments());
            if (empty($sshArguments) === false) {
                $config['options'][] = "-e 'ssh $sshArguments'";
            }

            if ($host->has("become")) {
                $config['options'][]  = "--rsync-path='sudo -H -u " . $host->get('become') . " rsync'";
            }

            $rsync->call($host->getHostname(), $source, "$host:$destination", $config);
        }
    }
}

desc('Build assets');
task('build:yarn:install', function() {
    if (!commandExist(parse('bin/yarn'))) {
        throw new Exception(
            'yarn is not installed, which is why the build pipeline cannot be executed.'
        );
    }

    run('cd {{build_path}} && {{bin/yarn}} install');
})->local();
task('build:yarn:build', function() {
    if (!commandExist(parse('bin/yarn'))) {
        throw new Exception(
            'yarn is not installed, which is why the build pipeline cannot be executed.'
        );
    }

    run('cd {{build_path}} && {{bin/yarn}} build');
})->local();
task('build', [
    'build:yarn:install',
    'build:yarn:build',
]);

// Upload build artifacts
task('build:upload', function () {
    rsyncUpload('{{build_path}}/public/build', '{{release_path}}/public');
});

desc('Migrate database');
task('database:migrate', function () {
    $options = '--allow-no-migration';
    if (get('migrations_config') !== '') {
        $options = sprintf('%s --configuration={{release_path}}/{{migrations_config}}', $options);
    }

    run(sprintf('{{bin/php}} {{bin/console}} doctrine:migrations:migrate %s {{console_options}}', $options));
});

desc('Clear cache');
task('deploy:cache:clear', function () {
    run('{{bin/php}} {{bin/console}} cache:clear {{console_options}} --no-warmup');
});

desc('Warm up cache');
task('deploy:cache:warmup', function () {
    run('{{bin/php}} {{bin/console}} cache:warmup {{console_options}}');
});

desc('Deploy project');
task('deploy', [
    'deploy:info',
    'build',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'database:migrate',
    'build:upload',
    'deploy:cache:clear',
    'deploy:cache:warmup',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
]);

after('deploy', 'success');