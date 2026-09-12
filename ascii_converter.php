<?php

function imageToAscii($imagePath, $newWidth = 100) {
    if (!file_exists($imagePath)) {
        return "Image not found!";
    }

    $imageInfo = getimagesize($imagePath);
    $mime = $imageInfo['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($imagePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($imagePath);
            break;
        default:
            return "Unsupported image type!";
    }

    $width = imagesx($image);
    $height = imagesy($image);

    $aspectRatio = $height / $width;
    $newHeight = intval($aspectRatio * $newWidth * 0.55); // 0.55 compensates for character aspect ratio

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // dark to light
    $chars = "@%#*+=-:. ";

    $ascii = "";

    for ($y = 0; $y < $newHeight; $y++) {
        for ($x = 0; $x < $newWidth; $x++) {
            $rgb = imagecolorat($resized, $x, $y);

            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            $gray = ($r + $g + $b) / 3;
            $index = intval(($gray / 255) * (strlen($chars) - 1));
            $ascii .= $chars[$index];
        }
        $ascii .= "\n";
    }

    imagedestroy($image);
    imagedestroy($resized);

    return $ascii;
}