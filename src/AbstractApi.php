<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/11/10 21:10
 * Email: sgenmi@gmail.com
 */

namespace Weida\PindoudouCore;

use Weida\PindoudouCore\Contract\ApiInterface;

class AbstractApi implements ApiInterface
{
    /**
     * @var string api地址
     */
    protected string $_url = '';

    public function getUrl(): string
    {
        return $this->_url;
    }

    public function getParams(): array
    {
        $vars = get_object_vars($this);
        unset($vars['_url']);
        return $vars;
    }
}