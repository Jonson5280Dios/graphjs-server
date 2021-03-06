<?php
/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphJS;

use HansOtt\PSR7Cookies\SetCookie;
use HansOtt\PSR7Cookies\RequestCookies;
use HansOtt\PSR7Cookies\Signer\Key;
use HansOtt\PSR7Cookies\Signer\Hmac\Sha256;
use HansOtt\PSR7Cookies\Signer\Mismatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Session
{
    const COOKIE = "_______g_j_c";

    public static function depend(ServerRequestInterface $request): ?string 
    {
        $signer = new Sha256();
        $key = new Key(md5(getenv("SINGLE_SIGNON_TOKEN_KEY")));
        $cookies = RequestCookies::createFromRequest($request);
        if ($cookies->has(static::COOKIE)) {
            try {
                $idSigned = $cookies->get(static::COOKIE);
                $id = $signer->verify(
                    $idSigned, 
                    $key
                );
                return $id->getValue();
            } catch (Mismatch $e) {
                error_log("Cookie tampered");
            }
        }
        return null;
    }

    public static function begin(ResponseInterface &$response, string $id): void
    {
        $signer = new Sha256();
        $key = new Key(md5(getenv("SINGLE_SIGNON_TOKEN_KEY")));
        $cookie = SetCookie::thatStaysForever(static::COOKIE, $id, "/", '', true, false, 'none');
        $signedCookie = $signer->sign($cookie, $key);
        $response = $signedCookie->addToResponse($response);
    }

    public static function destroy(ResponseInterface &$response): void
    {
        $cookie = SetCookie::thatDeletesCookie(static::COOKIE, "/", '', true, false, 'none');
        $response = $cookie->addToResponse($response);
    }
}