<?php
namespace Catch\Plugin\Exceptions;

class HookLoadFailedException extends \Exception
{
    protected $message = 'Hook 加载失败';
}
