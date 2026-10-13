<?php

namespace App\Support;

/**
 * Procesa las fotos de los estudiantes: recorte cuadrado centrado de 480 px en JPEG.
 * Lo usan la carga manual (PhotoController) y la importación desde Phidias.
 */
class StudentPhoto
{
    public const SIZE = 480;

    /** Devuelve el JPEG cuadrado, o null si el contenido no es una imagen válida. */
    public static function squareJpeg(string $contents): ?string
    {
        if ($contents === '') {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $srcX = (int) (($width - $side) / 2);
        $srcY = (int) (($height - $side) / 2);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($square, $image, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($square, null, 82);
        $jpeg = (string) ob_get_clean();

        imagedestroy($image);
        imagedestroy($square);

        return $jpeg;
    }
}
