<?php
/**
 * Created by PhpStorm.
 * User: linkuha (Pudich Aleksandr)
 * Date: 29.08.2019
 * Time: 7:22
 */

namespace SimpleLibs\Image;

/**
 * Class ExifHelper
 * for more complex use try https://github.com/PHPExif/php-exif
 *
 * @see https://www.awaresystems.be/imaging/tiff/tifftags/privateifd/exif.html
 * @package App\Helpers\Image
 */
class ExifReader
{
    const SECTION_FILE      = 'FILE';
    const SECTION_COMPUTED  = 'COMPUTED';
    const SECTION_IFD0      = 'IFD0';
    const SECTION_THUMBNAIL = 'THUMBNAIL';
    const SECTION_COMMENT   = 'COMMENT';
    const SECTION_EXIF      = 'EXIF';
    const SECTION_ALL       = 'ANY_TAG';
    const SECTION_IPTC      = 'IPTC';

    public static $sections = [
        self::SECTION_FILE,
        self::SECTION_COMPUTED,
        self::SECTION_IFD0,
        self::SECTION_THUMBNAIL,
        self::SECTION_COMMENT,
        self::SECTION_EXIF,
        self::SECTION_ALL,
        self::SECTION_IPTC,
    ];

    /**
     * Get image EXIF data
     * since php 7.2 filename param replaced to stream
     *
     * [
     *     exposure - sec
     *     aperture -
     *     focalLength - mm
     * ]
     * @param string $filename
     * @return mixed
     */
    public function getExif($filename)
    {
        if (! self::checkExif()) {
            trigger_error(
                'The EXIF Library is not installed.',
                E_USER_WARNING
            );
            return [];
        };
        if (! file_exists($filename)) {
            trigger_error(
                'Image not found.',
                E_USER_WARNING
            );
            return [];
        };
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if ($extension !== 'jpg') { // TODO tiff?
            trigger_error(
                'Metadata not supported for this image type.',
                E_USER_WARNING
            );
            return [];
        };
        $exifData = exif_read_data($filename, 'IFD0');
        if (! is_array($exifData) || empty($exifData)) {
            return [];
        }
        $exifDataArray = $this->handleRawData($exifData);
        ksort($exifDataArray);
        return $exifDataArray;
    }

    protected function handleRawData(array $exifData)
    {
        $gpsData = [];
        $resultArray = [];
        foreach ($exifData as $field => $value) {
            if (in_array($field, self::$sections) && is_array($value)) {
                $subData = $this->handleRawData($value);

                $resultArray = array_merge($resultArray, $subData);
                continue;
            }

            switch ($field) {
                case 'ApertureValue':
                    $apPiecesArray = explode('/', $value);
                    if (count($apPiecesArray) == 2) {
                        $apertureValue = round($apPiecesArray[0] / $apPiecesArray[1], 2, PHP_ROUND_HALF_DOWN);
                    } else {
                        $apertureValue = '';
                    }
                    $resultArray['aperture'] = $apertureValue;
                    break;
                case 'ApertureFNumber':
                    $resultArray['f-stop'] = $value;
                    break;
                case 'FNumber':
                    $fnPiecesArray = explode('/', $value);
                    if (count($fnPiecesArray) == 2) {
                        $fNumber = $fnPiecesArray[0] / $fnPiecesArray[1];
                    } else {
                        $fNumber = '';
                    }
                    $resultArray['fnumber'] = $fNumber;
                    break;
                case 'FocalLength':
                    $flPiecesArray = explode('/', $value);
                    // Avoid division by zero if focal length is invalid
                    if (end($flPiecesArray) == '0') {
                        $value = 0;
                    } else {
                        $value = (int) reset($flPiecesArray) / (int) end($flPiecesArray);
                    }
                    $resultArray['focalLength'] = $value;
                    break;
                case 'ExposureTime':
                    if (! is_float($value)) {
                        $value = $this->normalizeComponent($value);
                    }
                    // Based on the source code of Exiftool (PrintExposureTime subroutine):
                    // http://cpansearch.perl.org/src/EXIFTOOL/Image-ExifTool-9.90/lib/Image/ExifTool/Exif.pm
                    if ($value < 0.25001 && $value > 0) {
                        $value = sprintf('1/%d', intval(0.5 + 1 / $value));
                    } else {
                        $value = sprintf('%.1f', $value);
                        $value = preg_replace('/.0$/', '', $value);
                    }
                    $resultArray['exposure'] = $value;
                    break;
                case 'ExposureProgram':
                    $ep = self::resolveExposureProgram($value);
                    $resultArray['exposureProgram'] = $ep;
                    break;
                case 'MeteringMode':
                    $mm = self::resolveMeteringMode($value);
                    $resultArray['meteringMode'] = $mm;
                    break;
                case 'Flash':
                    $resultArray['flashStatus'] = $value;
                    $resultArray['flashLabel'] = self::resolveFlash($value);
                    break;
                case 'Make':
                case 'Model':
                case 'Artist':
                case 'Copyright':
                case 'Orientation':
                    $resultArray[strtolower($field)] = $value;
                    break;
                case 'DateTime':
                    $resultArray['date'] = $value;
                    break;
                case 'ISOSpeedRatings':
                    $resultArray['iso'] = $value;
                    break;
                case 'XResolution':
                    $resolutionParts = explode('/', $value);
                    $resultArray['horizontalResolution'] = (int) reset($resolutionParts);
                    break;
                case 'YResolution':
                    $resolutionParts = explode('/', $value);
                    $resultArray['verticalResolution'] = (int) reset($resolutionParts);
                    break;
                case 'GPSLatitude':
                    $gpsData['lat'] = $this->extractGPSCoordinate($value);
                    break;
                case 'GPSLongitude':
                    $gpsData['lon'] = $this->extractGPSCoordinate($value);
                    break;
            }
        }
        if (count($gpsData) === 2) {
            $latitudeRef = empty($data['GPSLatitudeRef'][0]) ? 'N' : $data['GPSLatitudeRef'][0];
            $longitudeRef = empty($data['GPSLongitudeRef'][0]) ? 'E' : $data['GPSLongitudeRef'][0];

            $gpsLocation = sprintf(
                '%s,%s',
                (strtoupper($latitudeRef) === 'S' ? -1 : 1) * $gpsData['lat'],
                (strtoupper($longitudeRef) === 'W' ? -1 : 1) * $gpsData['lon']
            );

            $resultArray['gps'] = $gpsLocation;
        }
        return $resultArray;
    }

