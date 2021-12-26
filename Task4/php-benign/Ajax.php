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

namespace app\api\controller;

use think\Db;

class Ajax extends Base
{
    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
    }

    /**
     * 获取下级地区
     */
    public function get_region()
    {
        if (IS_AJAX) {
            $pid  = input('pid/d', 0);
            $res = Db::name('region')->where('parent_id',$pid)->select();
            $this->success('请求成功', null, $res);
        }
    }
    /**
     * 内容页浏览量的自增接口
     */
    public function arcclick()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        if (IS_AJAX) {
            $aid = input('aid/d', 0);
            $type = input('type/s', '');

            $click = 0;
            if (empty($aid)) {
                echo($click);
                exit;
            }

            if ($aid > 0) {
                $archives_db = Db::name('archives');
                if ('view' == $type) {
                    $archives_db->where(array('aid'=>$aid))->setInc('click'); 
                }
                $click = $archives_db->where(array('aid'=>$aid))->getField('click');
            }

            echo($click);
            exit;
        } else {
            abort(404);
        }
    }

    /**
     * 文档下载次数
     */
    public function downcount()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        if (IS_AJAX) {
            $aid = input('aid/d', 0);

            $downcount = 0;
            if (empty($aid)) {
                echo($downcount);
                exit;
            }

            if ($aid > 0) {
                $archives_db = Db::name('archives');
                $downcount = $archives_db->where(array('aid'=>$aid))->getField('downcount');
            }

            echo($downcount);
            exit;
        } else {
            abort(404);
        }
    }

    /**
     * arclist列表分页arcpagelist标签接口
     */
    public function arcpagelist()
    {
        if (!IS_AJAX) {
            abort(404);
        }

        $pnum = input('page/d', 0);
        $pagesize = input('pagesize/d', 0);
        $tagid = input('tagid/s', '');
        $tagidmd5 = input('tagidmd5/s', '');
        !empty($tagid) && $tagid = preg_replace("/[^a-zA-Z0-9-_]/",'', $tagid);
        !empty($tagidmd5) && $tagidmd5 = preg_replace("/[^a-zA-Z0-9_]/",'', $tagidmd5);

        if (empty($tagid) || empty($pnum) || empty($tagidmd5)) {
            $this->error('参数有误');
        }

        $data = [
            'code' => 1,
            'msg'   => '',
            'lastpage'  => 0,
        ];

        $arcmulti_db = Db::name('arcmulti');
        $arcmultiRow = $arcmulti_db->where(['tagid'=>$tagidmd5])->find();
        if(!empty($arcmultiRow) && !empty($arcmultiRow['querysql']))
        {
            // arcpagelist标签属性pagesize优先级高于arclist标签属性pagesize
            if (0 < intval($pagesize)) {
                $arcmultiRow['pagesize'] = $pagesize;
            }

            // 取出属性并解析为变量
            $attarray = unserialize(stripslashes($arcmultiRow['attstr']));
            // extract($attarray, EXTR_SKIP); // 把数组中的键名直接注册为了变量

            // 通过页面及总数解析当前页面数据范围
            $pnum < 2 && $pnum = 2;
            $strnum = intval($attarray['row']) + ($pnum - 2) * $arcmultiRow['pagesize'];

            // 拼接完整的SQL
            $querysql = preg_replace('#LIMIT(\s+)(\d+)(,\d+)?#i', '', $arcmultiRow['querysql']);
            $querysql = preg_replace('#SELECT(\s+)(.*)(\s+)FROM#i', 'SELECT COUNT(*) AS totalNum FROM', $querysql);
            $queryRow = Db::query($querysql);
            if (!empty($queryRow)) {
                $tpl_content = '';
                $filename = './template/'.THEME_STYLE_PATH.'/'.'system/arclist_'.$tagid.'.'.\think\Config::get('template.view_suffix');
                if (!file_exists($filename)) {
                    $data['code'] = -1;
                    $data['msg'] = "模板追加文件 arclist_{$tagid}.htm 不存在！";
                    $this->error("标签模板不存在", null, $data);
                } else {
                    $tpl_content = @file_get_contents($filename);
                }
                if (empty($tpl_content)) {
                    $data['code'] = -1;
                    $data['msg'] = "模板追加文件 arclist_{$tagid}.htm 没有HTML代码！";
                    $this->error("标签模板不存在", null, $data);
                }

                /*拼接完整的arclist标签语法*/
                $offset = intval($strnum);
                $row = intval($offset) + intval($arcmultiRow['pagesize']);
                $innertext = "{eyou:arclist";
                foreach ($attarray as $key => $val) {
                    if (in_array($key, ['tagid','offset','row'])) {
                        continue;
                    }
                    $innertext .= " {$key}='{$val}'";
                }
                $innertext .= " limit='{$offset},{$row}'}";
                $innertext .= $tpl_content;
                $innertext .= "{/eyou:arclist}";
                /*--end*/
                $msg = $this->display($innertext); // 渲染模板标签语法
                $data['msg'] = $msg;

                //是否到了最终页
                if (!empty($queryRow[0]['totalNum']) && $queryRow[0]['totalNum'] <= $row) {
                    $data['lastpage'] = 1;
                }

            } else {
                $data['lastpage'] = 1;
            }
        }

        $this->success('请求成功', null, $data);
    }

    /**
     * 获取表单令牌
     */
    public function get_token($name = '__token__')
    {
        if (IS_AJAX) {
            echo $this->request->token($name);
            exit;
        } else {
            abort(404);
        }
    }

    /**
     * 检验会员登录
     */
    public function check_user()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        if (IS_AJAX) {
            $type = input('param.type/s', 'default');
            $img = input('param.img/s');
            $users_id = session('users_id');
            if ('login' == $type) {
                if (!empty($users_id)) {
                    $currentstyle = input('param.currentstyle/s');
                    $users = M('users')->field('username,nickname,head_pic')
                        ->where([
                            'users_id'  => $users_id,
                            'lang'      => $this->home_lang,  
                        ])->find();
                    if (!empty($users)) {
                        $nickname = $users['nickname'];
                        if (empty($nickname)) {
                            $nickname = $users['username'];
                        }
                        $head_pic = get_head_pic($users['head_pic']);
                        if ('on' == $img) {
                            $users['html'] = "<img class='{$currentstyle}' alt='{$nickname}' src='{$head_pic}' />";
                        } else {
                            $users['html'] = $nickname;
                        }
                        $users['ey_is_login'] = 1;
                        setcookie('users_id', $users_id, null);
                        $this->success('请求成功', null, $users);
                    }
                }
                $this->success('请先登录', null, ['ey_is_login'=>0]);
            }
            else if ('reg' == $type)
            {
                if (!empty($users_id)) {
                    $users['ey_is_login'] = 1;
                } else {
                    $users['ey_is_login'] = 0;
                }
                $this->success('请求成功', null, $users);
            }
            else if ('logout' == $type)
            {
                if (!empty($users_id)) {
                    $users['ey_is_login'] = 1;
                } else {
                    $users['ey_is_login'] = 0;
                }
                $this->success('请求成功', null, $users);
            }
            else if ('cart' == $type)
            {
                if (!empty($users_id)) {
                    $users['ey_is_login'] = 1;
                    $users['ey_cart_num_20191212'] = Db::name('shop_cart')->where(['users_id'=>$users_id])->sum('product_num');
                } else {
                    $users['ey_is_login'] = 0;
                    $users['ey_cart_num_20191212'] = 0;
                }
                $this->success('请求成功', null, $users);
            }
            $this->error('访问错误');
        } else {
            abort(404);
        }
    }

    /**
     * 获取用户信息
     */
    public function get_tag_user_info()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        if (!IS_AJAX) {
            abort(404);
        }

        $t_uniqid = input('param.t_uniqid/s', '');
        if (IS_AJAX && !empty($t_uniqid)) {
            $users_id = session('users_id');
            if (!empty($users_id)) {
                $users = Db::name('users')->field('b.*, a.*')
                    ->alias('a')
                    ->join('__USERS_LEVEL__ b', 'a.level = b.level_id', 'LEFT')
                    ->where([
                        'a.users_id' => $users_id,
                        'a.lang'     => $this->home_lang,
                    ])->find();
                if (!empty($users)) {
                    $users['reg_time'] = MyDate('Y-m-d H:i:s', $users['reg_time']);
                    $users['update_time'] = MyDate('Y-m-d H:i:s', $users['update_time']);
                } else {
                    $users = [];
                    $tableFields1 = Db::name('users')->getTableFields();
                    $tableFields2 = Db::name('users_level')->getTableFields();
                    $tableFields = array_merge($tableFields1, $tableFields2);
                    foreach ($tableFields as $key => $val) {
                        $users[$val] = '';
                    }
                }
                $users['url'] = url('user/Users/centre');
                unset($users['password']);
                unset($users['paypwd']);
                $dtypes = [];
                foreach ($users as $key => $val) {
                    $html_key = md5($key.'-'.$t_uniqid);
                    $users[$html_key] = $val;

                    $dtype = 'txt';
                    if (in_array($key, ['head_pic'])) {
                        $dtype = 'img';
                    } else if (in_array($key, ['url'])) {
                        $dtype = 'href';
                    }
                    $dtypes[$html_key] = $dtype;

                    unset($users[$key]);
                }

                $data = [
                    'ey_is_login'   => 1,
                    'users'  => $users,
                    'dtypes'  => $dtypes,
                ];
                $this->success('请求成功', null, $data);
            }
            $this->success('请先登录', null, ['ey_is_login'=>0]);
        }
        $this->error('访问错误');
    }

    // 验证码获取
    public function vertify()
    {
        $time = getTime();
        $type = input('param.type/s', 'default');
        $token = input('param.token/s', '');
        $configList = \think\Config::get('captcha');
        $captchaArr = array_keys($configList);
        if (in_array($type, $captchaArr)) {
            /*验证码插件开关*/
            $admin_login_captcha = config('captcha.'.$type);
            $config = (!empty($admin_login_captcha['is_on']) && !empty($admin_login_captcha['config'])) ? $admin_login_captcha['config'] : config('captcha.default');
            /*--end*/
            ob_clean(); // 清空缓存，才能显示验证码
            $Verify = new \think\Verify($config);
            if (!empty($token)) {
                $Verify->entry($token);
            } else {
                $Verify->entry($type);
            }
        }
        exit();
    }
      
    /**
     * 邮箱发送
     */
    public function send_email()
    {
        // 超时后，断掉邮件发送
        function_exists('set_time_limit') && set_time_limit(10);

        $type = input('param.type/s');
        
        // 留言发送邮件
        if (IS_AJAX_POST && 'gbook_submit' == $type) {
            $tid = input('param.tid/d');
            $aid = input('param.aid/d');

            $send_email_scene = config('send_email_scene');
            $scene = $send_email_scene[1]['scene'];

            $web_name = tpCache('web.web_name');
            // 判断标题拼接
            $arctype  = M('arctype')->field('typename')->find($tid);
            $web_name = $arctype['typename'].'-'.$web_name;

            // 拼装发送的字符串内容
            $row = M('guestbook_attribute')->field('a.attr_name, b.attr_value')
                ->alias('a')
                ->join('__GUESTBOOK_ATTR__ b', 'a.attr_id = b.attr_id AND a.typeid = '.$tid, 'LEFT')
                ->where([
                    'b.aid' => $aid,
                ])
                ->order('a.attr_id sac')
                ->select();
            $content = '';
            foreach ($row as $key => $val) {
                if (preg_match('/(\.(jpg|gif|png|bmp|jpeg|ico|webp))$/i', $val['attr_value'])) {
                    if (!stristr($val['attr_value'], '|')) {
                        $val['attr_value'] = $this->request->domain().handle_subdir_pic($val['attr_value']);
                        $val['attr_value'] = "<a href='".$val['attr_value']."' target='_blank'><img src='".$val['attr_value']."' width='150' height='150' /></a>";
                    }
                } else {
                    $val['attr_value'] = str_replace(PHP_EOL, ' | ', $val['attr_value']);
                }
                $content .= $val['attr_name'] . '：' . $val['attr_value'].'<br/>';
            }
            $html = "<p style='text-align: left;'>{$web_name}</p><p style='text-align: left;'>{$content}</p>";
            if (isMobile()) {
                $html .= "<p style='text-align: left;'>——来源：移动端</p>";
            } else {
                $html .= "<p style='text-align: left;'>——来源：电脑端</p>";
            }
            
            // 发送邮件
            $res = send_email(null,null,$html, $scene);
            if (intval($res['code']) == 1) {
                $this->success($res['msg']);
            } else {
                $this->error($res['msg']);
            }
        }
    }

    /**
     * 手机短信发送
     */
    public function SendMobileCode()
    {
        // 超时后，断掉发送
        function_exists('set_time_limit') && set_time_limit(5);

        // 发送手机验证码
        if (IS_AJAX_POST) {
            $post = input('post.');
            $source = !empty($post['source']) ? $post['source'] : 0;
            if (5 == $post['scene']) {
                if (empty($post['mobile'])) return false;
                /*发送并返回结果*/
                $data = $post['data'];
                switch ($data['type']) {
                    case '1':
                        $content = '您有新的待发货订单';
                        break;
                    case '2':
                        $content = '您有新的待收货订单';
                        break;
                }
                $Result = sendSms($post['scene'], $post['mobile'], array('content'=>$content, 'code'=>mt_rand(100000,999999)));
                if (intval($Result['status']) == 1) {
                    $this->success('发送成功！');
                } else {
                    $this->error($Result['msg']);
                }
                /* END */
            } else {
                $mobile = !empty($post['mobile']) ? $post['mobile'] : session('mobile');
                $is_mobile = !empty($post['is_mobile']) ? $post['is_mobile'] : false;
                if (empty($mobile)) $this->error('请先绑定手机号码');
                if ('true' === $is_mobile) {
                    /*是否存在手机号码*/
                    $where = [
                        'mobile' => $mobile
                    ];
                    $users_id = session('users_id');
                    if (!empty($users_id)) $where['users_id'] = ['NEQ', $users_id];
                    $Result = Db::name('users')->where($where)->count();
                    /* END */
                    if (0 == $post['source']) {
                        if (!empty($Result)) $this->error('手机号码已注册');
                    } else if (4 == $post['source']) {
                        if (empty($Result)) $this->error('手机号码不存在');
                    } else {
                        if (!empty($Result)) $this->error('手机号码已存在');
                    }
                }

                /*是否允许再次发送*/
                $where = [
                    'mobile'   => $mobile,
                    'source'   => $source,
                    'status'   => 1,
                    'is_use'   => 0,
                    'add_time' => ['>', getTime() - 120]
                ];
                $Result = Db::name('sms_log')->where($where)->order('id desc')->count();
                if (!empty($Result)) $this->error('120秒内只能发送一次！');
                /* END */

                /*图形验证码判断*/
                if (!empty($post['IsVertify'])) {
                    if (empty($post['vertify'])) $this->error('请输入图形验证码！');
                    $verify = new \think\Verify();
                    if (!$verify->check($post['vertify'], $post['type'])) $this->error('图形验证码错误！');
                }
                /* END */

                /*发送并返回结果*/
                $Result = sendSms($source, $mobile, array('content' => mt_rand(100000, 999999)));
                if (intval($Result['status']) == 1) {
                    $this->success('发送成功！');
                } else {
                    $this->error($Result['msg']);
                }
                /* END */
            }
        }
    }

    // 判断文章内容阅读权限
    public function get_arcrank($aid = '', $vars = '')
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $aid = intval($aid);
        $vars = intval($vars);
        if ((IS_AJAX || !empty($vars)) && !empty($aid)) {
            // 用户ID
            $users_id = session('users_id');
            // 文章查看所需等级值
            $Arcrank = M('archives')->alias('a')
                ->field('a.users_id, a.arcrank, b.level_value, b.level_name')
                ->join('__USERS_LEVEL__ b', 'a.arcrank = b.level_value', 'LEFT')
                ->where(['a.aid' => $aid])
                ->find();

            if (!empty($users_id)) {
                // 会员级别等级值
                $UsersDataa = Db::name('users')->alias('a')
                    ->field('a.users_id,b.level_value,b.level_name')
                    ->join('__USERS_LEVEL__ b', 'a.level = b.level_id', 'LEFT')
                    ->where(['a.users_id'=>$users_id])
                    ->find();
                if (0 == $Arcrank['arcrank']) {
                    if (IS_AJAX) {
                        $this->success('允许查阅！');
                    } else {
                        return true;
                    }
                }else if (-1 == $Arcrank['arcrank']) {
                    if ($users_id == $Arcrank['users_id']) {
                        if (IS_AJAX) {
                            $this->success('允许查阅！');
                        } else {
                            return true;
                        }
                    }else{
                        $msg = '待审核稿件，你没有权限阅读！';
                    }
                }else if ($UsersDataa['level_value'] < $Arcrank['level_value']) {
                    $msg = '内容需要【'.$Arcrank['level_name'].'】才可以查看，您为【'.$UsersDataa['level_name'].'】，请先升级！';
                }else{
                    if (IS_AJAX) {
                        $this->success('允许查阅！');
                    } else {
                        return true;
                    }
                }
                if (IS_AJAX) {
                    $this->error($msg);
                } else {
                    return $msg;
                }
            }else{
                if (0 == $Arcrank['arcrank']) {
                    if (IS_AJAX) {
                        $this->success('允许查阅！');
                    } else {
                        return true;
                    }
                }else if (-1 == $Arcrank['arcrank']) {
                    $msg = '待审核稿件，你没有权限阅读！';
                }else if (!empty($Arcrank['level_name'])) {
                    $msg = '文章需要【'.$Arcrank['level_name'].'】才可以查看，游客不可查看，请登录！';
                }else{
                    $msg = '游客不可查看，请登录！';
                }
                if (IS_AJAX) {
                    $this->error($msg);
                } else {
                    return $msg;
                }
            }
        } else {
            abort(404);
        }
    }

    /**
     * 获取会员列表
     * @author 小虎哥 by 2018-4-20
     */
    public function get_tag_memberlist()
    {
        $this->error('暂时没用上！');
        if (IS_AJAX_POST) {
            $htmlcode = input('post.htmlcode/s');
            $htmlcode = htmlspecialchars_decode($htmlcode);
            $htmlcode = preg_replace('/<\?(\s*)php(\s+)/i', '', $htmlcode);

            $attarray = input('post.attarray/s');
            $attarray = htmlspecialchars_decode($attarray);
            $attarray = json_decode(base64_decode($attarray));

            /*拼接完整的memberlist标签语法*/
            $eyou = new \think\template\taglib\Eyou('');
            $tagsList = $eyou->getTags();
            $tagsAttr = $tagsList['memberlist'];
            
            $innertext = "{eyou:memberlist";
            foreach ($attarray as $key => $val) {
                if (!in_array($key, $tagsAttr) || in_array($key, ['js'])) {
                    continue;
                }
                $innertext .= " {$key}='{$val}'";
            }
            $innertext .= " js='on'}";
            $innertext .= $htmlcode;
            $innertext .= "{/eyou:memberlist}";
            /*--end*/
            $msg = $this->display($innertext); // 渲染模板标签语法
            $data['msg'] = $msg;

            $this->success('读取成功！', null, $data);
        }
        $this->error('加载失败！');
    }

    /**
     * 发布或编辑文档时，百度自动推送
     */
    public function push_zzbaidu($url = '', $type = 'add')
    {
        $msg = '百度推送URL失败！';
        if (IS_AJAX_POST) {
            \think\Session::pause(); // 暂停session，防止session阻塞机制

            // 获取token的值：http://ziyuan.baidu.com/linksubmit/index?site=http://www.eyoucms.com/
            $sitemap_zzbaidutoken = config('tpcache.sitemap_zzbaidutoken');
            if (empty($sitemap_zzbaidutoken)) {
                $this->error('尚未配置实时推送Url的token！', null, ['code'=>0]);
            } else if (!function_exists('curl_init')) {
                $this->error('请开启php扩展curl_init', null, ['code'=>1]);
            }

            $urlsArr[] = $url;
            $type = ('edit' == $type) ? 'update' : 'urls';

            if (is_http_url($sitemap_zzbaidutoken)) {
                $searchs = ["/urls?","/update?"];
                $replaces = ["/{$type}?", "/{$type}?"];
                $api = str_replace($searchs, $replaces, $sitemap_zzbaidutoken);
            } else {
                $api = 'http://data.zz.baidu.com/'.$type.'?site='.$this->request->host(true).'&token='.trim($sitemap_zzbaidutoken);
            }

            $ch = curl_init();
            $options =  array(
                CURLOPT_URL => $api,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => implode("\n", $urlsArr),
                CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
            );
            curl_setopt_array($ch, $options);
            $result = curl_exec($ch);
            !empty($result) && $result = json_decode($result, true);
            if (!empty($result['success'])) {
                $this->success('百度推送URL成功！');
            } else {
                $msg = !empty($result['message']) ? $result['message'] : $msg;
            }
        }

        $this->error($msg);
    }

    // 视频权限播放逻辑
    public function video_logic()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $post = input('post.');
        if (IS_AJAX_POST && !empty($post['aid'])) {

            // 查询文档信息 
            $field = 'a.aid, a.typeid, a.channel, a.users_price, a.users_free, b.level_id, b.level_name, b.level_value,a.arc_level_id';
            $where = [
                'a.aid' => $post['aid'],
                'a.is_del' => 0
            ];
            $archivesInfo = Db::name('archives')
                ->alias('a')
                ->field($field)
                ->join('__USERS_LEVEL__ b', 'a.arc_level_id = b.level_id', 'LEFT')
                ->where($where)
                ->find();

            if (5 == $archivesInfo['channel']) {
                // 获取用户最新信息
                $UsersData = GetUsersLatestData();
                $UsersID = $UsersData['users_id'];

                // 初始化数据
                $result['BuyOnclick'] = 'MediaOrderBuy_1592878548();';
                $result['BuyName']    = '立即购买';
                $result['MsgOnclick'] = 'MediaOrderBuy_1592878548();';
                $result['MsgTitle']   = '尊敬的用户，该视频需要付费后才可观看全部内容';
                $result['MsgName']    = '立即购买';
                $result['VideoButton'] = '购买观看';
                $result['DelVideoUrl'] = 1;

                /*是否需要付费*/
                if (!empty($archivesInfo['users_price']) && 0 < $archivesInfo['users_price']) {
                    $Paid = 0; // 未付费
                    if (!empty($UsersID)) {
                        $where = [
                            'users_id' => $UsersID,
                            'product_id' => $post['aid'],
                            'order_status' => 1
                        ];
                        // 存在数据则已付费
                        $Paid = Db::name('media_order')->where($where)->count();
                    }

                    // 未付费则执行
                    if (!empty($Paid)) {
                        $result['BuyOnclick'] = '';
                        $result['BuyName']    = '已付费';
                        $result['MsgOnclick'] = '';
                        $result['MsgTitle']   = '';
                        $result['MsgName']    = '';
                        $result['VideoButton'] = '开始播放';
                        $result['DelVideoUrl'] = 0;
                    }
                } else {
                    $result['BuyOnclick'] = '';
                    $result['BuyName']    = '免费观看';
                    $result['MsgOnclick'] = '';
                    $result['MsgTitle']   = '';
                    $result['MsgName']    = '';
                    $result['VideoButton'] = '开始播放';
                    $result['DelVideoUrl'] = 0;
                }
                /*END*/
                /**注册会员免费但是没有登录*/
                if (!empty($archivesInfo['arc_level_id']) && empty($UsersData['level_value'])) {
                    $this->error('请先登录', url('user/Users/login'));
                }

                /*是否会员免费*/
                if (1 == $archivesInfo['users_free'] && !empty($UsersData['level_value'])) {
                    $where = [
                        'is_system' => 1,
                        'lang' => $this->home_lang
                    ];
                    $UsersLevel = DB::name('users_level')->where($where)->getField('level_value');
                    if (!empty($UsersLevel) && $UsersData['level_value'] > $UsersLevel) {
                        $result['MsgOnclick'] = $result['MsgTitle'] = $result['MsgName'] = '';
                        $result['VideoButton'] = '开始播放';
                        $result['DelVideoUrl'] = 0;
                    }
                } else if (!empty($archivesInfo['level_value']) && !empty($UsersData['level_value'])) {
                    if ($UsersData['level_value'] >= $archivesInfo['level_value']) {
                        $result['BuyOnclick'] = $result['MsgOnclick'] = $result['MsgTitle'] = $result['MsgName'] = '';
                        $result['BuyName']    = '会员免费';
                        $result['VideoButton'] = '开始播放';
                        $result['DelVideoUrl'] = 0;
                    } else if (!empty($archivesInfo['level_value']) && 0 >= $archivesInfo['users_price']) {
                        $result['BuyOnclick'] = 'LevelCentre_1592878548();';
                        $result['BuyName']    = '升级会员';
                        $result['MsgOnclick'] = 'LevelCentre_1592878548();';
                        $result['MsgTitle']   = '尊敬的用户，该视频需要付费后才可观看全部内容';
                        $result['MsgName']    = '升级会员';
                        $result['VideoButton'] = '升级观看';
                        $result['DelVideoUrl'] = 1;
                    }
                }
                /*END*/

                $this->success('查询成功', null, $result);
            } else {
                $this->error('非视频模型的文档！');
            }
        }
        abort(404);
    }
}