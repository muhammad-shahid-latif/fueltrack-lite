<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The fuel network (PSO, Shell, ...) the app is themed for.
 *
 * The choice is resolved once per request by the ResolveBrand middleware:
 * a locked brand from config wins, otherwise the dealer's own pick from the
 * cookie. With no brand the neutral FuelTrack theme is used.
 */
final class Brand
{
    public const COOKIE = 'ft_brand';

    private const LOGO_EXTENSIONS = ['svg', 'png', 'webp', 'jpg'];

    private static ?self $current = null;

    /**
     * @param  array<string, string>  $colors
     * @param  list<string>  $stripe
     */
    private function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $fullName,
        public readonly string $monogram,
        public readonly array $colors,
        public readonly array $stripe,
    ) {}

    /**
     * @return Collection<string, self>
     */
    public static function all(): Collection
    {
        return collect(config('brands', []))->map(fn (array $brand, string $key) => new self(
            key: $key,
            name: $brand['name'],
            fullName: $brand['full_name'],
            monogram: $brand['monogram'],
            colors: $brand['colors'],
            stripe: $brand['stripe'],
        ));
    }

    public static function find(?string $key): ?self
    {
        return $key ? self::all()->get($key) : null;
    }

    public static function locked(): ?self
    {
        return self::find(config('fueltrack.brand'));
    }

    public static function resolve(Request $request): ?self
    {
        return self::locked() ?? self::find($request->cookie(self::COOKIE));
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function setCurrent(?self $brand): void
    {
        self::$current = $brand;
    }

    public function color(string $name): string
    {
        return $this->colors[$name];
    }

    /**
     * Public URL of the logo file, or null until one is added.
     */
    public function logoUrl(): ?string
    {
        foreach (self::LOGO_EXTENSIONS as $extension) {
            $path = "images/brands/{$this->key}.{$extension}";

            if (is_file(public_path($path))) {
                return asset($path).'?v='.filemtime(public_path($path));
            }
        }

        return null;
    }

    /**
     * CSS custom properties that re-skin the whole app.
     */
    public function cssVariables(): string
    {
        $c = $this->colors;

        $variables = [
            '--ft-canopy-900' => $c['surface-900'],
            '--ft-canopy-800' => $c['surface-800'],
            '--ft-canopy-700' => $c['surface-700'],
            '--ft-canopy-500' => $c['surface-500'],
            '--ft-amber' => $c['accent'],
            '--ft-amber-deep' => "color-mix(in srgb, {$c['accent']} 82%, #000)",
            '--ft-amber-soft' => "color-mix(in srgb, {$c['accent']} 72%, #fff)",
            '--ft-on-accent' => $c['on-accent'],
            '--ft-led' => $c['led'],
            '--ft-ground' => $c['ground'],
            '--ft-stripe' => $this->stripeGradient(),
            '--bs-link-color-rgb' => self::rgb($c['surface-700']),
        ];

        return collect($variables)->map(fn (string $value, string $name) => "{$name}: {$value};")->join(' ');
    }

    /**
     * Hard-edged bands, like the colour stripe on a forecourt canopy.
     */
    public function stripeGradient(): string
    {
        $count = count($this->stripe);
        $stops = collect($this->stripe)->flatMap(function (string $color, int $i) use ($count) {
            $from = round($i / $count * 100, 2);
            $to = round(($i + 1) / $count * 100, 2);

            return ["{$color} {$from}%", "{$color} {$to}%"];
        });

        return 'linear-gradient(90deg, '.$stops->join(', ').')';
    }

    private static function rgb(string $hex): string
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return "{$r}, {$g}, {$b}";
    }
}
