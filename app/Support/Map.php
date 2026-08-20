<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tile 7 map.
 *
 * Renders a static 3x3 OpenStreetMap tile mosaic positioned so the target
 * coordinate lands dead centre of the card. No Leaflet, no JS, no API key:
 * the tile is decorative and non-interactive in the concept art, so an
 * interactive map library would be ~150KB for zero visible gain.
 *
 * lucent: static mosaic; upgrade path is self-hosted Leaflet in public/assets
 * if pan/zoom is ever wanted.
 */
final class Map
{
    private const TILE = 256;
    private const ZOOM = 13;
    private const SPAN = 3; // 3x3 tiles

    public static function render(float $lat, float $lng, string $label): string
    {
        [$originX, $originY, $offsetX, $offsetY] = self::geometry($lat, $lng);

        $tiles = '';
        for ($row = 0; $row < self::SPAN; $row++) {
            for ($col = 0; $col < self::SPAN; $col++) {
                $x = $originX + $col;
                $y = $originY + $row;
                $url = sprintf('https://tile.openstreetmap.org/%d/%d/%d.png', self::ZOOM, $x, $y);
                $tiles .= '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="" width="256" height="256" loading="lazy">';
            }
        }

        $style = sprintf('left:calc(50%% - %.1fpx);top:calc(50%% - %.1fpx)', $offsetX, $offsetY);
        $alt = $label !== '' ? 'Map of ' . $label : 'Map';

        return '<div class="map" role="img" aria-label="' . htmlspecialchars($alt, ENT_QUOTES) . '">'
            . '<div class="map-mosaic" style="' . $style . '">' . $tiles . '</div>'
            . '<span class="map-marker" aria-hidden="true"></span>'
            . '</div>';
    }

    /**
     * Web-Mercator slippy-tile maths.
     *
     * @return array{0:int,1:int,2:float,3:float} originTileX, originTileY, px offset within mosaic
     */
    public static function geometry(float $lat, float $lng): array
    {
        $n = 2 ** self::ZOOM;
        $latRad = deg2rad(max(-85.05112878, min(85.05112878, $lat)));

        $worldX = (($lng + 180.0) / 360.0) * $n;
        $worldY = (1.0 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2.0 * $n;

        $edge = intdiv(self::SPAN, 2);
        $originX = (int) floor($worldX) - $edge;
        $originY = (int) floor($worldY) - $edge;

        return [
            $originX,
            $originY,
            $worldX * self::TILE - $originX * self::TILE,
            $worldY * self::TILE - $originY * self::TILE,
        ];
    }

    public static function localTime(?string $timezone): string
    {
        try {
            $tz = new \DateTimeZone($timezone ?: date_default_timezone_get());
        } catch (\Exception) {
            $tz = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $tz))->format('H:i');
    }
}

// Sanity check: at zoom 13 the offset must land inside the middle tile of the
// mosaic, i.e. between one and two tile widths from the mosaic origin.
assert((static function (): bool {
    [, , $x, $y] = Map::geometry(38.7223, -9.1393); // Lisbon
    return $x >= 256 && $x < 512 && $y >= 256 && $y < 512;
})());
