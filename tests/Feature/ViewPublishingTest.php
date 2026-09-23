<?php

use A2ZWeb\Affiliate\AffiliateServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

$packageViews = fn (string $dir): string => realpath(__DIR__.'/../../resources/views/'.$dir);

function normalisePublishMap(array $map): array
{
    $out = [];
    foreach ($map as $from => $to) {
        $out[realpath($from) ?: $from] = $to;
    }

    return $out;
}

it('publishes each view namespace to the directory Laravel reads overrides from', function () use ($packageViews) {
    $blade = normalisePublishMap(ServiceProvider::pathsToPublish(AffiliateServiceProvider::class, 'affiliate-views-blade'));
    $livewire = normalisePublishMap(ServiceProvider::pathsToPublish(AffiliateServiceProvider::class, 'affiliate-views-livewire'));

    expect($blade)->toBe([$packageViews('blade') => resource_path('views/vendor/affiliate')]);
    expect($livewire)->toBe([$packageViews('livewire') => resource_path('views/vendor/affiliate-livewire')]);
});

it('keeps the legacy affiliate-views tag, now publishing both namespaces correctly', function () use ($packageViews) {
    $all = normalisePublishMap(ServiceProvider::pathsToPublish(AffiliateServiceProvider::class, 'affiliate-views'));

    expect($all)->toBe([
        $packageViews('blade') => resource_path('views/vendor/affiliate'),
        $packageViews('livewire') => resource_path('views/vendor/affiliate-livewire'),
    ]);
    expect($all)->not->toContain(resource_path('views/vendor/affiliate/blade'));
});

it('makes published overrides win over the package views', function () {
    $bladeDir = resource_path('views/vendor/affiliate');
    $livewireDir = resource_path('views/vendor/affiliate-livewire');

    try {
        $this->artisan('vendor:publish', ['--tag' => 'affiliate-views', '--force' => true])->assertSuccessful();

        expect(File::exists($bladeDir.'/partner/dashboard.blade.php'))->toBeTrue();
        expect(File::exists($bladeDir.'/partner-statement.blade.php'))->toBeTrue();
        expect(File::exists($livewireDir.'/partner-dashboard.blade.php'))->toBeTrue();
        expect(File::isDirectory($bladeDir.'/blade'))->toBeFalse();
        expect(File::isDirectory($bladeDir.'/livewire'))->toBeFalse();

        // Laravel only registers the vendor override directory when it exists at
        // boot, so re-run the provider's view registration after publishing.
        app('view')->replaceNamespace('affiliate', []);
        app('view')->replaceNamespace('affiliate-livewire', []);
        app('view')->getFinder()->flush();
        $register = new ReflectionMethod(AffiliateServiceProvider::class, 'registerViews');
        $register->invoke(new AffiliateServiceProvider(app()));

        expect(realpath(view('affiliate::partner.dashboard')->getPath()))
            ->toBe(realpath($bladeDir.'/partner/dashboard.blade.php'));
        expect(realpath(view('affiliate-livewire::partner-dashboard')->getPath()))
            ->toBe(realpath($livewireDir.'/partner-dashboard.blade.php'));
    } finally {
        File::deleteDirectory($bladeDir);
        File::deleteDirectory($livewireDir);
    }
});
