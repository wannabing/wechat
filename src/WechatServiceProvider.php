<?php


namespace Wannabing\Wechat;


use Illuminate\Support\ServiceProvider;

class WechatServiceProvider extends ServiceProvider
{
    public function boot(){
        $this->registerPublishing();
    }

    public function register()
    {
        $this->app->singleton('wechat',function(){
            return new Wechat;
        });
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config' => config_path()], 'wechat-config');
        }
    }
}
