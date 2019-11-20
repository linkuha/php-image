<?php
/**
 * Created by PhpStorm.
 * User: linkuha (Pudich Aleksandr)
 * Date: 30.08.2019
 * Time: 20:10
 */

namespace SimpleLibs\Image;

// https://github.com/Oberto/php-image-magician/blob/master/php_image_magician.php
class GdFilter
{
    /**
     * Find optimal sharpness
     *
     * @author Ryan Rud (http://adryrun.com)
     * @param $orig
     * @param $final
     * @return mixed
     */
    private static function findSharp($orig, $final)
    {
        $final  = $final * (750.0 / $orig);
        $a    = 52;
        $b    = -0.27810650887573124;
        $c    = .00047337278106508946;
        $result = $a + $b * $final + $c * $final * $final;
        return max(round($result), 0);
    }

    public static function blackAndWhite(&$img)
    {
        imagefilter($img, IMG_FILTER_GRAYSCALE);
        imagefilter($img, IMG_FILTER_CONTRAST, -1000);
    }

    public static function greyScaleEnhanced(&$img, $width, $aggressiveSharpening = false)
    {
        imagefilter($img, IMG_FILTER_GRAYSCALE);
        imagefilter($img, IMG_FILTER_CONTRAST, -15);
        imagefilter($img, IMG_FILTER_BRIGHTNESS, 2);
        self::sharpen($img, $width, $aggressiveSharpening);
    }

    private static function sharpen(&$img, $width, $aggressiveSharpening)
    {
        $widthOriginal = imagesx($img);
        if (version_compare(PHP_VERSION, '5.1.0') >= 0) {
            if ($aggressiveSharpening) {
                // A more aggressive sharpening solution
                $sharpenMatrix = [
                    [-1, -1, -1],
                    [-1, 16, -1],
                    [-1, -1, -1]
                ];
                $divisor = 8;
                $offset = 0;
                imageconvolution($img, $sharpenMatrix, $divisor, $offset);
            } else {
                // More subtle and personally more desirable
                $sharpness = self::findSharp($widthOriginal, $width);
                $sharpenMatrix = [
                    [-1, -2, -1],
                    [-2, $sharpness + 12, -2], //Lessen the effect
                    // of a filter by increasing the value in the center cell
                    [-1, -2, -1]
                ];
                $divisor = $sharpness; // adjusts brightness
                $offset = 0;
                imageconvolution($img, $sharpenMatrix, $divisor, $offset);
            }
        } else {
            trigger_error('Sharpening required PHP 5.1.0 or greater.');
            return false;
        }
        return true;
    }

    /**
     * @src http://www.php.net/manual/en/function.imagefilter.php#82162
     * @param $img
     * @param int $opacity
     * @return bool
     */
    public static function filterOpacity(&$img, $opacity = 75)
    {
        if (! isset($opacity)) {
            return false;
        }
        if ($opacity == 100) {
            return true;
        }
        $opacity /= 100;

        $w = imagesx($img);
        $h = imagesy($img);

        imagealphablending($img, false);
        //find the most opaque pixel in the image (the one with the smallest alpha value)
        $minAlpha = 127;
        for ($x = 0; $x < $w; $x++)
            for ($y = 0; $y < $h; $y++) {
                $alpha = (imagecolorat($img, $x, $y) >> 24) & 0xFF;
                if ($alpha < $minAlpha) {
                    $minAlpha = $alpha;
                }
            }
        //loop through image pixels and modify alpha for each
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                // get current alpha value (represents the TANSPARENCY!)
                $colorxy = imagecolorat($img, $x, $y);
                $alpha = ($colorxy >> 24) & 0xFF;
                //calculate new alpha
                if ($minAlpha !== 127) {
                    $alpha = 127 + 127 * $opacity * ($alpha - 127) / (127 - $minAlpha);
                } else {
                    $alpha += 127 * $opacity;
                }
                $alphaColor = imagecolorallocatealpha(
                    $img,
                    ($colorxy >> 16) & 0xFF,
                    ($colorxy >> 8) & 0xFF,
                    $colorxy & 0xFF,
                    $alpha
                );
                // set pixel with the new color + opacity
                if (! imagesetpixel($img, $x, $y, $alphaColor)) {
                    return false;
                }
            }
        }
        return true;
    }
}
