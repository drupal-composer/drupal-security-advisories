<?php

declare(strict_types = 1);

use App\Commands\BuildComposerJson;
use App\FileSystem;
use App\Http\ProjectReleaseFetcher;
use App\Http\UpdateFetcher;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\HttpCache\Store;
use function App\time;

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('UTC');
time();

$httpClient = new CachingHttpClient(
    HttpClient::create(),
    new Store(getenv('DSA_CACHE_DIR') ?: '/tmp/symfony-cache')
);
$fileSystem = new FileSystem(__DIR__ . '/build');

$app = new Application();
$app->add(new BuildComposerJson(
    $fileSystem,
    new ProjectReleaseFetcher($httpClient),
    new UpdateFetcher($httpClient)
));
$app->run();
