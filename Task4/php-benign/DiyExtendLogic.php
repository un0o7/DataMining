<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\admin\logic;

use think\Model;
use think\Db;
use think\Request;

/**
 * 逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
class DiyExtendLogic extends Model
{
    private $request = null; // 当前Request对象实例
    private $admin_lang = 'cn'; // 后台多语言标识

    /**
     * 析构函数
     */
    function  __construct() {
        null === $this->request && $this->request = Request::instance();
        $this->admin_lang = get_admin_lang();
    }

    /**
     * 获取当前页面所在的模型ID
     * @param string $id 模型ID
     */
    public function getChannelid()
    {
        $channel = input('param.channel/d', 0);
        if (!empty($channel)) {
            return $channel;
        }

        $controllerName = $this->request->controller();
        if ('Custom' != $controllerName) {
            $channel = Db::name('channeltype')->where([
                    'ctl_name'  => $controllerName,
                ])->getField('id');
        }

        return $channel;
    }
}
