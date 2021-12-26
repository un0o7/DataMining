<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 易而优团队 by 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\admin\controller;

use think\Page;
use think\Db;
use app\common\logic\ArctypeLogic;
use app\admin\logic\ProductLogic;
use app\admin\logic\ProductSpecLogic; // 用于商品规格逻辑功能处理

class ShopProduct extends Base
{
    // 模型标识
    public $nid = 'product';
    // 模型ID
    public $channeltype = '';
    // 表单类型
    public $attrInputTypeArr = array();
    
    public function _initialize() {
        parent::_initialize();
        $this->language_access(); // 多语言功能操作权限
        $channeltype_list = config('global.channeltype_list');
        $this->channeltype = $channeltype_list[$this->nid];
        empty($this->channeltype) && $this->channeltype = 2;
        $this->attrInputTypeArr = config('global.attr_input_type_arr');
        $this->assign('nid', $this->nid);
        $this->assign('channeltype', $this->channeltype);

        // 产品属性表
        $this->product_attrlist_db = Db::name('product_attrlist');
        // 商城产品参数表
        $this->shop_product_attrlist_db = Db::name('shop_product_attrlist');
        // 产品规格表
        $this->product_spec_preset_db = Db::name('product_spec_preset');
        // 产品规格值表
        $this->product_spec_value_db = Db::name('product_spec_value');
        // 规格业务层
        $this->ProductSpecLogic = new ProductSpecLogic;
        // 规格名称模型层
        $this->ProductSpecPresetModel = model('ProductSpecPreset');
        // 规格值模型层
        $this->ProductSpecValueModel = model('ProductSpecValue');
    }

