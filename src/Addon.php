<?php
declare (strict_types = 1);

namespace think\addon;

use Closure;
use think\App;
use think\Request;
use think\Response;

use think\facade\Event;
use think\facade\Cache;
use think\facade\Route;
// use app\common\model\Addon as AddonModel;

use ReflectionException;
use ReflectionMethod;
use think\exception\HttpException;
use think\exception\FuncNotFoundException;

/**
 * 扩展支持
 */
class addon
{
    /** @var App */
    protected $app;

    /**
     * 扩展名称
     * @var string
     */
    protected $name;

    /**
     * 扩展路径
     * @var string
     */
    protected $path;

    public function __construct(App $app)
    {
        $this->app  = $app;
        $this->name = $this->app->http->getName();
        $this->path = $this->app->http->getPath();
    }

    /**
     * 解析扩展
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        if (defined('IS_INSTALL') && IS_INSTALL === 'install') {
            return $next($request);
        }

        // 加载扩展全局事件
        // $this->loadEvent();

        // 解析扩展路由
        if (!$this->parseAddon()) {
            return $next($request);
        }

        return $this->app->middleware->pipeline('addon')
            ->send($request)
            ->then(function ($request) use ($next) {
                return $next($request);
            });
    }

    /**
     * 解析扩展
     * 
     * @return mixed
     */
    protected function parseAddon()
    {
        // 这里后期应该查询是否存在应用, 如果存在就跳过
        $sysList = ['install', 'admin', 'adm', 'index', 'common', 'store', 'user', 'api', 'article', 'pay', 'public', 'app'];
        // 检测当前pathinfo
        $pathinfo = $this->app->request->pathinfo();
        $pathinfoArr = explode('/', $pathinfo);
        $url = $this->app->request->domain();
        $checkModel = $pathinfoArr[0];
        // 检测是否系统应用或扩展
        $addon = in_array($checkModel, $sysList) ? '' : $checkModel;

        if (empty($addon)) {
            return false;
        }

        $appName     = empty($pathinfoArr[1]) ? 'index' : $pathinfoArr[1];
        $controller = empty($pathinfoArr[2]) ? 'index' : $pathinfoArr[2];
        $method     = empty($pathinfoArr[3]) ? 'index' : $pathinfoArr[3];

        $this->app->request->addon($addon);

        $addonPath = $this->app->getRootPath() . 'addon' . DS . $addon . DS;
        $appPath = $addonPath . $appName . DS;

        // dd($appPath);
        if (!is_dir($addonPath)) {
            return false;
        }
        // 优先查找控制器, 不存在则在默认控制Index查找方法
        // 指定app
        if(is_dir($appPath)) {
            // 检测控制器文件，设置控制器
            if(is_file($appPath . 'controller' . DS . ucfirst($controller) . EXT)) {
                $method = isset($pathinfoArr[3]) ? $pathinfoArr[3] : 'index';
            } else {
                if(isset($pathinfoArr[3])) {
                    $controller = $pathinfoArr[2];
                } else {
                    $controller = 'index';
                    $method = $pathinfoArr[2];
                }
            }
        } else {
            // 默认appName
            $appName = 'index';
            $appPath = $addonPath . $appName . DS;
            // 默认appName指定控制器
            if(count($pathinfoArr) > 2) {
                $controller = isset($pathinfoArr[1]) ? $pathinfoArr[1] : 'index';
                $method = isset($pathinfoArr[2]) ? $pathinfoArr[2] : 'index';
            } else {
                // index/index/method
                $method = array_pop($pathinfoArr);
                // 尝试找下控制器, 如果存在则使用控制器的默认方法
                if(is_file($appPath . 'controller' . DS . ucfirst($method) . EXT)) {
                    // index/method/index
                    $controller = $method;
                    $method = 'index';
                    // dd($controller);
                }
            }

            $appPath = $addonPath . $appName . DS;
        }

        // dd($addonPath);
        // dd($appName . '/' . $controller . '/' . $method);
        // dd($appPath . 'controller' . DS . ucfirst($controller) . EXT);

        // 判断扩展控制器 or 方法 是否存在
        if(!is_file($appPath . 'controller' . DS . ucfirst($controller) . EXT)) {
            throw new HttpException(404, 'Addon '. ucfirst($addon) .' controller not exists:' . ucfirst($controller));
        } else {
            $class = '\\addon\\' . $addon . '\\' . $appName . '\\controller\\' . ucfirst($controller);
            try {
                $reflect = new ReflectionMethod($class, $method);
            } catch (ReflectionException $e) {
                $class = is_object($class) ? get_class($class) : $class;
                throw new FuncNotFoundException('Addon '. ucfirst($addon) .' controller method not exists: ' . $class . '::' . $method . '()', "{$class}::{$method}", $e);
            }
        }

        // 设置扩展命名空间
        $this->app->setNamespace('addon\\' . $addon . '\\' . $appName);
        // 把应用目录设置为扩展模块目录
        $this->app->setAppPath($addonPath . $appName . DS);
        // 设置运行存储目录
        $this->app->setRuntimePath($this->app->getRuntimePath() . 'addon' . DS . $addon . DS);
        // 设置路由规则目录[特殊路由规则]
        // $this->app->http->setRoutePath($addonPath . 'route' . DS);
        // 加载扩展文件
        $this->loadAddon($addon, $addonPath);

        // 设置当前访问继承应用, 需要扩展app/Request的方法
        $this->app->request->appName($appName);
        
        // dd([$pathinfo, $appName . '/' . $controller . '/' . $method]);
        Route::rule($pathinfo, $appName . '/' . $controller . '/' . $method);
        return true;
    }

