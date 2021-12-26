<?php

namespace app\admin\behavior;

/**
 * 系统行为扩展：
 */
class ModuleInitBehavior {
    protected static $actionName;
    protected static $controllerName;
    protected static $moduleName;
    protected static $method;

    /**
     * 构造方法
     * @param Request $request Request对象
     * @access public
     */
    public function __construct()
    {

    }

    // 行为扩展的执行入口必须是run
    public function run(&$params){
        self::$actionName = request()->action();
        self::$controllerName = request()->controller();
        self::$moduleName = request()->module();
        self::$method = request()->method();
        $this->_initialize();
    }

    private function _initialize() {
        // $this->setChanneltypeStatus();
    }

    /**
     * 根据前端模板自动开启系统模型
     */
    private function setChanneltypeStatus()
    {
        /*不在以下相应的控制器和操作名里不往下执行，以便提高性能*/
        $ctlActArr = array(
            'Index@index',
            'System@clear_cache',
        );
        $ctlActStr = self::$controllerName.'@'.self::$actionName;
        if (!in_array($ctlActStr, $ctlActArr) || 'GET' != self::$method) {
            return true;
        }
        /*--end*/
        
        // 根据前端模板自动开启系统模型
        model('Channeltype')->setChanneltypeStatus();
    }
}
