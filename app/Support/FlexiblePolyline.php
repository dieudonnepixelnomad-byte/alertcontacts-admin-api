<?php

namespace App\Support;

/**
 * Flexible Polyline — format d'encodage de géométrie propre à HERE (open source).
 *
 * Portage de l'implémentation de référence heremaps/flexible-polyline.
 * CDC V4.1 §14.1 point 4 : aucun décodeur PHP officiel n'étant publié, on
 * porte l'algorithme, qui est court et stable.
 *
 * Le format encode des deltas en base64url avec un facteur de précision
 * variable, et supporte une troisième dimension optionnelle (altitude,
 * élévation…) — ignorée ici, AlertContacts routant en 2D.
 *
 * Convention de sortie : liste de points [lat, lng], comme App\Support\Geo.
 */
final class FlexiblePolyline
{
    private const VERSION = 1;

    private const ENCODING_TABLE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    /**
     * Décode une polyligne HERE.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function decode(string $encoded): array
    {
        if ($encoded === '') {
            return [];
        }

        $decoder = self::decodeUnsignedValues($encoded);
        $header = self::decodeHeader($decoder);

        $factorDegree = 10 ** $header['precision'];
        $factorZ = 10 ** $header['thirdDimPrecision'];
        $hasThirdDim = $header['thirdDim'] !== 0;

        $lastLat = 0;
        $lastLng = 0;
        $points = [];

        $values = $decoder['values'];
        $count = count($values);
        $i = $decoder['index'];

        // Chaque point consomme 2 valeurs (3 si troisième dimension)
        $stride = $hasThirdDim ? 3 : 2;

        while ($i + $stride <= $count) {
            $lastLat += self::toSigned($values[$i]);
            $lastLng += self::toSigned($values[$i + 1]);

            $points[] = [
                round($lastLat / $factorDegree, 7),
                round($lastLng / $factorDegree, 7),
            ];

            $i += $stride;
        }

        return $points;
    }

    /**
     * Encode une liste de points [lat, lng].
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    public static function encode(array $points, int $precision = 7): string
    {
        if ($points === []) {
            return '';
        }

        if ($precision < 0 || $precision > 15) {
            throw new \InvalidArgumentException('flexible polyline: précision hors bornes (0-15).');
        }

        $result = '';

        // En-tête : version, puis (precision | thirdDim << 4 | thirdDimPrecision << 7)
        $result .= self::encodeUnsigned(self::VERSION);
        $result .= self::encodeUnsigned($precision);

        $factor = 10 ** $precision;
        $lastLat = 0;
        $lastLng = 0;

        foreach ($points as $point) {
            $lat = (int) round($point[0] * $factor);
            $lng = (int) round($point[1] * $factor);

            $result .= self::encodeSigned($lat - $lastLat);
            $result .= self::encodeSigned($lng - $lastLng);

            $lastLat = $lat;
            $lastLng = $lng;
        }

        return $result;
    }

    /**
     * Décode toutes les valeurs non signées de la chaîne.
     *
     * @return array{values: array<int, int>, index: int}
     */
    private static function decodeUnsignedValues(string $encoded): array
    {
        $decodingTable = self::decodingTable();

        $values = [];
        $result = 0;
        $shift = 0;

        for ($i = 0, $len = strlen($encoded); $i < $len; $i++) {
            $char = $encoded[$i];

            if (!isset($decodingTable[$char])) {
                throw new \InvalidArgumentException("flexible polyline: caractère invalide « {$char} ».");
            }

            $value = $decodingTable[$char];

            $result |= ($value & 0x1F) << $shift;

            if (($value & 0x20) === 0) {
                $values[] = $result;
                $result = 0;
                $shift = 0;
            } else {
                $shift += 5;
            }
        }

        if ($shift > 0) {
            throw new \InvalidArgumentException('flexible polyline: chaîne tronquée.');
        }

        return ['values' => $values, 'index' => 0];
    }

    /**
     * Lit l'en-tête et avance l'index du décodeur.
     *
     * @param  array{values: array<int, int>, index: int}  $decoder
     * @return array{precision: int, thirdDim: int, thirdDimPrecision: int}
     */
    private static function decodeHeader(array &$decoder): array
    {
        if (count($decoder['values']) < 2) {
            throw new \InvalidArgumentException('flexible polyline: en-tête absent.');
        }

        $version = $decoder['values'][0];

        if ($version !== self::VERSION) {
            throw new \InvalidArgumentException("flexible polyline: version {$version} non supportée.");
        }

        $header = $decoder['values'][1];
        $decoder['index'] = 2;

        return [
            'precision'         => $header & 15,
            'thirdDim'          => ($header >> 4) & 7,
            'thirdDimPrecision' => ($header >> 7) & 15,
        ];
    }

    /**
     * Zigzag → entier signé.
     */
    private static function toSigned(int $value): int
    {
        // Bit de poids faible = signe
        if ($value & 1) {
            $value = ~$value;
        }

        return $value >> 1;
    }

    private static function encodeSigned(int $value): string
    {
        // Zigzag : le signe passe dans le bit de poids faible
        $unsigned = $value < 0 ? ~($value << 1) : ($value << 1);

        return self::encodeUnsigned($unsigned);
    }

    private static function encodeUnsigned(int $value): string
    {
        $result = '';

        while ($value > 0x1F) {
            $result .= self::ENCODING_TABLE[($value & 0x1F) | 0x20];
            $value >>= 5;
        }

        return $result . self::ENCODING_TABLE[$value];
    }

    /**
     * @return array<string, int>
     */
    private static function decodingTable(): array
    {
        static $table = null;

        if ($table === null) {
            $table = array_flip(str_split(self::ENCODING_TABLE));
        }

        return $table;
    }
}
