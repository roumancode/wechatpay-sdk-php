<?php

declare(strict_types=1);

namespace WeChatPay;

/**
 * RSA工具类，提供RSA密钥格式转换功能
 */
class RsaTool
{
    /**
     * @var string - Equal to `sequence(oid(1.2.840.113549.1.1.1), null))`
     * @link https://datatracker.ietf.org/doc/html/rfc3447#appendix-A.2
     */
    private const ASN1_OID_RSAENCRYPTION = '300d06092a864886f70d0101010500';
    private const ASN1_SEQUENCE = 48;
    private const CHR_NUL = "\0";
    private const CHR_ETX = "\3";

    /**
     * Translate the $thing strlen from `X690` style to the `ASN.1` 128bit hexadecimal length string
     *
     * @param string $thing - The string
     *
     * @return string The `ASN.1` 128bit hexadecimal length string
     */
    private static function encodeLength(string $thing): string
    {
        $num = strlen($thing);
        if ($num <= 0x7F) {
            return sprintf('%c', $num);
        }

        $tmp = ltrim(pack('N', $num), self::CHR_NUL);
        return pack('Ca*', strlen($tmp) | 0x80, $tmp);
    }

    /**
     * Convert the `PKCS#1` format RSA Public Key to `SPKI` format
     *
     * @param string $thing - The base64-encoded string, without envelope style
     *
     * @return string The `SPKI` style public key without envelope string
     */
    public static function pkcs1ToSpki(string $thing): string
    {
        $raw = self::CHR_NUL . base64_decode($thing, true);
        if ($raw === false) {
            throw new \InvalidArgumentException('Invalid base64 string provided');
        }
        
        $new = pack('H*', self::ASN1_OID_RSAENCRYPTION) . self::CHR_ETX . self::encodeLength($raw) . $raw;

        return base64_encode(pack('Ca*a*', self::ASN1_SEQUENCE, self::encodeLength($new), $new));
    }

    /**
     * 将PEM格式证书转换为Base64字符串
     *
     * @param string $data PEM格式证书内容
     * @return string Base64编码的证书内容
     */
    public static function pemToBase64(string $data): string
    {
        $lines = explode("\n", $data);
        $base64 = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '-----BEGIN') || str_starts_with($line, '-----END')) {
                continue;
            }
            $base64 .= $line;
        }
        
        return $base64;
    }
    
    /**
     * 将Base64字符串转换为PEM格式证书
     *
     * @param string $data Base64编码的证书内容
     * @param string $type 证书类型（如 PUBLIC KEY, PRIVATE KEY）
     * @return string PEM格式证书
     */
    public static function base64ToPem(string $data, string $type): string
    {
        if ($data === '' || str_contains($data, '-----BEGIN')) {
            return $data;
        }
        
        $pem = "-----BEGIN {$type}-----\n" .
            wordwrap($data, 64, "\n", true) .
            "\n-----END {$type}-----";
        
        return $pem;
    }

    /**
     * 将PKCS#1格式PEM公钥转换为SPKI格式PEM公钥
     *
     * @param string $thing PKCS#1格式PEM公钥
     * @return string SPKI格式PEM公钥
     */
    public static function pkcs1ToSpkiPem(string $thing): string
    {
        $raw = self::pemToBase64($thing);
        $new = self::pkcs1ToSpki($raw);
        
        return self::base64ToPem($new, 'PUBLIC KEY');
    }
}