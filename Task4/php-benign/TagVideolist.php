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

use think\Db;
use think\Request;

/**
 * 视频列表
 */
class TagVideolist extends Base
{
    public $aid = '';

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        /*应用于文档列表*/
        $this->aid = input('param.aid/d', 0);
        /*--end*/
    }

    /**
     * 获取每篇文章的属性
     * @author wengxianhu by 2018-4-20
     */
    public function getVideolist($aid = '', $autoplay = '')
    {
        $aid = !empty($aid) ? $aid : $this->aid;
        if (empty($aid)) {
            echo '标签videolist报错：缺少属性 aid 值。';
            return false;
        }

        //当前文章的视频列表
        $row = Db::name('media_file')
            ->where(['aid' => $aid])
            ->order('sort_order asc, file_id asc')
            ->cache(true,EYOUCMS_CACHE_TIME,"media_file")
            ->select();
        /*--end*/

        if (empty($row)) {
            return false;
        } else {
            $result = [];
            foreach ($row as $key => $val) {
                if (!empty($val['file_url'])) {
                    if (!is_http_url($val['file_url'])) {
                        $row[$key]['file_url'] = handle_subdir_pic($val['file_url'], 'media', true);
                    }
                    
                    $row[$key]['file_time']  = gmSecondFormat(intval($val['file_time']), ':');
                    if (!empty($val['file_time'])) {
                        $row[$key]['onclick'] = " onclick=\"changeVideoUrl1586341922('{$row[$key]['file_id']}', '{$row[$key]['aid']}', '{$row[$key]['uhash']}')\" ";
                        $row[$key]['hidden'] = '';
                        if ($key == count($row) - 1) {
                            $row[$key]['hidden'] = <<<EOF
<script type="text/javascript">
    function changeVideoUrl1586341922(id, aid, uhash) {
        //步骤一:创建异步对象
        var ajax = new XMLHttpRequest();
        //步骤二:设置请求的url参数,参数一是请求的类型,参数二是请求的url,可以带参数,动态的传递参数starName到服务端
        ajax.open("post", "{$this->root_dir}/index.php?m=home&c=View&a=pay_video_url", true);
        // 给头部添加ajax信息
        ajax.setRequestHeader("X-Requested-With","XMLHttpRequest");
        // 如果需要像 HTML 表单那样 POST 数据，请使用 setRequestHeader() 来添加 HTTP 头。然后在 send() 方法中规定您希望发送的数据：
        ajax.setRequestHeader("Content-type","application/x-www-form-urlencoded");
        //步骤三:发送请求+数据
        ajax.send('_ajax=1&id='+id+'&aid='+aid+'&uhash='+uhash);
        //步骤四:注册事件 onreadystatechange 状态改变就会调用
        ajax.onreadystatechange = function () {
            //步骤五 如果能够进到这个判断 说明 数据 完美的回来了,并且请求的页面是存在的
            if (ajax.readyState==4 && ajax.status==200) {
                var json = ajax.responseText;  
                var res = JSON.parse(json);
                if (res.code == 1) {
                    let obj = document.getElementById('video_play_20200520_{$aid}');
                    if (obj) {
                        if (document.getElementById("VideoDiv13579")) {
                            document.getElementById("VideoDiv13579").setAttribute("style", "display: none");
                        }
                        obj.src = res.url;
                        if ('video' == obj.tagName.toLowerCase()) {
                            obj.controls = 'controls';
                            var autoplay = "{$autoplay}";
                            if ('on' == autoplay) {
                                document.getElementById('video_play_20200520_{$aid}').play();
                            } else if ('off' == autoplay) {
                                document.getElementById('video_play_20200520_{$aid}').autoplay = false;
                            } else {
                                document.getElementById('video_play_20200520_{$aid}').play();
                            }
                        }
                    } else {
                        alert('请查看模板里videoplay视频播放标签是否完整！');
                    }
                } else {
                    if (document.getElementById("VideoDiv13579")) {
                        document.getElementById("VideoDiv13579").setAttribute("style", "display: block");
                        document.getElementById('video_play_20200520_{$aid}').pause();
                    }
                    // alert(res.msg);
                }
          　}
        }
    }
</script>
EOF;
                        }
                    }
                    $result[] = $row[$key];
                } else {
                    unset($row[$key]);
                }
            }
            return $result;
        }
    }
}