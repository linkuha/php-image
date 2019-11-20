<?php
/**
 * Created by PhpStorm.
 * User: linkuha (Pudich Aleksandr)
 * Date: 27.08.2019
 * Time: 17:33
 */

namespace SimpleLibs\Image;

/**
 * Class GdProcessor
 *
 * This work is licensed under the Creative Commons Attribution 3.0 Unported
 * License. To view a copy of this license,
 * visit http://creativecommons.org/licenses/by/3.0/ or send a letter to
 * Creative Commons, 444 Castro Street, Suite 900, Mountain View, California,
 * 94041, USA.
 *
 * For BMP read:
 * @see http://entropymine.com/jason/bmpsuite/bmpsuite/html/bmpsuite.html
 * @see http://paulbourke.net/dataformats/bitmaps/
 *
 * For ICO use loader below to create a GD object, and then load here:
 * https://github.com/lordelph/icofileloader
 */
class GdProcessor
{
    public $fontsDir = 'fonts';

    public $jpgProgressive = true;
    public $aggressiveSharpening = false;
    public $createCustomTypes = false;

    // red, green, blue, alpha (127 indicates completely transparent)
    private $transparencyColor = [0, 0, 0, 127];

    private $_fileName = '';
    private $_imageOrig = null;
    private $_image = null;

    // meta
    private $_type = null;
    private $_mime = null;
    private $_extension = null; // with dot
    private $_bits = null;
    private $_width = 0;
    private $_height = 0;

    public function __construct()
    {
        if (! $this->checkGd()) {
            throw new \RuntimeException('The GD Library is not installed.');
        }
    }

    public function getWidth()
    {
        return $this->_width;
    }

    public function getHeight()
    {
        return $this->_height;
    }

    public function getImage()
    {
        return $this->_image;
    }

    public function reset()
    {
        $this->_fileName = '';
        $this->_image = null;
        $this->_type = null;
        $this->_extension = null;
        $this->_mime = null;
        $this->_bits = null;
        $this->_width = 0;
        $this->_height = 0;
//        gc_collect_cycles();
    }

    private function allowedImageTypes()
    {
        return [
            IMAGETYPE_GIF,
            IMAGETYPE_PNG,
            IMAGETYPE_BMP,
            IMAGETYPE_ICO,
            IMAGETYPE_JPEG,
//            IMAGETYPE_WEBP
        ];
    }

    private function allowedImageExtensions()
    {
        return ['jpg', 'jpeg', 'gif', 'png', 'bmp', 'ico'];
    }

    public function checkExtension($filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (! in_array(strtolower($extension), $this->allowedImageExtensions())) {
            throw new \InvalidArgumentException('Not supported image extension ' . $extension);
        }
        return $extension;
    }

    public function checkImageType($imageType)
    {
        if (! in_array($imageType, $this->allowedImageTypes())) {
            throw new \InvalidArgumentException('Not supported image type');
        }
        return $imageType;
    }

