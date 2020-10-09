<?php
namespace isszz\addon;

use think\Service as BaseService;

class Service extends BaseService
{
    public function boot()
    {
        $this->app->event->listen('HttpRun', function () {
            $this->app->middleware->add(Addon::class);
        });

        $this->commands([
            'addon' => command\Addon::class,
            'unaddon' => command\Unaddon::class,
        ]);

        /*
        $this->app->bind([
            'think\route\Url' => Url::class,
        ]);*/
    }
}
