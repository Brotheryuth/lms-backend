<?php

class JWT {
    private static $secret = "lms_super_secret_key_2025";
    private static $algo   = "SHA256";

    public static function encode(array $payload): string {
        $header  = self::base64url(json_encode(["alg" => "HS256", "typ" => "JWT"]));
        $payload = self::base64url(json_encode($payload));
        $sig     = self::base64url(hash_hmac(self::$algo, "$header.$payload", self::$secret, true));
        return "$header.$payload.$sig";
    }

    public static function decode(string $token): ?array {
        $parts = explode(".", $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $sig] = $parts;
        $expected = self::base64url(hash_hmac(self::$algo, "$header.$payload", self::$secret, true));

        if (!hash_equals($expected, $sig)) return null;

        $data = json_decode(self::base64url_decode($payload), true);

        if (isset($data["exp"]) && $data["exp"] < time()) return null;

        return $data;
    }

    private static function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    private static function base64url_decode(string $data): string {
        return base64_decode(strtr($data, "-_", "+/"));
    }
}