    /**
     * Extract GPS coordinates from components array
     *
     * @param array|string $components
     * @return float
     */
    private function extractGPSCoordinate($components)
    {
        if (! is_array($components)) {
            $components = array($components);
        }
        $components = array_map(array($this, 'normalizeComponent'), $components);

        if (count($components) > 2) {
            return floatval($components[0]) + (floatval($components[1]) / 60) + (floatval($components[2]) / 3600);
        }

        return reset($components);
    }

    /**
     * Normalize component
     *
     * @param mixed $component
     * @return int|float
     */
    private function normalizeComponent($component)
    {
        $parts = explode('/', $component);

        if (count($parts) > 1) {
            if ($parts[1]) {
                return intval($parts[0]) / intval($parts[1]);
            }

            return 0;
        }

        return floatval(reset($parts));
    }

    private function resolveExposureProgram($ep)
    {
        switch ($ep) {
            case 0:
                $ep = '';
                break;
            case 1:
                $ep = 'manual';
                break;
            case 2:
                $ep = 'normal program';
                break;
            case 3:
                $ep = 'aperture priority';
                break;
            case 4:
                $ep = 'shutter priority';
                break;
            case 5:
                $ep = 'creative program';
                break;
            case 6:
                $ep = 'action program';
                break;
            case 7:
                $ep = 'portrait mode';
                break;
            case 8:
                $ep = 'landscape mode';
                break;
            default:
                break;
        }
        return $ep;
    }

    private function resolveMeteringMode($mm)
    {
        switch ($mm) {
            case 0:
                $mm = 'unknown';
                break;
            case 1:
                $mm = 'average';
                break;
            case 2:
                $mm = 'center weighted average';
                break;
            case 3:
                $mm = 'spot';
                break;
            case 4:
                $mm = 'multi spot';
                break;
            case 5:
                $mm = 'pattern';
                break;
            case 6:
                $mm = 'partial';
                break;
            case 255:
                $mm = 'other';
                break;
            default:
                break;
        }
        return $mm;
    }

    private function resolveFlash($flash)
    {
        switch ($flash) {
            case 0:
                $flash = 'flash did not fire';
                break;
            case 1:
                $flash = 'flash fired';
                break;
            case 5:
                $flash = 'strobe return light not detected';
                break;
            case 7:
                $flash = 'strobe return light detected';
                break;
            case 9:
                $flash = 'flash fired, compulsory flash mode';
                break;
            case 13:
                $flash = 'flash fired, compulsory flash mode, return light not detected';
                break;
            case 15:
                $flash = 'flash fired, compulsory flash mode, return light detected';
                break;
            case 16:
                $flash = 'flash did not fire, compulsory flash mode';
                break;
            case 24:
                $flash = 'flash did not fire, auto mode';
                break;
            case 25:
                $flash = 'flash fired, auto mode';
                break;
            case 29:
                $flash = 'flash fired, auto mode, return light not detected';
                break;
            case 31:
                $flash = 'flash fired, auto mode, return light detected';
                break;
            case 32:
                $flash = 'no flash function';
                break;
            case 65:
                $flash = 'flash fired, red-eye reduction mode';
                break;
            case 69:
                $flash = 'flash fired, red-eye reduction mode, return light not detected';
                break;
            case 71:
                $flash = 'flash fired, red-eye reduction mode, return light detected';
                break;
            case 73:
                $flash = 'flash fired, compulsory flash mode, red-eye reduction mode';
                break;
            case 77:
                $flash = 'flash fired, compulsory flash mode, red-eye reduction mode, return light not detected';
                break;
            case 79:
                $flash = 'flash fired, compulsory flash mode, red-eye reduction mode, return light detected';
                break;
            case 89:
                $flash = 'flash fired, auto mode, red-eye reduction mode';
                break;
            case 93:
                $flash = 'flash fired, auto mode, return light not detected, red-eye reduction mode';
                break;
            case 95:
                $flash = 'flash fired, auto mode, return light detected, red-eye reduction mode';
                break;
            default:
                break;
        }
        return $flash;
    }

    public static function checkExif()
    {
        return extension_loaded('mbstring') && extension_loaded('exif');
    }
}
