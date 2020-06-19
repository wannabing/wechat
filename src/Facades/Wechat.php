<?php


namespace Wannabing\Wechat\Facades;


use Illuminate\Support\Facades\Facade;

class Wechat extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Wannabing\Wechat\Wechat::class;
    }
}