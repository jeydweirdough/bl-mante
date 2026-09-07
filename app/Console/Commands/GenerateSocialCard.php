<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Draws the 1200x630 image that appears when a link to the site is shared.
 *
 * It exists as a command rather than a checked-in binary so the card follows
 * the property name and tagline instead of going stale the moment either
 * changes. Re-run it after editing config/hotel.php.
 *
 * PNG rather than SVG on purpose: Facebook, LinkedIn, WhatsApp and X all
 * refuse to render an SVG og:image, and a share card that silently fails to
 * appear is worse than no card at all.
 *
 * This is a typographic placeholder. Replace it with a real photograph of the
 * property before launch -- a share card is often the only image a person sees
 * before deciding whether to click.
 */
class GenerateSocialCard extends Command
{
    protected $signature = 'hotel:social-card
                            {--font= : Path to a TrueType font (defaults to a common system font)}';

    protected $description = 'Generate the Open Graph share card at public/images/social-card.png';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('The gd extension is required. Enable extension=gd in php.ini.');

            return self::FAILURE;
        }

        $font = $this->resolveFont();

        if ($font === null) {
            $this->error('No TrueType font found. Pass one with --font=/path/to/font.ttf');

            return self::FAILURE;
        }

        [$width, $height] = [1200, 630];
        $image = imagecreatetruecolor($width, $height);

        // A vertical gradient, drawn a row at a time. Matches the slate-900
        // used by the site header so the card does not look borrowed.
        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $colour = imagecolorallocate(
                $image,
                (int) (15 + 18 * $t),
                (int) (23 + 25 * $t),
                (int) (42 + 40 * $t),
            );
            imagefilledrectangle($image, 0, $y, $width, $y, $colour);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $muted = imagecolorallocate($image, 148, 163, 184);
        $accent = imagecolorallocate($image, 56, 189, 248);

        // A rule under the wordmark, for something other than centred text.
        imagefilledrectangle($image, 80, 168, 176, 174, $accent);

        imagettftext($image, 26, 0, 80, 130, $muted, $font, mb_strtoupper(
            config('hotel.address.district').' · '.config('hotel.address.city')
        ));

        $this->wrap(
            $image,
            text: config('hotel.name'),
            font: $font,
            size: 72,
            x: 80,
            y: 280,
            lineHeight: 88,
            maxWidth: $width - 160,
            colour: $white,
        );

        $this->wrap(
            $image,
            text: config('hotel.seo.tagline'),
            font: $font,
            size: 34,
            x: 80,
            y: 400,
            lineHeight: 48,
            maxWidth: $width - 200,
            colour: $muted,
        );

        imagettftext($image, 26, 0, 80, 560, $accent, $font, '3 · 6 · 12 · 22 hour stays');

        $path = public_path('images/social-card.png');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        imagepng($image, $path, 9);
        imagedestroy($image);

        $this->info('Wrote '.$path.' ('.number_format(filesize($path) / 1024, 1).' KB)');

        return self::SUCCESS;
    }

    /** Greedy word wrap, because GD has no concept of a text box. */
    private function wrap($image, string $text, string $font, int $size, int $x, int $y, int $lineHeight, int $maxWidth, int $colour): void
    {
        $words = preg_split('/\s+/', $text);
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);
            $box = imagettfbbox($size, 0, $font, $candidate);

            if (($box[2] - $box[0]) > $maxWidth && $line !== '') {
                imagettftext($image, $size, 0, $x, $y, $colour, $font, $line);
                $y += $lineHeight;
                $line = $word;

                continue;
            }

            $line = $candidate;
        }

        if ($line !== '') {
            imagettftext($image, $size, 0, $x, $y, $colour, $font, $line);
        }
    }

    private function resolveFont(): ?string
    {
        $candidates = array_filter([
            $this->option('font'),
            resource_path('fonts/social-card.ttf'),
            'C:/Windows/Fonts/arialbd.ttf',
            'C:/Windows/Fonts/arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