    public function imageCreate($fileName, $mimeType)
    {
        $image = null;
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
            $image = \imagecreatefromjpeg($fileName);
                break;
            // Portable Network Graphic
            case 'image/png':
                $image = \imagecreatefrompng($fileName);
                break;
            case 'image/gif':
                $image = \imagecreatefromgif($fileName);
                break;
            // Windows Bitmap
            case 'image/x-ms-bmp':
            case 'image/bmp':
            $image = \imagecreatefrombmp($fileName);
                break;
            // Icons, e.g. favicon
            case 'image/ico':       // erroneous
            case 'image/x-icon':    // internet media type
            case 'image/vnd.microsoft.icon':    // IANA
                trigger_error(
                    'ICO images is not supported for load here. 
                        Use "lordelph/icofileloader" library.',
                    E_USER_WARNING
                );
                break;
            // WebP  (alpha supporting)
            // PHP_VERSION_ID >= 70100 && function_exists('imagewebp')
            // imagecreatefromwebp
            default:
                break;
        }
        return $image;
    }

    public function load($file, $imageType = null)
    {
        $this->reset();
        if ($imageType && $this->isGdResource($file)) {
            $this->_type = $this->checkImageType($imageType);
            $this->_mime = image_type_to_mime_type($imageType);
            $this->_extension = image_type_to_extension($imageType);
            $this->_image = $this->_imageOrig = $file;
            $this->_width = imagesx($this->_image);
            $this->_height = imagesy($this->_image);
            return true;
        }
        if (is_string($file) && file_exists($file)) {

            $this->_extension = '.' . $this->_extension;

            $imageData = getimagesize($file); // .webp since php 7.1
            $this->_type = $this->checkImageType($imageData[2]);
            $this->_mime = $imageData['mime'];

            $this->_fileName = $file;

            $this->_width = $imageData[0];
            $this->_height = $imageData[1];
            $this->_bits = $imageData['bits'];

            $this->_image = $this->imageCreate($this->_fileName, $this->_mime);
            $this->_imageOrig = $this->_image;
            return true;
        }
        return false;
    }

    public function saveAsPng($fileName, $quality = 90)
    {
        $mimeCurr = $this->_mime;
        $this->_mime = 'image/png';
        $fileName = preg_replace('/\.[\w\d]+$/i', '.png', $fileName);
        $res = $this->save($fileName, $quality);
        $this->_mime = $mimeCurr;
        return $res;
    }

    /*
     * Exif data will be removed
     */
    public function save($fileName, $quality = 90)
    {
        switch ($this->_mime) {
            case 'image/jpeg':
            case 'image/jpg':
                if ($this->jpgProgressive) {
                    imageinterlace($this->_image, true);
                }
                return \imagejpeg($this->_image, $fileName, $quality);
                break;
            case 'image/png':
                $quality = round(($quality/100) * 9); //  Scale quality from 0-100 to 0-9
                $invertQuality = 9 - $quality;  // Invert quality setting as 0 is best, not 9
                imagesavealpha($this->_image, true);
                imageinterlace($this->_image, true);
                return \imagepng($this->_image, $fileName, $invertQuality);
                break;
            case 'image/gif':
                return \imagegif($this->_image, $fileName);
                break;
            case 'image/x-ms-bmp':
            case 'image/bpm':
//                file_put_contents($fileName, $this->gd2BmpString($this->_image)); // it's works too
//                return true;
                return \imagebmp($this->_image, $fileName);
                break;
            // favicon
            case 'image/ico':
            case 'image/x-icon':
            case 'image/vnd.microsoft.icon':
                return $this->imageico($this->_image, $fileName);
                break;
        }
        return false;
    }

    public function __destruct()
    {
        if (is_resource($this->_image)) {
            imagedestroy($this->_image);
        }
        if (is_resource($this->_imageOrig)) {
            imagedestroy($this->_imageOrig);
        }
    }

    public function switchToOrig()
    {
        if (is_resource($this->_image)) {
            imagedestroy($this->_image);
        }
        $this->_image = $this->_imageOrig;
    }

	// todo check https://stackoverflow.com/questions/3874533/what-could-cause-a-color-index-out-of-range-error-for-imagecolorsforindex/3898007#3898007
    private function keepTransparency(&$targetImg)
    {
        if ($this->_type === IMAGETYPE_PNG) {
            imagealphablending($targetImg, false); // black pixels to transparent in the background

            list($trR, $trG, $trB, $trA) = $this->transparencyColor;
            $transparencyIndex = imagecolorallocatealpha($targetImg, $trR, $trG, $trB, $trA); // with alpha, GD >=2.0.8
            imagecolortransparent($targetImg, $transparencyIndex);

            imagefill($targetImg, 0, 0, $transparencyIndex);
            imagesavealpha($targetImg, true);
        }
        if ($this->_type === IMAGETYPE_GIF) {
            $transparencyIndex = imagecolortransparent($this->_image);
            imagepalettecopy($this->_image, $targetImg);
            if ($transparencyIndex >= 0) {
                $transparencyColors = imagecolorsforindex($this->_image, $transparencyIndex);
                $transparencyNewIndex = imagecolorallocate(
                    $targetImg,
                    $transparencyColors['red'],
                    $transparencyColors['green'],
                    $transparencyColors['blue']
                );
                imagefill($targetImg, 0, 0, $transparencyNewIndex);
                imagecolortransparent($targetImg, $transparencyNewIndex);
            }
        }
    }

    public function resize($width = null, $height = null, $forceStretch = false)
    {
        if (! $this->_image || (! $width && ! $height)) {
            return false;
        }
        if (! in_array($this->_type, array_diff($this->allowedImageTypes(), [IMAGETYPE_ICO]))) {
            trigger_error('ICO is not allowed for resizing.');
            return false;
        }

        if (! $width) {
            if ($this->_height > $height && ! $forceStretch) {
                $sourceRatio = $this->_width / $this->_height;
                $newWidth = round($height * $sourceRatio);
                $newHeight = $height;

//                $ratio = $this->_height / $height;
//                $newWidth = round($this->_width / $ratio);
//                $newHeight = $height;
            } else {
                $newWidth = $this->_width;
                $newHeight = $this->_height;
            }
        } elseif (! $height) {
            if ($this->_width > $width && ! $forceStretch) {
                $sourceRatio = $this->_width / $this->_height;
                $newWidth = $width;
                $newHeight = round($width * $sourceRatio);

//                $ratio = $this->_width / $width;
//                $newWidth = $width;
//                $newHeight = round($this->_height / $ratio);
            } else {
                $newWidth = $this->_width;
                $newHeight = $this->_height;
            }
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        $this->keepTransparency($resizedImage);

        imagecopyresampled(
            $resizedImage,
            $this->_image,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $this->_width,
            $this->_height
        );

        $this->_image = $resizedImage;
        return true;
    }

    public function crop($width = null, $height = null, $fromPointX = 0, $fromPointY = 0)
    {
        if (! $this->_image || (! $width && ! $height)) {
            return false;
        }
        if (! in_array($this->_type, array_diff($this->allowedImageTypes(), [IMAGETYPE_ICO]))) {
            trigger_error('ICO is not allowed for crop.');
            return false;
        }

        $resizedImage = imagecreatetruecolor($width, $height);

        $this->keepTransparency($resizedImage);

        imagecopyresampled(
            $resizedImage,
            $this->_image,
            0,
            0,
            $fromPointX,
            $fromPointY,
            $this->_width,
            $this->_height,
            $this->_width,
            $this->_height
        );

        $this->_image = $resizedImage;
        return true;
    }

    public function thumbnail($width, $height)
    {
        if (! $this->_image || ! $width || ! $height) {
            return false;
        }
        if (! in_array($this->_type, array_diff($this->allowedImageTypes(), [IMAGETYPE_ICO]))) {
            trigger_error('ICO is not allowed for thumbnail.');
            return false;
        }

        $sourceRatio = $this->_width / $this->_height;
        $thumbRatio = $width / $height;

        $newWidth = $this->_width;
        $newHeight = $this->_height;

        if ($sourceRatio !== $thumbRatio) {
            if ($this->_width >= $this->_height) {
                if ($thumbRatio > 1) {
                    $newHeight = $this->_width / $thumbRatio;
                    if ($newHeight > $this->_height) {
                        $newWidth = $this->_height * $thumbRatio;
                        $newHeight = $this->_height;
                    }
                } elseif ($thumbRatio == 1) {
                    $newWidth = $this->_height;
                    $newHeight = $this->_height;
                } else {
                    $newWidth = $this->_height * $thumbRatio;
                }
            } else {
                if ($thumbRatio > 1) {
                    $newHeight = $this->_width / $thumbRatio;
                } elseif ($thumbRatio == 1) {
                    $newWidth = $this->_width;
                    $newHeight = $this->_width;
                } else {
                    $newHeight = $this->_width / $thumbRatio;
                    if ($newHeight > $this->_height) {
                        $newHeight = $this->_height;
                        $newWidth = $this->_height * $thumbRatio;
                    }
                }
            }
        }

        $resizedImage = imagecreatetruecolor($width, $height); // true color for best quality

        $this->keepTransparency($resizedImage);

        imagecopyresampled(
            $resizedImage,
            $this->_image,
            0,
            0,
            round(($this->_width - $newWidth) / 2),
            round(($this->_height - $newHeight) / 2),
            $width,
            $height,
            $newWidth,
            $newHeight
        );

        $this->_image = $resizedImage;
        return true;
    }

    /**
     * @author of method James Heinrich
     * @src http://phpthumb.sourceforge.net
     */
    private function gd2bmpString(&$image)
    {
        $imageX = imagesx($image);
        $imageY = imagesy($image);
        $BMP = '';
        for ($y = ($imageY - 1); $y >= 0; $y--) {
            $thisline = '';
            for ($x = 0; $x < $imageX; $x++) {
                $argb = $this->getPixelColor($image, $x, $y);
                $thisline .= chr($argb['blue']) . chr($argb['green']) . chr($argb['red']);
            }
            while (strlen($thisline) % 4) {
                $thisline .= "\x00";
            }
            $BMP .= $thisline;
        }
        $bmpSize = strlen($BMP) + 14 + 40;
        // BITMAPFILEHEADER [14 bytes] - http://msdn.microsoft.com/library/en-us/gdi/bitmaps_62uq.asp
        $BITMAPFILEHEADER = 'BM';                                    // WORD    bfType;
        $BITMAPFILEHEADER .= $this->littleEndian2String($bmpSize, 4); // DWORD   bfSize;
        $BITMAPFILEHEADER .= $this->littleEndian2String(0, 2); // WORD    bfReserved1;
        $BITMAPFILEHEADER .= $this->littleEndian2String(0, 2); // WORD    bfReserved2;
        $BITMAPFILEHEADER .= $this->littleEndian2String(54, 4); // DWORD   bfOffBits;
        // BITMAPINFOHEADER - [40 bytes] http://msdn.microsoft.com/library/en-us/gdi/bitmaps_1rw2.asp
        $BITMAPINFOHEADER = $this->littleEndian2String(40, 4); // DWORD  biSize;
        $BITMAPINFOHEADER .= $this->littleEndian2String($imageX, 4); // LONG   biWidth;
        $BITMAPINFOHEADER .= $this->littleEndian2String($imageY, 4); // LONG   biHeight;
        $BITMAPINFOHEADER .= $this->littleEndian2String(1, 2); // WORD   biPlanes;
        $BITMAPINFOHEADER .= $this->littleEndian2String(24, 2); // WORD   biBitCount;
        $BITMAPINFOHEADER .= $this->littleEndian2String(0, 4); // DWORD  biCompression;
        $BITMAPINFOHEADER .= $this->littleEndian2String(0, 4); // DWORD  biSizeImage;
        $BITMAPINFOHEADER .= $this->littleEndian2String(2835, 4); // LONG   biXPelsPerMeter;
        $BITMAPINFOHEADER .= $this->littleEndian2String(2835, 4); // LONG   biYPelsPerMeter;
        $BITMAPINFOHEADER .= $this->littleEndian2String(0, 4); // DWORD  biClrUsed;
        $BITMAPINFOHEADER .= $this->littleEndian2String(0, 4); // DWORD  biClrImportant;
        return $BITMAPFILEHEADER . $BITMAPINFOHEADER . $BMP;
    }

    private function getPixelColor(&$img, $x, $y)
    {
        return imagecolorsforindex($img, imagecolorat($img, $x, $y));
    }

    private function littleEndian2String($number, $minBytes = 1)
    {
        $intString = '';
        while ($number > 0) {
            $intString .= chr($number & 255);
            $number >>= 8;
        }
        return str_pad($intString, $minBytes, "\x00", STR_PAD_RIGHT);
    }

    /**
     * Output an ICO image to either the standard output or a file.
     *
     * It takes the same arguments as 'imagepng' from the GD library. Works by
     * creating a ICO container with a single PNG image.
     * This type of ICO image is supported since Windows Vista and by all major
     * browsers.
     *
     * https://en.wikipedia.org/wiki/ICO_(file_format)#PNG_format
     * @param $image
     * @param null $filename
     * @param int $quality
     * @param int $filters
     * @return bool
     */
    public function imageico($image, $filename = null, $quality = 9, $filters = PNG_NO_FILTER)
    {
        $x = imagesx($image);
        $y = imagesy($image);
        if ($x > 256 || $y > 256) {
            trigger_error('ICO images cannot be larger than 256 pixels wide/tall', E_USER_WARNING);
            return false;
        }
        // Collect PNG data.
        ob_start();
        imagesavealpha($image, true);
        imagepng($image, null, $quality, $filters);
        $pngData = ob_get_clean();
        // Write ICO header, image entry and PNG data.
        $icoHeader = pack('v3', 0, 1, 1);
        $icoHeader .= pack('C4v2V2', $x, $y, 0, 0, 1, 32, strlen($pngData), 22);
        // Output to file.
        if ($filename) {
            file_put_contents($filename, $icoHeader . $pngData);
            return true;
        }
        return false;
    }

    /**
     * @param $wmrkPath
     * @param string|array $position One of the pre-defined positions or array [$x, $y] points
     * @param int $padding
     * @param int $opacity min = 0 is fully transparent; max = 100 is not transparent
     * @return bool
     */
    public function addWatermark($wmrkPath, $position, $padding = 0, $opacity = 0)
    {
//        $wmrkExtension = $this->checkExtension($wmrkPath);
        $wmrkData = getimagesize($wmrkPath);
        $wmrkType = $this->checkImageType($wmrkData[2]);
        $wmrkMime = image_type_to_mime_type($wmrkType);

        $stamp = $this->imageCreate($wmrkPath, $wmrkMime);
        $im = $this->_image;

        $sx = imagesx($stamp);
        $sy = imagesy($stamp);

        if (is_array($position) && is_integer($position[0]) && is_integer($position[1])) {
            list($x, $y) = $position;
        } elseif (is_string($position)) {
            list($x, $y) = ImageHelper::calculateOverlayPosition([
                'position' => $position,
                'padding' => $padding,
                'baseWidth' => $this->_width,
                'baseHeight' => $this->_width,
                'assetWidth' => $sx,
                'assetHeight' => $sy
            ]);
        }
        if (! isset($x, $y)) {
            trigger_error(
                'Text position can not be recognized.',
                E_USER_WARNING
            );
            return false;
        }

        if ($wmrkType === IMAGETYPE_PNG) {
            $opacity = $opacity <= 100 ?: 100;
            $opacity = $opacity >= 0 ?: 0;
            GdFilter::filterOpacity($stamp, $opacity); // TODO test need to invert?
        }

        imagecopy($im, $stamp, $x, $y, 0, 0, imagesx($stamp), imagesy($stamp));
        return true;
    }

    private function getTextFont($font)
    {
        $fontPath = $this->fontsDir . '/' . $font;

        if ($font === null || ! file_exists($fontPath)) {
            $fontPath = $this->fontsDir . '/arimo.ttf';   // default
            if (! file_exists($font)) {
                trigger_error(
                    "Font '$font' not found.",
                    E_USER_WARNING
                );
                return false;
            }
        }
        putenv('GDFONTPATH=' . realpath($fontPath));  // check for GD version
        return $font;
    }


    private function getTextSize($fontSize, $angle, $font, $text)
    {
        $box = @imagettfbbox($fontSize, $angle, $font, $text);
        $textWidth = abs($box[4] - $box[0]);
        $textHeight = abs($box[5] - $box[1]); // should also be same as $fontSize
        return [$textWidth, $textHeight];
    }

    /**
     * @see http://php.net/manual/en/function.imagettftext.php
     * @param string $text
     * @param string|array $position One of the pre-defined positions or array [$x, $y] points
     * @param int $padding
     * @param string $fontColor
     * @param int $fontSize
     * @param int $angle
     * @param null|string $font
     * @return bool
     */
    public function addText($text, $position = 'top-left', $padding = 0,
                            $fontColor = '#fff', $fontSize = 12, $angle = 0, $font = null
    ) {
        $rgbArray = ImageHelper::formatColor($fontColor);
        $r = $rgbArray['r'];
        $g = $rgbArray['g'];
        $b = $rgbArray['b'];

        $font = $this->getTextFont($font);
        list($textWidth, $textHeight) = $this->getTextSize($fontSize, $angle, $font, $text);

        if (is_array($position) && is_integer($position[0]) && is_integer($position[1])) {
            list($x, $y) = $position;
        } elseif (is_string($position)) {
            list($x, $y) = ImageHelper::calculateOverlayPosition([
                'position' => $position,
                'padding' => $padding,
                'assetWidth' => $textWidth,
                'assetHeight' => $textHeight,
                'baseWidth' => $this->_width,
                'baseHeight' => $this->_height,
            ]);
        }
        if (! isset($x, $y)) {
            trigger_error(
                'Text position can not be recognized.',
                E_USER_WARNING
            );
            return false;
        }
        $fontColor = imagecolorallocate($this->_image, $r, $g, $b);
        imagettftext($this->_image, $fontSize, $angle, $x, $y, $fontColor, $font, $text);
        return true;
    }

    /**
     * The default direction of imageRotate() is counter clockwise.
     *
     * @param int|string $value
     *     (int) number of degress to rotate image
     *     (str) param "left": rotate left
     *     (str) param "right": rotate right
     *     (str) param "upside": upside-down image
     * @param string $bgColor
     */
    public function rotate($value = 90, $bgColor = 'transparent')
    {
        if (is_integer($value)) {
            $degrees = $value;
        }

        $rgbArray = ImageHelper::formatColor($bgColor);
        $r = $rgbArray['r'];
        $g = $rgbArray['g'];
        $b = $rgbArray['b'];
        $a = isset($rgbArray['a']) ? $rgbArray['a'] : 127;

        if (is_string($value)) {
            $value = strtolower($value);
            switch ($value) {
                case 'left':
                    $degrees = 90;
                    break;
                case 'right':
                    $degrees = 270;
                    break;
                case 'upside':
                    $degrees = 180;
                    break;
                default:
                    break;
            }
        }
        $degrees = 360 - (isset($degrees) ? $degrees : 270);
        $bg = imagecolorallocatealpha($this->_image, $r, $g, $b, $a);
        imagefill($this->_image, 0, 0, $bg);
        $this->_image = imagerotate($this->_image, $degrees, $bg);
        imagesavealpha($this->_image, true);
    }

    public function checkGd()
    {
        return extension_loaded('gd') && function_exists('gd_info');
    }

    public function getInfo()
    {
        return gd_info();
    }

    public function isGdResource($file)
    {
        return is_resource($file) && get_resource_type($file) === 'gd';
    }
}
