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
            'build' => command\Build::class,
            'clear' => command\Clear::class,
        ]);

        /*
        $this->app->bind([
            'think\route\Url' => Url::class,
        ]);*/
    }
}
