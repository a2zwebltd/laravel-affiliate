<?php

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Yaml\Yaml;

$boostRoot = dirname(__DIR__, 2).'/resources/boost';

it('ships the Boost guideline and skills', function () use ($boostRoot) {
    expect(file_exists($boostRoot.'/guidelines/core.blade.php'))->toBeTrue();
    expect(glob($boostRoot.'/skills/*/SKILL.md'))->not->toBeEmpty();
    expect(file_exists($boostRoot.'/skills/affiliate-integration/SKILL.md'))->toBeTrue();
});

it('gives every skill frontmatter whose name matches its folder', function () use ($boostRoot) {
    foreach (glob($boostRoot.'/skills/*/SKILL.md') as $file) {
        $contents = file_get_contents($file);
        expect(preg_match('/\A---\R(.*?)\R---\R/s', $contents, $m))->toBe(1, "Missing frontmatter in {$file}");

        $meta = class_exists(Yaml::class)
            ? Yaml::parse($m[1])
            : collect(preg_split('/\R/', $m[1]))
                ->mapWithKeys(fn ($line) => str_contains($line, ':')
                    ? [trim(strstr($line, ':', true)) => trim(substr(strstr($line, ':'), 1))]
                    : [])
                ->all();

        expect($meta['name'] ?? null)->toBe(basename(dirname($file)));
        expect(trim((string) ($meta['description'] ?? '')))->not->toBe('');
    }
});

it('renders the guideline through Blade', function () use ($boostRoot) {
    $rendered = Blade::render(file_get_contents($boostRoot.'/guidelines/core.blade.php'));

    expect($rendered)->toContain('## Laravel Affiliate')
        ->and($rendered)->toContain('affiliate-integration');
});