    /**
     * 加载扩展文件
     * 
     * @param string $addonName 扩展名
     * @return void
     */
    protected function loadAddon(string $addonName, string $addonPath): void
    {
        if (is_file($addonPath . 'common.php')) {
            include_once $addonPath . 'common.php';
        }

        $files = [];
        $files = array_merge($files, glob($addonPath . 'config' . DS . '*' . $this->app->getConfigExt()));

        foreach ($files as $file) {
            $this->app->config->load($file, pathinfo($file, PATHINFO_FILENAME));
        }

        if (is_file($addonPath . 'event' . EXT)) {
            $this->app->loadEvent(include $addonPath . 'event' . EXT);
        }

        if (is_file($addonPath . 'middleware' . EXT)) {
            $this->app->middleware->import(include $addonPath . 'middleware' . EXT, 'addon');
        }

        if (is_file($addonPath . 'provider' . EXT)) {
            $this->app->bind(include $addonPath . 'provider' . EXT);
        }
        // 加载扩展默认语言包
        // $this->app->loadLangPack($this->app->lang->defaultLangSet());
    }

    /**
     * 加载扩展全局事件
     */
    protected function loadEvent()
    {
        $eventList = Cache::get('addon_global_event_list');

        if (empty($eventList)) {
            $addonModel = new AddonModel();
            $addonList  = $addonModel->getAddonList([], 'name');

            $eventList = [];
            foreach ($addonList as $v) {
                $filePath = root_path('addon') . $v['name'] . DS . 'event' . EXT;
                if (!is_file($filePath)) {
                    continue;
                }

                $addonEvent = require_once $filePath;
                // 载入全局事件
                $listen = isset($addonEvent['global']) ? $addonEvent['global'] : [];

                if (empty($listen)) {
                    continue;
                }

                $eventList[] = $listen;
            }

            Cache::tag('addon')->set('addon_global_event_list', $eventList);
        }

        if (!empty($eventList)) {
            foreach ($eventList as $k => $listen) {
                if (empty($listen)) {
                    continue;
                }
                Event::listenEvents($listen);
            }
        }
    }
}