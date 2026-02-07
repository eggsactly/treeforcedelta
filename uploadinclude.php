<?php

function exifFractionToFloat($value): ?float
{
    if (is_string($value) && strpos($value, '/') !== false) {
        [$num, $den] = explode('/', $value, 2);
        if ((float)$den === 0.0) {
            return null;
        }
        return (float)$num / (float)$den;
    }

    if (is_numeric($value)) {
        return (float)$value;
    }

    return null;
}

function exifGpsToDecimal(array $coord, string $ref): ?float
{
    if (count($coord) !== 3) {
        return null;
    }

    $degrees = exifFractionToFloat($coord[0]);
    $minutes = exifFractionToFloat($coord[1]);
    $seconds = exifFractionToFloat($coord[2]);

    if ($degrees === null || $minutes === null || $seconds === null) {
        return null;
    }

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if ($ref === 'S' || $ref === 'W') {
        $decimal *= -1;
    }

    return $decimal;
}

function getImageGpsCoordinates(string $imagePath): ?array
{
    if (!function_exists('exif_read_data')) {
        return null;
    }

    if (!is_file($imagePath)) {
        return null;
    }

    $exif = @exif_read_data($imagePath);

    if (!$exif || empty($exif['GPSLatitude']) || empty($exif['GPSLongitude']) ||
        empty($exif['GPSLatitudeRef']) || empty($exif['GPSLongitudeRef'])) {
        return null;
    }

    $lat = exifGpsToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
    $lon = exifGpsToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef']);

    if ($lat === null || $lon === null) {
        return null;
    }

    return [
        'latitude'  => $lat,
        'longitude' => $lon
    ];
}

function codeToMessage($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
            break;
        case UPLOAD_ERR_PARTIAL:
            $message = "The uploaded file was only partially uploaded";
            break;
        case UPLOAD_ERR_NO_FILE:
            $message = "No file was uploaded";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $message = "Missing a temporary folder";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $message = "Failed to write file to disk";
            break;
        case UPLOAD_ERR_EXTENSION:
            $message = "File upload stopped by extension";
            break;

        default:
            $message = "Unknown upload error";
            break;
    }
    return $message;
}

?>