    /**
     * 文章列表
     */
    public function index()
    {
        $assign_data = array();
        $condition = array();
        // 获取到所有GET参数
        $param = input('param.');
        $flag = input('flag/s');
        $typeid = input('typeid/d', 0);
        $begin = strtotime(input('add_time_begin'));
        $end = strtotime(input('add_time_end'));

        // 应用搜索条件
        foreach (['keywords','typeid','flag','is_release'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.title'] = array('LIKE', "%{$param[$key]}%");
                } else if ($key == 'typeid') {
                    $typeid = $param[$key];
                    $hasRow = model('Arctype')->getHasChildren($typeid);
                    $typeids = get_arr_column($hasRow, 'id');
                    /*权限控制 by 小虎哥*/
                    $admin_info = session('admin_info');
                    if (0 < intval($admin_info['role_id'])) {
                        $auth_role_info = $admin_info['auth_role_info'];
                        if(! empty($auth_role_info)){
                            if(! empty($auth_role_info['permission']['arctype'])){
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
                        $condition['a.'.$param[$key]] = array('eq', 1);
                    }
                // } else if ($key == 'is_release') {
                //     if (0 < intval($param[$key])) {
                //         $condition['a.users_id'] = array('gt', intval($param[$key]));
                //     }
                } else {
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        /*权限控制 by 小虎哥*/
        $admin_info = session('admin_info');
        if (0 < intval($admin_info['role_id'])) {
            $auth_role_info = $admin_info['auth_role_info'];
            if(! empty($auth_role_info)){
                if(isset($auth_role_info['only_oneself']) && 1 == $auth_role_info['only_oneself']){
                    $condition['a.admin_id'] = $admin_info['admin_id'];
                }
            }
        }
        /*--end*/
        
        // 时间检索
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between',"$begin,$end");
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
        $orderby = input('param.orderby/s');
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
        $Page = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = DB::name('archives')
            ->field("a.aid")
            ->alias('a')
            ->where($condition)
            ->order($orderby)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->getAllWithIndex('aid');

        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        if ($list) {
            $aids = array_keys($list);
            $fields = "b.*, a.*, a.aid as aid";
            $row = DB::name('archives')
                ->field($fields)
                ->alias('a')
                ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                ->where('a.aid', 'in', $aids)
                ->getAllWithIndex('aid');
            foreach ($list as $key => $val) {
                $row[$val['aid']]['arcurl'] = get_arcurl($row[$val['aid']]);
                $row[$val['aid']]['litpic'] = handle_subdir_pic($row[$val['aid']]['litpic']); // 支持子目录
                $list[$key] = $row[$val['aid']];
            }
        }
        $show = $Page->show(); // 分页显示输出
        $assign_data['page'] = $show; // 赋值分页输出
        $assign_data['list'] = $list; // 赋值数据集
        $assign_data['pager'] = $Page; // 赋值分页对象

        /*允许发布文档列表的栏目*/
        $arctype_html = allow_release_arctype($typeid, array($this->channeltype));
        $assign_data['arctype_html'] = $arctype_html;
        /*--end*/

        /*当前栏目信息*/
        $assign_data['typeid'] = $typeid; // 栏目ID
        $arctype_info = array();
        if ($typeid > 0) $arctype_info = M('arctype')->field('typename')->find($typeid);
        $assign_data['arctype_info'] = $arctype_info;
        /*--end*/

        /*选项卡*/
        $tab = input('param.tab/d', 3);
        $assign_data['tab'] = $tab;
        /*--end*/

        $this->assign($assign_data);

        return $this->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if (IS_POST) {
            $post = input('post.');

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
            $litpic = '';
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
            $is_jump = isset($post['is_jump']) ? $post['is_jump'] : 0;
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
                $filenameCount = Db::name('archives')->where([
                        'htmlfilename'  => $post['htmlfilename'],
                    ])->count();
                if (!empty($filenameCount)) {
                    $this->error("自定义文件名已存在，请重新设置！");
                }
            }

            // 产品类型
            if (!empty($post['prom_type'])) {
                if ($post['prom_type_vir'] == 2) {
                    $post['netdisk_url'] = trim($post['netdisk_url']);
                    if (empty($post['netdisk_url'])) {
                        $this->error("网盘地址不能为空！");
                    }
                    $post['prom_type'] = 2;
                } else if ($post['prom_type_vir'] == 3) {
                    $post['text_content'] = trim($post['text_content']);
                    if (empty($post['text_content'])) {
                        $this->error("虚拟文本内容不能为空！");
                    }
                    $post['prom_type'] = 3;
                }
            }

            // --存储数据
            $newData = array(
                'typeid'=> empty($post['typeid']) ? 0 : $post['typeid'],
                'channel'   => $this->channeltype,
                'is_b'      => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'      => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'      => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_jump'     => $is_jump,
                'is_litpic'     => $is_litpic,
                'jumplinks' => $jumplinks,
                'seo_keywords'     => $seo_keywords,
                'seo_description'     => $seo_description,
                'admin_id'  => session('admin_info.admin_id'),
                'stock_show'    => empty($post['stock_show']) ? 0 : $post['stock_show'],
                'lang'  => $this->admin_lang,
                'sort_order'    => 100,
                'add_time'     => strtotime($post['add_time']),
                'update_time'  => strtotime($post['add_time']),
            );
            $data = array_merge($post, $newData);
            $aid = Db::name('archives')->insertGetId($data);
            $_POST['aid'] = $aid;
            if ($aid) {
                // ---------后置操作
                model('Product')->afterSave($aid, $data, 'add', true);
                // ---------end
                
                // 添加产品规格
                model('ProductSpecPreset')->ProductSpecInsertAll($aid, $data);
                adminLog('新增产品：'.$data['title']);

                //虚拟商品保存
                if (!empty($post['prom_type']) && in_array($post['prom_type'], [2,3])) {
                    model('ProductNetdisk')->saveProductNetdisk($aid, $data);
                }

                // 生成静态页面代码
                $successData = [
                    'aid' => $aid,
                    'tid' => $post['typeid'],
                ];
                $this->success("操作成功!", url('ShopProduct/index'), $successData);
            }
            $this->error("操作失败!");
        }

        $typeid = input('param.typeid/d', 0);
        $assign_data['typeid'] = $typeid; // 栏目ID

        // 栏目信息
        $arctypeInfo = Db::name('arctype')->find($typeid);

        /*允许发布文档列表的栏目*/
        $arctype_html = allow_release_arctype($typeid, array($this->channeltype));
        $assign_data['arctype_html'] = $arctype_html;
        /*--end*/

        /*自定义字段*/
        $addonFieldExtList = model('Field')->getChannelFieldList($this->channeltype);
        $channelfieldBindRow = Db::name('channelfield_bind')->where([
                'typeid'    => ['IN', [0, $typeid]],
            ])->column('field_id');
        if (!empty($channelfieldBindRow)) {
            foreach ($addonFieldExtList as $key => $val) {
                if (!in_array($val['id'], $channelfieldBindRow)) {
                    unset($addonFieldExtList[$key]);
                }
            }
        }
        $assign_data['addonFieldExtList'] = $addonFieldExtList;
        $assign_data['aid'] = 0;
        /*--end*/

        /*可控制的字段列表*/
        $assign_data['ifcontrolRow'] = Db::name('channelfield')->field('id,name')->where([
                'channel_id'    => $this->channeltype,
                'ifmain'        => 1,
                'ifeditable'    => 1,
                'ifcontrol'     => 0,
                'status'        => 1,
            ])->getAllWithIndex('name');

        // 阅读权限
        $arcrank_list = get_arcrank_list();
        $assign_data['arcrank_list'] = $arcrank_list;

        /*产品参数*/
        $assign_data['canshu'] = $this->ajax_get_attr_input($typeid);
        /*--end*/
        
        /*模板列表*/
        $archivesLogic = new \app\admin\logic\ArchivesLogic;
        $templateList = $archivesLogic->getTemplateList($this->nid);
        $this->assign('templateList', $templateList);
        /*--end*/

        /*默认模板文件*/
        $tempview = 'view_'.$this->nid.'.'.config('template.view_suffix');
        !empty($arctypeInfo['tempview']) && $tempview = $arctypeInfo['tempview'];
        $this->assign('tempview', $tempview);
        /*--end*/

        // 商城配置
        $shopConfig = getUsersConfigData('shop');
        $assign_data['shopConfig'] = $shopConfig;

        // 商品规格
        if (isset($shopConfig['shop_open_spec']) && 1 == $shopConfig['shop_open_spec']) {
            // 预设值名称
            $assign_data['preset_value'] = Db::name('product_spec_preset')->where('lang',$this->admin_lang)->field('preset_id,preset_mark_id,preset_name')->group('preset_mark_id')->order('preset_mark_id desc')->select();
        }

        /*商品参数列表*/
        $where = [
            'is_del' => 0,
            'status' => 1
        ];
        $assign_data['AttrList'] = $this->shop_product_attrlist_db->where($where)->order('sort_order asc')->select();
        /*END*/

        // URL模式
        $tpcache = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;

        $this->assign($assign_data);

        return $this->fetch();
    }
    
    /**
     * 编辑
     */
    public function edit()
    {
        if (IS_POST) {
            $post = input('post.');

            /* 处理TAG标签 */
            if (!empty($post['tags_new'])) {
                $post['tags'] = !empty($post['tags']) ? $post['tags'] . ',' . $post['tags_new'] : $post['tags_new'];
                unset($post['tags_new']);
            }
            $post['tags'] = explode(',', $post['tags']);
            $post['tags'] = array_unique($post['tags']);
            $post['tags'] = implode(',', $post['tags']);
            /* END */

            $typeid = input('post.typeid/d', 0);
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
            $litpic = '';
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
            $is_jump = isset($post['is_jump']) ? $post['is_jump'] : 0;
            if (intval($is_jump) > 0) {
                $jumplinks = $post['jumplinks'];
            }

            // 模板文件，如果文档模板名与栏目指定的一致，默认就为空。让它跟随栏目的指定而变
            if ($post['type_tempview'] == $post['tempview']) {
                unset($post['type_tempview']);
                unset($post['tempview']);
            }

            // 产品类型
            if (!empty($post['prom_type'])) {
                if ($post['prom_type_vir'] == 2) {
                    $post['netdisk_url'] = trim($post['netdisk_url']);
                    if (empty($post['netdisk_url'])) {
                        $this->error("网盘地址不能为空！");
                    }
                    $post['prom_type'] = 2;
                } else if ($post['prom_type_vir'] == 3) {
                    $post['text_content'] = trim($post['text_content']);
                    if (empty($post['text_content'])) {
                        $this->error("虚拟文本内容不能为空！");
                    }
                    $post['prom_type'] = 3;
                }
            }

            //处理自定义文件名,仅由字母数字下划线和短横杆组成,大写强制转换为小写
            if (!empty($post['htmlfilename'])) {
                $post['htmlfilename'] = preg_replace("/[^a-zA-Z0-9_-]+/", "", $post['htmlfilename']);
                $post['htmlfilename'] = strtolower($post['htmlfilename']);
                //判断是否存在相同的自定义文件名
                $filenameCount = Db::name('archives')->where([
                        'aid'   => ['NEQ', $post['aid']],
                        'htmlfilename'  => $post['htmlfilename'],
                    ])->count();
                if (!empty($filenameCount)) {
                    $this->error("自定义文件名已存在，请重新设置！");
                }
            }

            // 同步栏目切换模型之后的文档模型
            $channel = Db::name('arctype')->where(['id'=>$typeid])->getField('current_channel');
            // --存储数据
            $newData = array(
                'typeid'=> $typeid,
                'channel'   => $channel,
                'is_b'      => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'      => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'      => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_jump'   => $is_jump,
                'is_litpic'     => $is_litpic,
                'jumplinks' => $jumplinks,
                'seo_keywords'     => $seo_keywords,
                'seo_description'     => $seo_description,
                'stock_show'    => empty($post['stock_show']) ? 0 : $post['stock_show'],
                'add_time'     => strtotime($post['add_time']),
                'update_time'     => getTime(),
            );
            $data = array_merge($post, $newData);

            $r = Db::name('archives')->where([
                    'aid'   => $data['aid'],
                    'lang'  => $this->admin_lang,
                ])->update($data);
            
            if ($r) {
                // ---------后置操作
                model('Product')->afterSave($data['aid'], $data, 'edit', true);
                
                // 更新规格名称数据
                // model('ProductSpecData')->ProducSpecNameEditSave($data);

                //虚拟商品保存
                if (!empty($post['prom_type']) && in_array($post['prom_type'], [2,3])) {
                    model('ProductNetdisk')->saveProductNetdisk($data['aid'], $data);
                }

                // 更新规格值及金额数据
                model('ProductSpecValue')->ProducSpecValueEditSave($data);
                // ---------end
                adminLog('编辑产品：'.$data['title']);

                // 生成静态页面代码
                $successData = [
                    'aid'       => $data['aid'],
                    'tid'       => $typeid,
                ];
                $this->success("操作成功!", url('ShopProduct/index'), $successData);
            }
            $this->error("操作失败!");
        }

        $assign_data = array();

        $id = input('id/d');
        $info = model('Product')->getInfo($id);

        // 获取规格数据信息
        // 包含：SpecSelectName、HtmlTable、spec_mark_id_arr、preset_value
        $assign_data = model('ProductSpecData')->GetProductSpecData($id);
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        /*兼容采集没有归属栏目的文档*/
        if (empty($info['channel'])) {
            $channelRow = Db::name('channeltype')->field('id as channel')
                ->where('id',$this->channeltype)
                ->find();
            $info = array_merge($info, $channelRow);
        }
        /*--end*/
        $typeid = $info['typeid'];
        $assign_data['typeid'] = $typeid;

        // 栏目信息
        $arctypeInfo = Db::name('arctype')->find($typeid);

        $info['channel'] = $arctypeInfo['current_channel'];
        if (is_http_url($info['litpic'])) {
            $info['is_remote'] = 1;
            $info['litpic_remote'] = handle_subdir_pic($info['litpic']);
        } else {
            $info['is_remote'] = 0;
            $info['litpic_local'] = handle_subdir_pic($info['litpic']);
        }
    
        // SEO描述
        if (!empty($info['seo_description'])) {
            $info['seo_description'] = @msubstr(checkStrHtml($info['seo_description']), 0, config('global.arc_seo_description_length'), false);
        }

        $assign_data['field'] = $info;

        // 产品相册
        $proimg_list = model('ProductImg')->getProImg($id);
        foreach ($proimg_list as $key => $val) {
            $proimg_list[$key]['image_url'] = handle_subdir_pic($val['image_url']); // 支持子目录
        }
        $assign_data['proimg_list'] = $proimg_list;

        /*允许发布文档列表的栏目，文档所在模型以栏目所在模型为主，兼容切换模型之后的数据编辑*/
        $arctype_html = allow_release_arctype($typeid, array($info['channel']));
        $assign_data['arctype_html'] = $arctype_html;
        /*--end*/
        
        /*自定义字段*/
        $addonFieldExtList = model('Field')->getChannelFieldList($info['channel'], 0, $id, $info);
        $channelfieldBindRow = Db::name('channelfield_bind')->where([
                'typeid'    => ['IN', [0,$typeid]],
            ])->column('field_id');
        if (!empty($channelfieldBindRow)) {
            foreach ($addonFieldExtList as $key => $val) {
                if (!in_array($val['id'], $channelfieldBindRow)) {
                    unset($addonFieldExtList[$key]);
                }
            }
        }
        $assign_data['addonFieldExtList'] = $addonFieldExtList;
        $assign_data['aid'] = $id;
        /*--end*/

        /*可控制的主表字段列表*/
        $assign_data['ifcontrolRow'] = Db::name('channelfield')->field('id,name')->where([
                'channel_id'    => $this->channeltype,
                'ifmain'        => 1,
                'ifeditable'    => 1,
                'ifcontrol'     => 0,
                'status'        => 1,
            ])->getAllWithIndex('name');

        /*虚拟商品内容读取*/
        $assign_data['netdisk'] = Db::name("product_netdisk")->where('aid', $id)->find();
        /*end*/

        // 阅读权限
        $arcrank_list = get_arcrank_list();
        $assign_data['arcrank_list'] = $arcrank_list;

        /*产品参数*/
        $assign_data['canshu'] = $this->ajax_get_attr_input($typeid, $id);
        /*--end*/

        /*模板列表*/
        $archivesLogic = new \app\admin\logic\ArchivesLogic;
        $templateList = $archivesLogic->getTemplateList($this->nid);
        $this->assign('templateList', $templateList);
        /*--end*/

        /*默认模板文件*/
        $tempview = $info['tempview'];
        empty($tempview) && $tempview = $arctypeInfo['tempview'];
        $this->assign('tempview', $tempview);
        /*--end*/

        // 商城配置
        $shopConfig = getUsersConfigData('shop');
        $assign_data['shopConfig'] = $shopConfig;

        // 处理产品价格属性
        $IsSame = '';
        if (empty($shopConfig['shop_type']) || 1 == $shopConfig['shop_type']) {
            if ($shopConfig['shop_type'] == $assign_data['field']['prom_type']) {
                $IsSame = '0'; // 相同
            }else{
                $IsSame = '1'; // 不相同
            }
        }
        $assign_data['IsSame'] = $IsSame;

        // URL模式
        $tpcache = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;

        /*商品参数列表*/
        $where = [
            'is_del' => 0,
            'status' => 1
        ];
        $assign_data['AttrList'] = $this->shop_product_attrlist_db->where($where)->order('sort_order asc')->select();
        /*END*/

        /*商品参数值*/
        $assign_data['canshu'] = '';
        if (!empty($info['attrlist_id'])) {
            $assign_data['canshu'] = $this->ajax_get_shop_attr_input($typeid, $id, $info['attrlist_id']);
        }
        /*--end*/
        
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
     * 删除商品相册图
     */
    public function del_proimg()
    {
        if (IS_POST) {
            $filename= input('filename/s');
            $filename= str_replace('../','',$filename);
            $filename= trim($filename,'.');
            if(eyPreventShell($filename) && !empty($filename)){
                $filename_new = trim($filename,'/');
                $filetype = preg_replace('/^(.*)\.(\w+)$/i', '$2', $filename);
                $phpfile = strtolower(strstr($filename,'.php'));  //排除PHP文件
                $size = getimagesize($filename_new);
                $fileInfo = explode('/',$size['mime']);
                if((file_exists($filename_new) && $fileInfo[0] != 'image') || $phpfile || !in_array($filetype, explode(',', config('global.image_ext')))){
                    exit;
                }
                if (!empty($filename)) {
                    M('product_img')->where("image_url = '$filename'")->delete();
                }
            }
        }
    }

    // 商品编号查重效验
    public function product_number_check()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $where = [];
            if (!empty($post['aid'])) $where['aid'] = ['NOT IN', $post['aid']];
            if (!empty($post['product_number'])) {
                $where['product_number'] = $post['product_number'];
            } else {
                $this->error('请填写商品编号');
            }
            $Result = Db::name('archives')->where($where)->count('aid');
            if (!empty($Result)) {
                $this->error('商品编号已存在');
            } else {
                $this->success('商品编号正常');
            }
        }
    }

    // 商品规格列表
    public function spec_index()
    {
        // 获取所有商品规格名称，分页返回
        $PresetData = $this->ProductSpecPresetModel->GetAllSpecPreset(input('param.'));
        $this->assign($PresetData);
        return $this->fetch();
    }

    // 保存规格
    public function spec_save()
    {
        if (IS_AJAX_POST) {
            $param = input('param.');

            $SaveData = [];
            foreach ($param['preset_name'] as $key => $value) {
                // 规格名称为空则跳过
                if (empty($value)) continue;

                // 可添加数据
                $SaveData[$key] = [
                    'preset_id'    => $param['preset_id'][$key],
                    'preset_name'  => $value,
                    'preset_desc'  => $param['preset_desc'][$key],
                    'preset_type'  => $param['preset_type'][$key],
                    'sort_order'   => $param['sort_order'][$key],
                    'update_time'  => getTime()
                ];

                // 若为空则清除主键字段
                if (empty($SaveData[$key]['preset_id'])) {
                    $SaveData[$key]['add_time'] = getTime();
                    unset($SaveData[$key]['preset_id']);
                }
            }

            $ResultID = $this->ProductSpecPresetModel->saveAll($SaveData);
            if (!empty($ResultID)) {
                $this->success('更新成功');
            } else {
                $this->error('更新失败');
            }
        }
    }

    // 删除规格值
    public function spec_del()
    {
        $PresetID = input('del_id/a');
        $PresetID = eyIntval($PresetID);
        if(!empty($PresetID)) {
            $Result = $this->product_spec_preset_db->where('preset_id', 'IN', $PresetID)->delete();
            if($Result) {
                adminLog('删除商品规格-id：'.implode(',', $PresetID));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        } else {
            $this->error('参数有误');
        }
    }

    // 规格值列表
    public function spec_value_index()
    {
        $PresetID = input('id/d');
        // 获取单条规格名称数据
        $PresetData = $this->ProductSpecPresetModel->GetFindSpecPreset($PresetID);
        if (empty($PresetData)) $this->error('请先添加规格名称');
        $this->assign('PresetData', $PresetData);

        $Where['preset_id'] = $PresetID;
        $keywords = input('keywords/s');
        if (!empty($keywords)) $Where['preset_value'] = ['LIKE', "%{$keywords}%"];

        // 分页
        $count = $this->product_spec_value_db->where($Where)->count('preset_id');
        $pageObj = new Page($count, 10);
        $pageStr = $pageObj->show();
        $this->assign('pageObj', $pageObj);
        $this->assign('pageStr', $pageStr);

        // 查询规格数据
        $ValueData = $this->product_spec_value_db
            ->where($Where)
            ->order('sort_order asc, value_id asc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();
        foreach ($ValueData as $key => $value) {
            $ValueData[$key]['value_img_src'] = get_default_pic($value['value_img']);
            if (0 == $PresetData['preset_type']) unset($value['value_img']);
        }

        $this->assign('ValueData', $ValueData);
        return $this->fetch();
    }

    // 保存规格值
    public function spec_value_save()
    {
        if (IS_AJAX_POST) {
            $param = input('param.');

            $SaveData = [];
            $AddNum = 0;
            foreach ($param['value_name'] as $key => $value) {
                if (empty($value)) continue;

                // 可添加数据
                $SaveData[$key] = [
                    'value_id'    => $param['value_id'][$key],
                    'preset_id'   => $param['preset_id'],
                    'value_name'  => $value,
                    'value_img'   => !empty($param['value_img'][$key]) ? $param['value_img'][$key] : '',
                    'sort_order'  => $param['sort_order'][$key],
                    'update_time' => getTime()
                ];

                // 若为空则清除主键字段
                if (empty($SaveData[$key]['value_id'])) {
                    $AddNum++;
                    $SaveData[$key]['add_time'] = getTime();
                    unset($SaveData[$key]['value_id']);
                }
            }

            $ResultID = $this->ProductSpecValueModel->saveAll($SaveData);
            if (!empty($ResultID)) {
                /*更新规格名称表的规格值数量*/
                $this->product_spec_preset_db->where('preset_id', $param['preset_id'])->setInc('preset_value', $AddNum);
                /*END*/
                $this->success('更新成功');
            } else {
                $this->error('更新失败');
            }
        }
    }

    // 删除规格值
    public function spec_value_del()
    {
        $ValueID = input('del_id/a');
        $ValueID = eyIntval($ValueID);
        if(!empty($ValueID)){
            $Result = $this->product_spec_value_db->where('value_id', 'IN', $ValueID)->delete();
            if($Result){
                adminLog('删除商品规格值-id：'.implode(',', $ValueID));
                /*更新规格名称表的规格值数量*/
                $DelNum = count($ValueID);
                $this->product_spec_preset_db->where('preset_id', input('post.preset_id/d'))->setDec('preset_value', $DelNum);
                /*END*/
                $this->success('删除成功');
            }else{
                $this->error('删除失败');
            }
        }else{
            $this->error('参数有误');
        }
    }

    // 规格模板
    public function spec_template()
    {
        // 查询规格数据
        $PresetData = $this->product_spec_preset_db->where('status', 1)->order('sort_order asc')->select();
        $PresetID = get_arr_column($PresetData, 'preset_id');
        
        $where = [
            'status' => 1,
            'preset_id' => ['IN', $PresetID]
        ];
        $ValueData = $this->product_spec_value_db->where($where)->order('sort_order asc')->select();
        $ValueData = group_same_key($ValueData, 'preset_id');

        foreach ($PresetData as $key => $value) {
            $PresetData[$key]['SpecValue'] = !empty($ValueData[$value['preset_id']]) ? $ValueData[$value['preset_id']] : [];
        }

        $this->assign('PresetData', $PresetData);
        return $this->fetch('spec_template');
    }

    public function combi_spec_data()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 刷新或重新进入产品添加页则清除关于产品session
            if (isset($post['initialization']) && !empty($post['initialization'])) {
                session('SpecID', null); $this->success('初始化完成');
            }

            // 初始化变量
            $HtmlTable = $SelectedSpec = null;

            // 获取已选规格拼装及生成规格HTML的ID
            if (!empty($post['preset_id'])) {
                $ResultData = $this->ProductSpecLogic->GetSelectedSpec($post);
                $SelectedSpec = $ResultData['SelectedSpec'];
            }

            // 清除一整条规格信息，清除session中相应的数据
            if (!empty($post['del_preset_id']) && empty($post['del_value_id'])) {
                $ResultData = $this->ProductSpecLogic->ClearSpecPresetMarkID($post);
            }

            // 删除单个规格值则清除session对应的值
            if (!empty($post['del_preset_id']) && !empty($post['del_value_id'])) {
                $ResultData = $this->ProductSpecLogic->ClearSpecPresetValueID($post);
            }

            // 获取规格拼装后的html表格
            if (isset($post['aid']) && !empty($post['aid'])) {
                // 编辑
                $HtmlTable = $this->ProductSpecLogic->SpecAssembly($ResultData['ResultID'], $post['aid']);
            } else {
                // 新增
                $HtmlTable = $this->ProductSpecLogic->SpecAssembly($ResultData['ResultID'], $post['aid']);
            }

            // 返回数据
            $ReturnData = [
                'HtmlTable' => $HtmlTable,
                'SelectedSpec' => $SelectedSpec
            ];
            $this->success('加载成功！', null, $ReturnData);
        }
    }

    // 商品属性列表
    public function attrlist_index()
    {   
        // 查询条件
        $Where['is_del'] = 0;
        $keywords = input('keywords/s');
        if (!empty($keywords)) $Where['list_name'] = ['LIKE', "%{$keywords}%"];

        // 分页
        $count = $this->shop_product_attrlist_db->where($Where)->count('list_id');
        $pageObj = new Page($count, config('paginate.list_rows'));
        $pageStr = $pageObj->show();
        $this->assign('pageObj', $pageObj);
        $this->assign('pageStr', $pageStr);

        // 数据
        $list = $this->shop_product_attrlist_db
            ->where($Where)
            ->order('sort_order asc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    // 新增商品属性
    public function attrlist_add()
    {
        function_exists('set_time_limit') && set_time_limit(0);

        if(IS_AJAX_POST) {
            $post = input('post.');
            // 参数名称不可重复
            $ListName = array_unique($post['list_name']);
            if (count($ListName) != count($post['list_name'])) $this->error('参数名称不可重复！');
            
            // 数据拼装
            $SaveData = [];
            foreach ($ListName as $key => $value) {
                if (!empty($value)) {
                    $list_id    = $post['list_id'][$key];
                    $list_name  = trim($value);

                    $SaveData[$key] = [
                        'list_id'    => !empty($list_id) ? $list_id : 0,
                        'list_name'  => $list_name,
                        'desc'       => !empty($post['desc'][$key]) ? $post['desc'][$key] : '',
                        'sort_order' => !empty($post['sort_order'][$key]) ? $post['sort_order'][$key] : 100,
                        'update_time' => getTime()
                    ];

                    if (empty($list_id)) {
                        $SaveData[$key]['add_time'] = getTime();
                        unset($SaveData[$key]['list_id']);
                    }
                }
            }

            $ReturnId = model('ShopProductAttrlist')->saveAll($SaveData);
            if ($ReturnId) {
                adminLog('新增商品参数：'.implode(',', $post['list_name']));
                $this->success('操作成功', url('Product/attrlist_index'));
            } else {
                $this->error('操作失败');
            }
        }
    }

    // 参数删除
    public function attrlist_del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(!empty($id_arr)){
            $Result = $this->shop_product_attrlist_db->where('list_id', 'IN', $id_arr)->delete();
            if($Result){
                adminLog('删除商品参数-id：'.implode(',', $id_arr));
                $this->success('删除成功');
            }else{
                $this->error('删除失败');
            }
        }else{
            $this->error('参数有误');
        }
    }

    /**
     * 商品参数值列表
     */
    public function attribute_index()
    {
        $condition = array();
        // 获取到所有GET参数
        $get = input('get.');
        $list_id = input('list_id/d', 0);

        // 应用搜索条件
        foreach (['keywords', 'list_id'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.attr_name'] = ['LIKE', "%{$get[$key]}%"];
                } else if ($key == 'list_id') {
                    $condition['a.list_id'] = $list_id;
                } else {
                    $condition['a.'.$key] = ['eq', $get[$key]];
                }
            }
        }
        $condition['a.is_del'] = 0;

        // 分页
        $count = DB::name('shop_product_attribute')->alias('a')->where($condition)->count();
        $pageObj = new Page($count, config('paginate.list_rows'));
        $pageStr = $pageObj->show();
        $this->assign('pageObj', $pageObj);
        $this->assign('pageStr', $pageStr);
        
        // 数据
        $list = DB::name('shop_product_attribute')
            ->alias('a')
            ->where($condition)
            ->order('a.sort_order asc, a.attr_id asc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();

        $attrInputTypeArr = [
            0 => '手工录入',
            1 => '选取默认值'
        ];
        $this->assign('attrInputTypeArr', $attrInputTypeArr);
        $this->assign('list', $list);
        return $this->fetch();
    }

   

    /**
     * 新增商品参数
     */
    public function attribute_add()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);
        
        if(IS_AJAX_POST) {
            $attr_values = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符            
            $attr_values = trim($attr_values);
            $post_data   = input('post.');
            $post_data['attr_values'] = $attr_values;

            $SaveData = array(
                'attr_name'       => $post_data['attr_name'],
                'list_id'         => $post_data['list_id'],
                'attr_input_type' => isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : '',
                'attr_values'     => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'sort_order'      => $post_data['sort_order'],
                'status'          => 1,
                'add_time'        => getTime(),
                'update_time'     => getTime(),
            );

            $ReturnId = Db::name('shop_product_attribute')->add($SaveData);
            if ($ReturnId) {
                // 参数值合计增加
                Db::name('shop_product_attrlist')->where('list_id', $post_data['list_id'])->setInc('attr_count');
                adminLog('新增商品参数：'.$SaveData['attr_name']);
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            } 
        }

        $list_id = input('param.list_id/d', 0);
        $list = $this->shop_product_attrlist_db->where('list_id', $list_id)->find();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 编辑商品参数
     */
    public function attribute_edit()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);

        if(IS_AJAX_POST) {
            $attr_values = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符            
            $attr_values = trim($attr_values);
            $post_data = input('post.');
            $post_data['attr_values'] = $attr_values;

            $SaveData = array(
                'attr_name'       => $post_data['attr_name'],
                'list_id'         => $post_data['list_id'],
                'attr_input_type' => isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : '',
                'attr_values'     => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'sort_order'      => $post_data['sort_order'],
                'update_time'     => getTime(),
            );

            $ReturnId = Db::name('shop_product_attribute')->where('attr_id', $post_data['attr_id'])->update($SaveData);
            if ($ReturnId) {
                adminLog('编辑商品参数：'.$SaveData['attr_name']);
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            }
        }

        $info = Db::name('shop_product_attribute')->where('attr_id', input('id/d'))->find();
        if (empty($info)) $this->error('数据不存在，请联系管理员！');
        $this->assign('field', $info);

        $list = $this->shop_product_attrlist_db->where('list_id', $info['list_id'])->find();
        $this->assign('list', $list);
        return $this->fetch();
    }
    
