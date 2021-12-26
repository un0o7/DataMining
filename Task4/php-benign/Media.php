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

namespace app\admin\controller;

use think\Page;
use think\Db;

class Media extends Base
{
    // 模型标识
    public $nid = 'media';
    // 模型ID
    public $channeltype = '';

    public function _initialize()
    {
        parent::_initialize();
        $channeltype_list  = config('global.channeltype_list');
        $this->channeltype = $channeltype_list[$this->nid];
        empty($this->channeltype) && $this->channeltype = 5;
        $this->assign('nid', $this->nid);
        $this->assign('channeltype', $this->channeltype);
    }

    /**
     * 列表
     */
    public function index()
    {
        $assign_data = array();
        $condition   = array();
        // 获取到所有GET参数
        $param  = input('param.');
        $flag   = input('flag/s');
        $typeid = input('typeid/d', 0);
        $begin  = strtotime(input('add_time_begin'));
        $end    = strtotime(input('add_time_end'));

        // 应用搜索条件
        foreach (['keywords', 'typeid', 'flag', 'is_release'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.title'] = array('LIKE', "%{$param[$key]}%");
                } else if ($key == 'typeid') {
                    $typeid  = $param[$key];
                    $hasRow  = model('Arctype')->getHasChildren($typeid);
                    $typeids = get_arr_column($hasRow, 'id');
                    /*权限控制 by 小虎哥*/
                    $admin_info = session('admin_info');
                    if (0 < intval($admin_info['role_id'])) {
                        $auth_role_info = $admin_info['auth_role_info'];
                        if (!empty($auth_role_info)) {
                            if (!empty($auth_role_info['permission']['arctype'])) {
                                if (!empty($typeid)) {
                                    $typeids = array_intersect($typeids, $auth_role_info['permission']['arctype']);
                                }
                            }
                        }
                    }
                    /*--end*/
                    $condition['a.typeid'] = array('IN', $typeids);
                } else if ($key == 'flag') {
                    if ('is_release' == $param[$key]) {
                        $condition['a.users_id'] = array('gt', 0);
                    } else {
                        $condition['a.' . $param[$key]] = array('eq', 1);
                    }
                    // } else if ($key == 'is_release') {
                    //     if (0 < intval($param[$key])) {
                    //         $condition['a.users_id'] = array('gt', intval($param[$key]));
                    //     }
                } else {
                    $condition['a.' . $key] = array('eq', $param[$key]);
                }
            }
        }

        /*权限控制 by 小虎哥*/
        $admin_info = session('admin_info');
        if (0 < intval($admin_info['role_id'])) {
            $auth_role_info = $admin_info['auth_role_info'];
            if (!empty($auth_role_info)) {
                if (isset($auth_role_info['only_oneself']) && 1 == $auth_role_info['only_oneself']) {
                    $condition['a.admin_id'] = $admin_info['admin_id'];
                }
            }
        }
        /*--end*/

        // 时间检索
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between', "$begin,$end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        // 模型ID
        $condition['a.channel'] = array('eq', $this->channeltype);
        // 多语言
        $condition['a.lang'] = array('eq', $this->admin_lang);
        // 回收站
        $condition['a.is_del'] = array('eq', 0);

        /*自定义排序*/
        $orderby  = input('param.orderby/s');
        $orderway = input('param.orderway/s');
        if (!empty($orderby)) {
            $orderby = "a.{$orderby} {$orderway}";
            $orderby .= ", a.aid desc";
        } else {
            $orderby = "a.aid desc";
        }
        /*end*/

        /**
         * 数据查询，搜索出主键ID的值
         */
        $count = DB::name('archives')->alias('a')->where($condition)->count('aid');// 查询满足要求的总记录数
        $Page  = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list  = DB::name('archives')
            ->field("a.aid")
            ->alias('a')
            ->where($condition)
            ->order($orderby)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->getAllWithIndex('aid');

        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        if ($list) {
            $aids   = array_keys($list);
            $fields = "b.*, a.*, a.aid as aid";
            $row    = DB::name('archives')
                ->field($fields)
                ->alias('a')
                ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                ->where('a.aid', 'in', $aids)
                ->getAllWithIndex('aid');
            foreach ($list as $key => $val) {
                $row[$val['aid']]['arcurl'] = get_arcurl($row[$val['aid']]);
                $row[$val['aid']]['litpic'] = handle_subdir_pic($row[$val['aid']]['litpic']); // 支持子目录
                $list[$key]                 = $row[$val['aid']];
            }
        }
        $show                 = $Page->show(); // 分页显示输出
        $assign_data['page']  = $show; // 赋值分页输出
        $assign_data['list']  = $list; // 赋值数据集
        $assign_data['pager'] = $Page; // 赋值分页对象

