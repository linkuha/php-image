<?php
/**
 * Created by PhpStorm.
 * User: linkuha (Pudich Aleksandr)
 * Date: 28.08.2019
 * Time: 17:23
 */

namespace SimpleLibs\Image;


class ImageHelper
{
    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    /**
     * Converts a CMYK color to RGB
     *
     * @param float|float[] $c
     * @param float $m
     * @param float $y
     * @param float $k
     *
     * @return float[]
     */
    public static function cmyk2rgb($c, $m = null, $y = null, $k = null)
    {
        if (is_array($c)) {
            list($c, $m, $y, $k) = $c;
        }
        $c *= 255;
        $m *= 255;
        $y *= 255;
        $k *= 255;
        $r = (1 - round(2.55 * ($c + $k)));
        $g = (1 - round(2.55 * ($m + $k)));
        $b = (1 - round(2.55 * ($y + $k)));
        if ($r < 0) {
            $r = 0;
        }
        if ($g < 0) {
            $g = 0;
        }
        if ($b < 0) {
            $b = 0;
        }
        return [
            $r, $g, $b,
            "r" => $r, "g" => $g, "b" => $b
        ];
    }

    public static function isImage($filename)
    {
        $isImage = false;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filename);
        finfo_close($finfo);
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/gif':
            case 'image/png':
            case 'image/bmp':
            case 'image/x-windows-bmp':
                $isImage = true;
                break;
            default:
                $isImage = false;
        }
        return $isImage;
    }

    public static function getBmpSize($data)
    {
        if (substr($data, 0, 2) === "BM") {
            $meta = unpack('vtype/Vfilesize/Vreserved/Voffset/Vheadersize/Vwidth/Vheight', $data);
            return [
                'width' => (int) $meta['width'],
                'height' => (int) $meta['height'],
            ];
        }
        return false;
    }

    /**
     * @param $data
     * @return bool true if first four bytes look like a PNG
     */
    public static function isPNG($data)
    {
        $signature = unpack('LFourCC', $data);
        return ($signature['FourCC'] == 0x474e5089);
    }

    /**
     * Reads the ICONDIR header and verifies it looks sane
     * @param string $data
     * @return array|null - null is returned if the file doesn't look like an .ico file
     */
    public static function getIcondir($data)
    {
        $icondir = unpack('SReserved/SType/SCount', $data);
        if ($icondir['Reserved'] == 0 && $icondir['Type'] == 1) {
            return $icondir;
        }
        return null;
    }

    public static function isICO($data)
    {
        return null !== self::getIcondir($data);
    }

    public static function isJpeg($data)
    {
        return (bin2hex($data[0]) == 'ff' && bin2hex($data[1]) == 'd8');
    }



    public function calculateCropDimensions()
    {

    }

    /**
     * Port and improve from
     * @see https://github.com/Oberto/php-image-magician
     *
     * NOTE: this is done from the UPPER left corner!!
     *     for crop start positions pass padding = 0 and position = 'middle' (thumbnail)
     *
     * @param array $options
     *     @option string  'position'
     *     @option int  'padding'
     *     @option int  'assetWidth'
     *     @option int  'assetHeight'
     * @return array
     */
    public static function calculateOverlayPosition($options)
    {
        foreach (['padding', 'baseWidth', 'baseHeight',
                     'assetWidth', 'assetHeight'] as $option) {
            if (! isset($options[$option]) || ! is_integer($options[$option])) {
                throw new \InvalidArgumentException("Option $option must be set and integer value.");
            }
        }
        if (! isset($options['position'])) {
            $options['position'] = 'bottom-right';
        }
        if (isset($options['padding'])) {
            $padding = $options['padding'];
        } else {
//            $ratioW = $options['baseWidth'] / $options['assetWidth'];
//            $ratioH = $options['baseHeight'] / $options['assetHeight'];
//            $ratio = $ratioW > $ratioH ? $ratioW : $ratioH;
//            $padding = 15 * (1 - $ratio);
            $padding = 0; // todo
        }
        $pos = strtolower($options['position']);
        switch ($pos) {
            default:
            case 'top-left':
                $width = 0 + $padding;
                $height = 0 + $padding;
                break;
            case 'top':
                $width = ($options['baseWidth'] - $options['assetWidth']) / 2;
                $height = 0 + $padding;
                break;
            case 'top-right':
                $width = $options['baseWidth'] - $options['assetWidth'] - $padding;
                $height = 0 + $padding;;
                break;
            case 'left':
                $width = 0 + $padding;
                $height = ($options['baseHeight'] - $options['assetHeight']) / 2;
                break;
            case 'center':
            case 'middle':
                $width = ($options['baseWidth'] - $options['assetWidth']) / 2;
                $height = ($options['baseHeight'] - $options['assetHeight']) / 2;
                break;
            case 'right':
                $width = $options['baseWidth'] - $options['assetWidth'] - $padding;
                $height = ($options['baseHeight'] - $options['assetHeight'] / 2);
                break;
            case 'bottom-left':
                $width = 0 + $padding;
                $height = $options['baseHeight'] - $options['assetHeight'] - $padding;
                break;
            case 'bottom':
                $width = ($options['baseWidth'] - $options['assetWidth']) / 2;
                $height = $options['baseHeight'] - $options['assetHeight'] - $padding;
                break;
            case 'bottom-right':
                $width = $options['baseWidth'] - $options['assetWidth'] - $padding;
                $height = $options['baseHeight'] - $options['assetHeight'] - $padding;
                break;
        }
        return [$width, $height];
    }


    public static function formatColor($value)
    {
        $rgbArray = [];
        if (is_array($value)) {
            if (key($value) == 0 && count($value) == 3) {
                $rgbArray['r'] = $value[0];
                $rgbArray['g'] = $value[1];
                $rgbArray['b'] = $value[2];
            } else {
                $rgbArray = $value;
            }
        } elseif (is_string($value)) {
            if (strtolower($value) === 'transparent') {
                $rgbArray = [
                    'r' => 255,
                    'g' => 255,
                    'b' => 255,
                    'a' => 127
                ];
            } else {
                if (preg_match('^(?:#/\w{3})|(?:\w{6})$/i', $value)) {
                    $rgbArray = self::hex2dec($value);
                }
            }
        }
        return $rgbArray;
    }

    /**
     * Convert #hex color to RGB
     *
     * @param $hex
     * @return array
     */
    public static function hex2dec($hex)
    {
        $color = str_replace('#', '', $hex);
        if (strlen($color) === 3) {
            $color = $color . $color;
        }
        $rgb = [
            'r' => hexdec(substr($color, 0, 2)),
            'g' => hexdec(substr($color, 2, 2)),
            'b' => hexdec(substr($color, 4, 2)),
            'a' => 0
        ];
        return $rgb;
    }
}