    /**
     * 删除商品参数
     */
    public function attribute_del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(!empty($id_arr)){
            $r = Db::name('shop_product_attribute')->where(['attr_id' => ['IN', $id_arr]])->delete();
            if($r){
                $IDCount = count($id_arr);
                Db::name('shop_product_attrlist')->where('list_id', input('list_id/d'))->setDec('attr_count', $IDCount);
                adminLog('删除商品参数-id：'.implode(',', $id_arr));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        } else {
            $this->error('参数有误');
        }
    }

    /**
     * 动态获取商品参数输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajax_get_shop_attr_input($typeid = '', $aid = '', $list_id = '')
    {
        $typeid = intval($typeid);
        $aid = intval($aid);
        $list_id = intval($list_id);
        $productLogic = new ProductLogic();
        $str = $productLogic->getShopAttrInput($aid, $typeid, $list_id);
        if (empty($str)) {
            $str = '<div style="font-size: 12px;text-align: center;">提示：该参数还没有参数值，若有需要请点击【<a href="'.url('Product/attribute_index', array('list_id'=>$list_id)).'">商品参数</a>】进行更多操作。</div>';
        }
        if (IS_AJAX) {
            exit($str);
        } else {
            return $str;
        }
    }

    /**
     * 动态获取商品参数输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajax_get_attr_input($typeid = '', $aid = '', $list_id = '')
    {
        $typeid = intval($typeid);
        $aid = intval($aid);
        $list_id = intval($list_id);
        $productLogic = new ProductLogic();
        $str = $productLogic->getAttrInput($aid, $typeid, $list_id);
        if (empty($str)) {
            $str = '<div style="font-size: 12px;text-align: center;">提示：该参数还没有参数值，若有需要请点击【<a href="'.url('Product/attribute_index', array('list_id'=>$list_id)).'">商品参数</a>】进行更多操作。</div>';
        }
        if (IS_AJAX) {
            exit($str);
        } else {
            return $str;
        }
    }

    /**
     * 发布商品
     */
    public function release()
    {
        $typeid = input('param.typeid/d', 0);
        if (0 < $typeid) {
            $param = input('param.');
            $row = Db::name('arctype')->field('current_channel')->find($typeid);
            /*针对不支持发布文档的模型*/
            if ($row['current_channel'] != 2) {
                $this->error('该栏目不支持发布商品！', url('ShopProduct/release'));
            }
            /*-----end*/

            $data = [
                'typeid'    => $typeid,
            ];
            $jumpUrl = url("ShopProduct/add", $data, true, true);
            header('Location: '.$jumpUrl);
            exit;
        }

        /*允许发布文档列表的栏目*/
        $select_html = allow_release_arctype(0, [2]);
        $this->assign('select_html',$select_html);
        /*--end*/

        return $this->fetch();
    }
}