        // 栏目ID
        $assign_data['typeid'] = $typeid; // 栏目ID
        /*当前栏目信息*/
        $arctype_info = array();
        if ($typeid > 0) {
            $arctype_info = M('arctype')->field('typename')->find($typeid);
        }
        $assign_data['arctype_info'] = $arctype_info;
        /*--end*/

        /*选项卡*/
        $tab                = input('param.tab/d', 3);
        $assign_data['tab'] = $tab;
        /*--end*/
        
        /*前台URL模式*/
        $assign_data['seo_pseudo'] = tpCache('seo.seo_pseudo');

        $this->assign($assign_data);

        return $this->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        $this->check_use(); // 验证是否授权，限制发布视频的条数

        if (IS_POST) {
            $post    = input('post.');

            /* 处理TAG标签 */
            if (!empty($post['tags_new'])) {
                $post['tags'] = !empty($post['tags']) ? $post['tags'] . ',' . $post['tags_new'] : $post['tags_new'];
                unset($post['tags_new']);
            }
            $post['tags'] = explode(',', $post['tags']);
            $post['tags'] = array_unique($post['tags']);
            $post['tags'] = implode(',', $post['tags']);
            /* END */

            $content = input('post.addonFieldExt.content', '', null);

            // 根据标题自动提取相关的关键字
            $seo_keywords = $post['seo_keywords'];
            if (!empty($seo_keywords)) {
                $seo_keywords = str_replace('，', ',', $seo_keywords);
            } else {
                // $seo_keywords = get_split_word($post['title'], $content);
            }

            // 自动获取内容第一张图片作为封面图
            $is_remote = !empty($post['is_remote']) ? $post['is_remote'] : 0;
            $litpic    = '';
            if ($is_remote == 1) {
                $litpic = $post['litpic_remote'];
            } else {
                $litpic = $post['litpic_local'];
            }
            if (empty($litpic)) {
                $litpic = get_html_first_imgurl($content);
            }
            $post['litpic'] = $litpic;

            /*是否有封面图*/
            if (empty($post['litpic'])) {
                $is_litpic = 0; // 无封面图
            } else {
                $is_litpic = 1; // 有封面图
            }

            // SEO描述
            $seo_description = '';
            if (empty($post['seo_description']) && !empty($content)) {
                $seo_description = @msubstr(checkStrHtml($content), 0, config('global.arc_seo_description_length'), false);
            } else {
                $seo_description = $post['seo_description'];
            }

            // 外部链接跳转
            $jumplinks = '';
            $is_jump   = isset($post['is_jump']) ? $post['is_jump'] : 0;
            if (intval($is_jump) > 0) {
                $jumplinks = $post['jumplinks'];
            }

            // 模板文件，如果文档模板名与栏目指定的一致，默认就为空。让它跟随栏目的指定而变
            if ($post['type_tempview'] == $post['tempview']) {
                unset($post['type_tempview']);
                unset($post['tempview']);
            }

            //处理自定义文件名,仅由字母数字下划线和短横杆组成,大写强制转换为小写
            if (!empty($post['htmlfilename'])) {
                $post['htmlfilename'] = preg_replace("/[^a-zA-Z0-9_-]+/", "", $post['htmlfilename']);
                $post['htmlfilename'] = strtolower($post['htmlfilename']);
                //判断是否存在相同的自定义文件名
                $filenameCount = Db::name('archives')->where('htmlfilename', $post['htmlfilename'])->count();
                if (!empty($filenameCount)) {
                    $this->error("自定义文件名已存在，请重新设置！");
                }
            }

            // --存储数据
            $newData = array(
                'typeid'          => empty($post['typeid']) ? 0 : $post['typeid'],
                'channel'         => $this->channeltype,
                'is_b'            => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'         => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'        => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_jump'         => $is_jump,
                'is_litpic'       => $is_litpic,
                'jumplinks'       => $jumplinks,
                'seo_keywords'    => $seo_keywords,
                'seo_description' => $seo_description,
                'admin_id'        => session('admin_info.admin_id'),
                'lang'            => $this->admin_lang,
                'sort_order'      => 100,
                'users_free'      => empty($post['users_free']) ? 0 : $post['users_free'],
                'add_time'        => strtotime($post['add_time']),
                'update_time'     => strtotime($post['add_time']),
            );
            $data    = array_merge($post, $newData);

            $aid          = Db::name('archives')->insertGetId($data);
            $_POST['aid'] = $aid;
            if ($aid) {
                // ---------后置操作
                model('Media')->afterSave($aid, $data, 'add');
                // ---------end
                adminLog('新增视频：' . $data['title']);

                // 生成静态页面代码
                $successData = [
                    'aid' => $aid,
                    'tid' => $post['typeid'],
                ];
                $this->success("操作成功!", null, $successData);
                exit;
            }

            $this->error("操作失败!");
            exit;
        }

