<?php

declare(strict_types = 1);

use App\Commands\BuildComposerJson;
use App\Http\DrupalApi;
use App\Http\UpdateFetcher;
use App\ReleaseManager;
use App\SecurityAdvisoryManager;
use App\Container;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\HttpCache\Store;

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('UTC');

$container = new Container();
$container
    ->add('http_client', HttpClient::create())
    ->add('caching_http_client', new CachingHttpClient(
        $container->get('http_client'),
        new Store(
            Container::cacheDir(),
        )
    ))
    ->add('cache', new FilesystemAdapter(marshaller: new DefaultMarshaller()))
    ->add('drupal_api', new DrupalApi(
        // We don't use CachingHttpClient because it doesn't support concurrency. See
        // https://github.com/symfony/symfony/issues/36858. The whole dataset is cached
        // separately by release manager service.
        $container->get('http_client'),
    ))
    ->add('security_advisory_manager', new SecurityAdvisoryManager(
        $container->get('drupal_api'),
        $container->get('cache'),
    ))
    ->add('update_fetcher', new UpdateFetcher(
        $container->get('caching_http_client'),
    ))
    ->add('release_manager', new ReleaseManager(
        $container->get('drupal_api'),
        $container->get('update_fetcher'),
        $container->get('cache'),
    ))
    ->add('file_system', new Filesystem())
;

$app = new Application();
$app->add(new BuildComposerJson(
    $container->get('file_system'),
    $container->get('release_manager')
));

$app->run();
