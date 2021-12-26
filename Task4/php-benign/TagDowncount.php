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

namespace think\template\taglib\eyou;

/**
 * 在内容页模板显示下载次数
 */
class TagDowncount extends Base
{
    public $aid = 0;

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->aid = input('param.aid/d', 0);
    }

    /**
     * 在内容页模板显示下载次数
     * @author wengxianhu by 2018-4-20
     */
    public function getDowncount($aid = '')
    {
        $aid = !empty($aid) ? intval($aid) : $this->aid;

        if (empty($aid)) {
            return '标签downcount报错：缺少属性 aid 值。';
        }

        static $downcount_js = null;
        if (null === $downcount_js) {
            $downcount_js = <<<EOF
<script type="text/javascript">
    function tag_downcount(aid)
    {
        //步骤一:创建异步对象
        var ajax = new XMLHttpRequest();
        //步骤二:设置请求的url参数,参数一是请求的类型,参数二是请求的url,可以带参数,动态的传递参数starName到服务端
        ajax.open("post", "{$this->root_dir}/index.php?m=api&c=Ajax&a=downcount&aid="+aid, true);
        // 给头部添加ajax信息
        ajax.setRequestHeader("X-Requested-With","XMLHttpRequest");
        // 如果需要像 HTML 表单那样 POST 数据，请使用 setRequestHeader() 来添加 HTTP 头。然后在 send() 方法中规定您希望发送的数据：
        ajax.setRequestHeader("Content-type","application/x-www-form-urlencoded");
        //步骤三:发送请求
        ajax.send('_ajax=1');
        //步骤四:注册事件 onreadystatechange 状态改变就会调用
        ajax.onreadystatechange = function () {
            //步骤五 如果能够进到这个判断 说明 数据 完美的回来了,并且请求的页面是存在的
            if (ajax.readyState==4 && ajax.status==200) {
        　　　　document.getElementById("eyou_downcount_"+aid).innerHTML = ajax.responseText;
          　}
        } 
    }
</script>
EOF;
        } else {
            $downcount_js = '';
        }

        $parseStr = <<<EOF
<i id="eyou_downcount_{$aid}" class="eyou_downcount" style="font-style:normal"></i> 
<script type="text/javascript">tag_downcount({$aid});</script>
EOF;

        $parseStr = $downcount_js . $parseStr;

        return $parseStr;
    }
}