        $typeid                = input('param.typeid/d', 0);
        $assign_data['typeid'] = $typeid; // 栏目ID

        // 栏目信息
        $arctypeInfo = Db::name('arctype')->find($typeid);

        /*允许发布文档列表的栏目*/
        $arctype_html                = allow_release_arctype($typeid, array($this->channeltype));
        $assign_data['arctype_html'] = $arctype_html;
        /*--end*/

        /*自定义字段*/
        $addonFieldExtList   = model('Field')->getChannelFieldList($this->channeltype);
        $channelfieldBindRow = Db::name('channelfield_bind')->where([
            'typeid' => ['IN', [0, $typeid]],
        ])->column('field_id');
        if (!empty($channelfieldBindRow)) {
            foreach ($addonFieldExtList as $key => $val) {
                if (!in_array($val['id'], $channelfieldBindRow)) {
                    unset($addonFieldExtList[$key]);
                }
            }
        }
        $assign_data['addonFieldExtList'] = $addonFieldExtList;
        $assign_data['aid']               = 0;
        /*--end*/

        // 阅读权限
        $arcrank_list                = get_arcrank_list();
        $assign_data['arcrank_list'] = $arcrank_list;

        /*模板列表*/
        $archivesLogic = new \app\admin\logic\ArchivesLogic;
        $templateList  = $archivesLogic->getTemplateList($this->nid);
        $this->assign('templateList', $templateList);
        /*--end*/

        /*默认模板文件*/
        $tempview = 'view_' . $this->nid . '.' . config('template.view_suffix');
        !empty($arctypeInfo['tempview']) && $tempview = $arctypeInfo['tempview'];
        $this->assign('tempview', $tempview);
        /*--end*/

        // URL模式
        $tpcache                   = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;

        // 系统最大上传视频的大小
        $file_size = tpCache('basic.file_size');
        $postsize       = @ini_get('file_uploads') ? ini_get('post_max_size') : -1;
        $fileupload     = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : -1;
        $min_size = strval($file_size) < strval($postsize) ? $file_size : $postsize;
        $min_size = strval($min_size) < strval($fileupload) ? $min_size : $fileupload;
        $assign_data['upload_max_filesize'] = intval($min_size) * 1024 * 1024;
        //视频类型
        $media_type = tpCache('basic.media_type');
        $media_type = !empty($media_type) ? $media_type : "swf|mpg|mp3|rm|rmvb|wmv|wma|wav|mid|mov|mp4";
        $assign_data['media_type'] = $media_type;
        //文件类型
        $file_type = tpCache('basic.file_type');
        $file_type = !empty($file_type) ? $file_type : "zip|gz|rar|iso|doc|xls|ppt|wps";
        $assign_data['file_type'] = $file_type;

        // 模型配置信息
        $channelRow = Db::name('channeltype')->where('id', $this->channeltype)->find();
        $channelRow['data'] = json_decode($channelRow['data'], true);
        $assign_data['channelRow'] = $channelRow;
        
