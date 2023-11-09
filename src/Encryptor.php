<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/11/9 22:22
 * Email: sgenmi@gmail.com
 */

namespace Weida\PindoudouCore;

class Encryptor
{
    /**
     * @param array $params
     * @param string $secret
     * @return string
     * @author Weida
     */
    public static function Sign(array $params,string $secret):string{
        unset($params["sign"]);
        ksort($params);
        $sign = $secret;
        foreach ($params as $k => $v) {
            if (!str_starts_with($v, "@")) {
                $sign .= sprintf("%s%s",$k,$v);
            }
        }
        $sign .= $secret;
        return strtoupper(md5($sign));
    }

}