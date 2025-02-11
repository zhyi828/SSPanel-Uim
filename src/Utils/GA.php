<?php

declare(strict_types=1);

namespace App\Utils;

final class GA
{
    private $codeLength = 6;

    /**
     * Create new secret.
     * 16 characters, randomly chosen from the allowed base32 characters.
     */
    public function createSecret(int $secretLength = 16): string
    {
        $validChars = $this->_getBase32LookupTable();
        unset($validChars[32]);

        $secret = '';
        for ($i = 0; $i < $secretLength; $i++) {
            $secret .= $validChars[array_rand($validChars)];
        }
        return $secret;
    }

    /**
     * Calculate the code, with given secret and point in time
     */
    public function getCode(string $secret, ?int $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = floor(\time() / 30);
        }

        $secretkey = $this->_base32Decode($secret);

        // Pack time into binary string
        $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);
        // Hash it with users secret key
        $hm = hash_hmac('SHA1', $time, $secretkey, true);
        // Use last nipple of result as index/offset
        $offset = ord(substr($hm, -1)) & 0x0F;
        // grab 4 bytes of the result
        $hashpart = substr($hm, $offset, 4);

        // Unpak binary value
        $value = unpack('N', $hashpart);
        $value = $value[1];
        // Only 32 bits
        $value &= 0x7FFFFFFF;

        $modulo = 10 ** $this->codeLength;
        return str_pad($value % $modulo, $this->codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Get QR-Code URL for image, from google charts
     */
    public function getQRCodeGoogleUrl(string $name, string $secret, ?string $title = null): string
    {
        $urlencoded = urlencode('otpauth://totp/' . $name . '?secret=' . $secret . '');
        if (isset($title)) {
            $urlencoded .= urlencode('&issuer=' . urlencode($title));
        }
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . $urlencoded . '';
    }

    public function getUrl($name, $secret, $title = null)
    {
        $urlencoded = 'otpauth://totp/' . $name . '?secret=' . $secret . '';
        if (isset($title)) {
            $urlencoded .= '&issuer=' . urlencode($title);
        }
        return $urlencoded;
    }

    public function verifyCode(string $secret, string $code, int $discrepancy = 1, ?int $currentTimeSlice = null): bool
    {
        if ($currentTimeSlice === null) {
            $currentTimeSlice = floor(\time() / 30);
        }

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode === $code) {
                return true;
            }
        }

        return false;
    }

    public function setCodeLength(int $length)
    {
        $this->codeLength = $length;
        return $this;
    }

    private function _base32Decode($secret)
    {
        if ($secret === '') {
            return '';
        }

        $base32chars = $this->_getBase32LookupTable();
        $base32charsFlipped = array_flip($base32chars);

        $paddingCharCount = substr_count($secret, $base32chars[32]);
        $allowedValues = [6, 4, 3, 1, 0];
        if (! \in_array($paddingCharCount, $allowedValues)) {
            return false;
        }
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount === $allowedValues[$i] &&
                substr($secret, -$allowedValues[$i]) !== str_repeat($base32chars[32], $allowedValues[$i])) {
                return false;
            }
        }
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        for ($i = 0, $iMax = count($secret); $i < $iMax; $i += 8) {
            $x = '';
            if (! \in_array($secret[$i], $base32chars)) {
                return false;
            }
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert($base32charsFlipped[$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            foreach ($eightBits as $zValue) {
                $binaryString .= (($y = chr(base_convert($zValue, 2, 10))) || ord($y) === 48) ? $y : '';
            }
        }
        return $binaryString;
    }

    private function _base32Encode(string $secret, bool $padding = true): string
    {
        if ($secret !== '') {
            return '';
        }

        $base32chars = $this->_getBase32LookupTable();

        $secret = str_split($secret);
        $binaryString = '';
        foreach ($secret as $iValue) {
            $binaryString .= str_pad(base_convert(ord($iValue), 10, 2), 8, '0', STR_PAD_LEFT);
        }
        $fiveBitBinaryArray = str_split($binaryString, 5);
        $base32 = '';
        $i = 0;
        while ($i < count($fiveBitBinaryArray)) {
            $base32 .= $base32chars[base_convert(str_pad($fiveBitBinaryArray[$i], 5, '0'), 2, 10)];
            $i++;
        }
        $x = strlen($binaryString) % 40;

        if ($padding && $x !== 0) {
            if ($x === 8) {
                $base32 .= str_repeat($base32chars[32], 6);
            } elseif ($x === 16) {
                $base32 .= str_repeat($base32chars[32], 4);
            } elseif ($x === 24) {
                $base32 .= str_repeat($base32chars[32], 3);
            } elseif ($x === 32) {
                $base32 .= $base32chars[32];
            }
        }
        return $base32;
    }

    private function _getBase32LookupTable(): array
    {
        return [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
            'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
            '=',  // padding char
        ];
    }
}