        // 会员等级信息
        $field = 'level_id, level_name, level_value';
        $UsersLevel = DB::name('users_level')->field($field)->where('lang', $this->admin_lang)->select();
        $assign_data['users_level'] = $UsersLevel;
        $InitialValue = $UsersLevel[0]['level_value'];
        foreach ($UsersLevel as $key => $value) {
            // 初始会员等级值
            if (1 == $value['is_system'] && !empty($value['level_value'])) {
                $InitialValue = $value['level_value'];
            }
        }
        // 初始会员等级值
        $assign_data['initial_value'] = $InitialValue;

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 编辑
     */
    public function edit()
    {
        $this->check_use(); // 验证是否授权，限制发布视频的条数

        if (IS_POST) {
            $post    = input('post.');

            /* 处理TAG标签 */
            if (!empty($post['tags_new'])) {
                $post['tags'] = !empty($post['tags']) ? $post['tags'] . ',' . $post['tags_new'] : $post['tags_new'];
                unset($post['tags_new']);
            }
            $post['tags'] = explode(',', $post['tags']);
            $post['tags'] = array_unique($post['tags']);
            $post['tags'] = implode(',', $post['tags']);
            /* END */

            $typeid  = input('post.typeid/d', 0);
            $content = input('post.addonFieldExt.content', '', null);

            // 根据标题自动提取相关的关键字
            $seo_keywords = $post['seo_keywords'];
            if (!empty($seo_keywords)) {
                $seo_keywords = str_replace('，', ',', $seo_keywords);
            } else {
                // $seo_keywords = get_split_word($post['title'], $content);
            }

            // 自动获取内容第一张图片作为封面图
            $is_remote = !empty($post['is_remote']) ? $post['is_remote'] : 0;
            $litpic    = '';
            if ($is_remote == 1) {
                $litpic = $post['litpic_remote'];
            } else {
                $litpic = $post['litpic_local'];
            }
            if (empty($litpic)) {
                $litpic = get_html_first_imgurl($content);
            }
            $post['litpic'] = $litpic;

            /*是否有封面图*/
            if (empty($post['litpic'])) {
                $is_litpic = 0; // 无封面图
            } else {
                $is_litpic = !empty($post['is_litpic']) ? $post['is_litpic'] : 0; // 有封面图
            }

            // SEO描述
            $seo_description = '';
            if (empty($post['seo_description']) && !empty($content)) {
                $seo_description = @msubstr(checkStrHtml($content), 0, config('global.arc_seo_description_length'), false);
            } else {
                $seo_description = $post['seo_description'];
            }

            // --外部链接
            $jumplinks = '';
            $is_jump   = isset($post['is_jump']) ? $post['is_jump'] : 0;
            if (intval($is_jump) > 0) {
                $jumplinks = $post['jumplinks'];
            }

            // 模板文件，如果文档模板名与栏目指定的一致，默认就为空。让它跟随栏目的指定而变
            if ($post['type_tempview'] == $post['tempview']) {
                unset($post['type_tempview']);
                unset($post['tempview']);
            }

            // 同步栏目切换模型之后的文档模型
            $channel = Db::name('arctype')->where(['id'=>$typeid])->getField('current_channel');

            //处理自定义文件名,仅由字母数字下划线和短横杆组成,大写强制转换为小写
            if (!empty($post['htmlfilename'])) {
                $post['htmlfilename'] = preg_replace("/[^a-zA-Z0-9_-]+/", "", $post['htmlfilename']);
                $post['htmlfilename'] = strtolower($post['htmlfilename']);
                //判断是否存在相同的自定义文件名
                $filenameCount = Db::name('archives')->where([
                    'aid'          => ['NEQ', $post['aid']],
                    'htmlfilename' => $post['htmlfilename'],
                ])->count();
                if (!empty($filenameCount)) {
                    $this->error("自定义文件名已存在，请重新设置！");
                }
            }

            // --存储数据
            $newData = array(
                'typeid'          => $typeid,
                'channel'         => $channel,
                'is_b'            => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'         => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'        => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_jump'         => $is_jump,
                'is_litpic'       => $is_litpic,
                'jumplinks'       => $jumplinks,
                'seo_keywords'    => $seo_keywords,
                'seo_description' => $seo_description,
                'users_free'      => empty($post['users_free']) ? 0 : $post['users_free'],
                'add_time'        => strtotime($post['add_time']),
                'update_time'     => getTime(),
            );
            $data    = array_merge($post, $newData);

            $r = Db::name('archives')->where([
                'aid'  => $data['aid'],
                'lang' => $this->admin_lang,
            ])->update($data);

            if ($r) {
                // ---------后置操作
                model('Media')->afterSave($data['aid'], $data, 'edit');
                // ---------end
                adminLog('编辑视频：' . $data['title']);

                // 生成静态页面代码
                $successData = [
                    'aid' => $data['aid'],
                    'tid' => $typeid,
                ];
                $this->success("操作成功!", null, $successData);
                exit;
            }

            $this->error("操作失败!");
            exit;
        }

        $assign_data = array();

        $id   = input('id/d');
        $info = model('Media')->getInfo($id, null, true);
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        /*兼容采集没有归属栏目的文档*/
        if (empty($info['channel'])) {
            $channelRow = Db::name('channeltype')->field('id as channel')
                ->where('id', $this->channeltype)
                ->find();
            $info       = array_merge($info, $channelRow);
        }
        /*--end*/
        $typeid = $info['typeid'];
        $assign_data['typeid'] = $typeid;
        //视频文件
        $video_file = model('MediaFile')->getMediaFile($id);
        $assign_data['video_file'] = json_encode($video_file);

        // 栏目信息
        $arctypeInfo = Db::name('arctype')->find($typeid);

        $info['channel'] = $arctypeInfo['current_channel'];
        if (is_http_url($info['litpic'])) {
            $info['is_remote']     = 1;
            $info['litpic_remote'] = handle_subdir_pic($info['litpic']);
        } else {
            $info['is_remote']    = 0;
            $info['litpic_local'] = handle_subdir_pic($info['litpic']);
        }

        // SEO描述
        if (!empty($info['seo_description'])) {
            $info['seo_description'] = @msubstr(checkStrHtml($info['seo_description']), 0, config('global.arc_seo_description_length'), false);
        }

        $assign_data['field'] = $info;

        /*允许发布文档列表的栏目，文档所在模型以栏目所在模型为主，兼容切换模型之后的数据编辑*/
        $arctype_html                = allow_release_arctype($typeid, array($info['channel']));
        $assign_data['arctype_html'] = $arctype_html;
        /*--end*/

        /*自定义字段*/
        $addonFieldExtList   = model('Field')->getChannelFieldList($info['channel'], 0, $id, $info);
        $channelfieldBindRow = Db::name('channelfield_bind')->where([
            'typeid' => ['IN', [0, $typeid]],
        ])->column('field_id');
        if (!empty($channelfieldBindRow)) {
            foreach ($addonFieldExtList as $key => $val) {
                if (!in_array($val['id'], $channelfieldBindRow)) {
                    unset($addonFieldExtList[$key]);
                }
            }
        }
        $assign_data['addonFieldExtList'] = $addonFieldExtList;
        $assign_data['aid']               = $id;
        /*--end*/

        // 阅读权限
        $arcrank_list                = get_arcrank_list();
        $assign_data['arcrank_list'] = $arcrank_list;

        /*模板列表*/
        $archivesLogic = new \app\admin\logic\ArchivesLogic;
        $templateList  = $archivesLogic->getTemplateList($this->nid);
        $this->assign('templateList', $templateList);
        /*--end*/

        /*默认模板文件*/
        $tempview = $info['tempview'];
        empty($tempview) && $tempview = $arctypeInfo['tempview'];
        $this->assign('tempview', $tempview);
        /*--end*/

        // URL模式
        $tpcache                   = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;

        // 系统最大上传视频的大小
        $file_size = tpCache('basic.file_size');
        $postsize       = @ini_get('file_uploads') ? ini_get('post_max_size') : -1;
        $fileupload     = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : -1;
        $min_size = strval($file_size) < strval($postsize) ? $file_size : $postsize;
        $min_size = strval($min_size) < strval($fileupload) ? $min_size : $fileupload;
        $assign_data['upload_max_filesize'] = intval($min_size) * 1024 * 1024;
        //视频类型
        $media_type = tpCache('basic.media_type');
        $media_type = !empty($media_type) ? $media_type : "swf|mpg|mp3|rm|rmvb|wmv|wma|wav|mid|mov|mp4";
        $assign_data['media_type'] = $media_type;
        //文件类型
        $file_type = tpCache('basic.file_type');
        $file_type = !empty($file_type) ? $file_type : "zip|gz|rar|iso|doc|xls|ppt|wps";
        $assign_data['file_type'] = $file_type;

        // 模型配置信息
        $channelRow = Db::name('channeltype')->where('id', $this->channeltype)->find();
        $channelRow['data'] = json_decode($channelRow['data'], true);
        $assign_data['channelRow'] = $channelRow;

        // 查询文档的会员等级值
        $MediaValue = 0;

        // 会员等级信息
        $field = 'level_id, level_name, level_value';
        $UsersLevel = DB::name('users_level')->field($field)->where('lang', $this->admin_lang)->select();
        $assign_data['users_level'] = $UsersLevel;
        $InitialValue = $UsersLevel[0]['level_value'];
        foreach ($UsersLevel as $key => $value) {
            // 初始会员等级值
            if (1 == $value['is_system'] && !empty($value['level_value'])) {
                $InitialValue = $value['level_value'];
            }
            // 文档的会员等级值
            if ($info['arc_level_id'] == $value['level_id'] && !empty($value['level_value'])) {
                $MediaValue = $value['level_value'];
            }

        }
        // 初始会员等级值
        $assign_data['initial_value'] = $InitialValue;
        // 文档的会员等级值
        $assign_data['media_value'] = $MediaValue;

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 删除
     */
    public function del()
    {
        if (IS_POST) {
            $archivesLogic = new \app\admin\logic\ArchivesLogic;
            $archivesLogic->del();
        }
    }

    /**
     * 获取七牛云token
     */
/*    public function qiniu_upload()
    {
        if (IS_AJAX_POST) {
            $weappInfo     = Db::name('weapp')->where('code','Qiniuyun')->field('id,status,data')->find();
            if (empty($weappInfo)) {
                $this->error('请先安装配置【七牛云图片加速】插件!', null, ['code'=>-1]);
            } else if (1 != $weappInfo['status']) {
                $this->error('请先启用【七牛云图片加速】插件!', null, ['code'=>-2,'id'=>$weappInfo['id']]);
            } else {
                $Qiniuyun = json_decode($weappInfo['data'], true);
                if (empty($Qiniuyun)) {
                    $this->error('请先配置【七牛云图片加速】插件!', null, ['code'=>-3]);
                } else if (empty($Qiniuyun['domain'])) {
                    $this->error('请先配置【七牛云图片加速】插件中的域名!', null, ['code'=>-3]);
                }
            }

            //引入七牛云的相关文件
            weapp_vendor('Qiniu.src.Qiniu.Auth', 'Qiniuyun');
            weapp_vendor('Qiniu.src.Qiniu.Storage.UploadManager', 'Qiniuyun');
            require_once ROOT_PATH.'weapp/Qiniuyun/vendor/Qiniu/autoload.php';

            // 配置信息
            $accessKey = $Qiniuyun['access_key'];
            $secretKey = $Qiniuyun['secret_key'];
            $bucket    = $Qiniuyun['bucket'];
            $domain    = '//'.$Qiniuyun['domain'];

            // 区域对应的上传URl
            $config = new \Qiniu\Config(null);
            $uphost  = $config->getUpHost($accessKey, $bucket);
            $uphost = str_replace('http://', '//', $uphost);

            // 生成上传Token
            $auth = new \Qiniu\Auth($accessKey, $secretKey);
            $token = $auth->uploadToken($bucket);
            if ($token) {
                $filePath = UPLOAD_PATH.'media/' . date('Ymd/') . session('admin_id') . '-' . dd2char(date("ymdHis") . mt_rand(100, 999));
                $data = [
                    'token'  => $token,
                    'domain'  => $domain,
                    'uphost'  => $uphost,
                    'filePath'  => $filePath,
                ];
                $this->success('获取token成功!', null, $data);
            } else {
                $this->error('获取token失败!');
            }
        }

    }*/

    /**
     * 验证是否授权可用
     * @return [type] [description]
     */
    public function check_use()
    {
        $web_is_authortoken = tpCache('web.web_is_authortoken');
        if (-1 == $web_is_authortoken) {
            $num = 3;
            $count = Db::name('archives')->where(['channel'=>5])->count();
            if ($num <= $count) {
                $this->error('仅限发布'.$num.'篇视频文档，请购买商业授权！');
                // $this->error('仅限发布'.$num.'篇视频文档，请购买商业授权！', null, ['icon'=>4]);
            }
        }
    }
}