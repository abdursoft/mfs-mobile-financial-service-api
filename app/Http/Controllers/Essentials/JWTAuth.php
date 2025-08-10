<?php

namespace App\Http\Controllers\Essentials;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTAuth
{
    /**
     * Authentication token
     */
    public static function createToken($aud, $hour = 1, $id = null, $phone = null)
    {
        $payload = [
            'iss' => $_SERVER['SERVER_NAME'],
            'role' => $aud,
            'iat' => time(),
            'nbf' => time() + 10,
            'exp' => time() + ($hour * 3600),
            'id'  => $id,
            'phone' => $phone
        ];
        return JWT::encode($payload, env('JWT_SECRET'), 'HS256');
    }

    /**
     * Payment token
     */
    public static function paymentToken($aud, $hour = 1, $id = null)
    {
        $payload = [
            'iss' => $_SERVER['SERVER_NAME'],
            'role' => $aud,
            'iat' => time(),
            'nbf' => time() + 10,
            'exp' => time() + ($hour * 3600),
            'id'  => $id,
        ];
        return JWT::encode($payload, env('JWT_SECRET'), 'HS256');
    }


    /**
     * Wallet session
     */
    public static function walletSession($aud, $hour = 1, $id = null, $phone = null)
    {
        $payload = [
            'iss' => $_SERVER['SERVER_NAME'],
            'role' => $aud,
            'iat' => time(),
            'nbf' => time() + 10,
            'exp' => time() + ($hour * 3600),
            'id'  => $id,
            'phone' => $phone,
        ];
        return JWT::encode($payload, env('JWT_SECRET'), 'HS256');
    }

    /**
     * JWT token sanitizer
     * @param token Bearer token extractor
     * will return token without Bearer
     */
    public static function tokenSanitizer( $token ) {
        $token = trim( str_replace( 'Bearer', '', $token ) );
        return trim($token,' ');
    }

    public static function decodeToken($token)
    {
        JWT::$leeway = 50;
        try {
            $data = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
            return $data;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public static function verifyToken($token = "token", $active = true)
    {
        if ($active) {
            $headers =  getallheaders();
            try {
                $xTOKEN  = $_COOKIE[$token] ?? $headers[$token];
                $data = self::decodeToken(self::tokenSanitizer($xTOKEN));
                return $data;
            } catch (\Throwable $th) {
                return false;
            }
        } else {
            try {
                $data = self::decodeToken(self::tokenSanitizer($token));
                return $data;
            } catch (\Throwable $th) {
                return false;
            }
        }
    }

    public static function getToken($key, $token = "token", $active = true)
    {
        if ($active) {
            $headers =  getallheaders();
            try {
                $xTOKEN  = $_COOKIE[$token] ?? $headers[$token];
                $data = self::decodeToken(self::tokenSanitizer($xTOKEN));
                return $data->$key;
            } catch (\Throwable $th) {
                return false;
            }
        } else {
            try {
                $data = self::decodeToken(self::tokenSanitizer($token));
                return $data->$key;
            } catch (\Throwable $th) {
                return false;
            }
        }
    }


    /**
     * Sanitize auth data
     * @param data set of the array or object data
     * @param keys set of the keys want to remove
     */
    public static function authSanitize( array | object $data, array $keys ) {
        if ( is_array( $data ) && is_array( $keys ) ) {
            for ( $i = 0; $i < count( $keys ); $i++ ) {
                if ( key_exists( $keys[$i], $data ) ) {
                    unset( $data[$keys[$i]] );
                }
            }
        } elseif ( is_object( $data ) ) {
            $data = (array) $data;
            for ( $i = 0; $i < count( $keys ); $i++ ) {
                if ( key_exists( $keys[$i], $data ) ) {
                    unset( $data[$keys[$i]] );
                }
            }
        }
        return $data;
    }
}
