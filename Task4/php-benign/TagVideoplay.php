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
 * 视频播放
 */
class TagVideoplay extends Base
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
    public function getVideoplay($aid = '', $autoplay = '')
    {
        $aid = !empty($aid) ? $aid : $this->aid;
        if (empty($aid)) {
            echo '标签videoplay报错：缺少属性 aid 值。';
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
            // 获取文档数据
            $archives  = Db::name('archives')->where(['aid' => $aid])->field('users_price, arc_level_id')->find();
            $UsersData = session('users');
            $UsersID   = $UsersData['users_id'];

            $MediaOrder = [];
            if (!empty($UsersID)) {
                $where = [
                    'order_status' => 1,
                    'users_id' => $UsersID
                ];
                $field = 'order_id, users_id, product_id';
                $MediaOrder = Db::name('media_order')->field($field)->where($where)->getAllWithIndex('product_id');
            }

            if (0 < intval($archives['arc_level_id']) && !empty($UsersData)) {
                // 查询会员信息
                $users = Db::name('users')
                    ->alias('a')
                    ->field('a.users_id,b.level_value,b.level_name')
                    ->join('__USERS_LEVEL__ b', 'a.level = b.level_id', 'LEFT')
                    ->where(['a.users_id'=>$UsersID])
                    ->find();
                // 查询播放所需等级值
                $file_level = Db::name('archives')
                    ->alias('a')
                    ->field('b.level_value,b.level_name')
                    ->join('__USERS_LEVEL__ b', 'a.arc_level_id = b.level_id', 'LEFT')
                    ->where(['a.aid'=>$aid])
                    ->find();
            }

            // 处理视频数据
            $result = [];
            foreach ($row as $key => $val) {
                if (!empty($val['file_url'])) {
                    if (!is_http_url($val['file_url'])) {
                        $val['file_url'] = handle_subdir_pic($val['file_url'], 'media', true);
                    }

                    if (empty($val['gratis']) && 0 == $val['gratis']) {
                        if (empty($MediaOrder[$aid])) {
                            if (0 < $archives['users_price']) {
                                $val['file_url'] = '';
                            }

                            if (0 < intval($archives['arc_level_id'])) {
                                if (empty($UsersID)) {
                                    $val['file_url'] = '';
                                } else {
                                    if ($users['level_value'] < $file_level['level_value']) {
                                        $val['file_url'] = '';
                                    }
                                }
                            }
                        }
                    }
                    
                    $result = $val;
                    break;
                }
            }

            if (!empty($result)) {
                $from_id = "video_play_20200520_{$aid}";
                $result['id'] = " id='{$from_id}' ";
                if ('on' == $autoplay) {
                    $autoplay_str = "document.getElementById('{$from_id}').autoplay = 'autoplay';";
                } else {
                    $autoplay_str = '';
                }

                $result['hidden'] = <<<EOF
<script type="text/javascript">
    if ('video' == document.getElementById('{$from_id}').tagName.toLowerCase()) {
        if (!document.getElementById('{$from_id}').controls) {
            document.getElementById('{$from_id}').controls = 'controls';
        }
        {$autoplay_str}
    }
</script>
EOF;
            }

            return $result;
        }

    }
}