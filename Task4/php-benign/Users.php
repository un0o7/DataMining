<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 陈风任 <491085389@qq.com>
 * Date: 2019-1-25
 */

namespace app\user\controller;

use think\Db;
use think\Config;
use think\Verify;
use app\user\logic\SmtpmailLogic;

class Users extends Base
{
    public $smtpmailLogic;

    public function _initialize()
    {
        parent::_initialize();
        $this->smtpmailLogic      = new SmtpmailLogic;
        $this->users_db           = Db::name('users');      // 会员数据表
        $this->users_level_db     = Db::name('users_level'); // 会员等级表
        $this->users_parameter_db = Db::name('users_parameter'); // 会员属性表
        $this->users_list_db      = Db::name('users_list'); // 会员属性信息表
        $this->users_config_db    = Db::name('users_config');// 会员配置表
        $this->users_money_db     = Db::name('users_money');// 会员金额明细表
        $this->smtp_record_db     = Db::name('smtp_record');// 发送邮箱记录表
        $this->sms_log_db         = Db::name('sms_log');// 发送手机记录表
        // 微信配置信息
        $this->pay_wechat_config = unserialize(getUsersConfigData('pay.pay_wechat_config'));
    }

    // 会员中心首页
    public function index()
    {
        $result = [];
        // 资料信息
        $result['users_para'] = model('Users')->getDataParaList($this->users_id);
        $this->assign('users_para', $result['users_para']);

        // 菜单名称
        $result['title'] = Db::name('users_menu')->where([
            'mca'  => 'user/Users/index',
            'lang' => $this->home_lang,
        ])->getField('title');

        $eyou = array(
            'field' => $result,
        );
        $this->assign('eyou', $eyou);

        $html = $this->fetch('users_centre');

        /*第三方注册的用户，无需修改登录密码*/
        if (!empty($this->users['thirdparty'])) {
            $html = str_ireplace('onclick="ChangePwdMobile();"', 'onclick="ChangePwdMobile();" style="display: none;"', $html);
            $html = str_ireplace('onclick="ChangePwd();"', 'onclick="ChangePwd();" style="display: none;"', $html);
        }
        /*end*/
        // 美化昵称输入框
        $html = str_ireplace('type="text" name="nickname"', 'type="text" name="nickname" class="input-txt"', $html);

        return $html;
    }

    // 会员选择登陆方式界面
    public function users_select_login()
    {
        // 若存在则调转至会员中心
        if ($this->users_id > 0) {
            $this->redirect('user/Users/centre');
            exit;
        }
        // 跳转链接
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url("user/Users/centre");
        session('eyou_referurl', $referurl);

        // 拼装url
        $result = [
            'wechat_url'  => url("user/Users/ajax_wechat_login"),
            'website_url' => $this->root_dir . "/index.php?m=user&c=Users&a=login&website=website",
        ];

        // 若为微信端并且开启微商城模式则重定向
        if (isWeixin() && !empty($this->usersConfig['shop_micro'])) {
            $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
            if (!empty($WeChatLoginConfig)) {
                $this->redirect($result['wechat_url']);
            }
        }

        // 若后台功能设置-登录设置中，微信端本站登录为关闭状态，则直接跳转到微信授权页面
        if (isset($this->usersConfig['users_open_website_login']) && empty($this->usersConfig['users_open_website_login'])) {
            $this->redirect($result['wechat_url']);
            exit;
        }

        // 数据加载
        $eyou = array(
            'field' => $result,
        );
        $this->assign('eyou', $eyou);
        return $this->fetch('users_select_login');
    }

    // 使用ajax微信授权登陆
    public function ajax_wechat_login()
    {
        $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
        // 微信授权登陆
        if (!empty($WeChatLoginConfig['appid']) && !empty($WeChatLoginConfig['appsecret'])) {
            if (isMobile() && isWeixin()) {
                // 判断登陆成功跳转的链接，若为空则默认会员中心链接并存入session
                $referurl = session('eyou_referurl');
                if (empty($referurl)) {
                    $referurl = url('user/Users/index', '', true, true);
                    session('eyou_referurl', $referurl);
                }

                // 获取微信配置授权登陆
                $appid     = $WeChatLoginConfig['appid'];
                $NewUrl    = urlencode(url('user/Users/get_wechat_info', '', true, true));
                $ReturnUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appid . "&redirect_uri=" . $NewUrl . "&response_type=code&scope=snsapi_userinfo&state=eyoucms&#wechat_redirect";

                if (isset($this->usersConfig['users_open_website_login']) && empty($this->usersConfig['users_open_website_login'])) {
                    $this->redirect($ReturnUrl);
                    exit;
                } else {
                    $this->success('授权成功！', $ReturnUrl);
                }
            }
            $this->error('非手机端微信、小程序，不可以使用微信登陆，请选择本站登陆！');
        }
        $this->error('后台微信配置尚未配置AppSecret，不可以微信登陆，请选择本站登陆！');

    }

    // 授权之后，获取会员信息
    public function get_wechat_info()
    {
        $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];

        // 微信配置信息
        $appid  = $WeChatLoginConfig['appid'];
        $secret = $WeChatLoginConfig['appsecret'];
        $code   = input('param.code/s');

        // 获取到会员openid
        $get_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid . '&secret=' . $secret . '&code=' . $code . '&grant_type=authorization_code';
        $data          = httpRequest($get_token_url);
        $WeChatData    = json_decode($data, true);
        if (empty($WeChatData) || (!empty($WeChatData['errcode']) && !empty($WeChatData['errmsg']))) {
            $this->error('AppSecret错误或已过期', $this->root_dir.'/');
        }
        
        // 查询这个openid是否已注册
        $where = [
            'open_id' => $WeChatData['openid'],
            'lang'    => $this->home_lang,
        ];
        $Users = $this->users_db->where($where)->find();
        if (!empty($Users)) {
            // 已注册
            session('users_id', $Users['users_id']);
            session('users', $Users);
            setcookie('users_id', $Users['users_id'], null);
            $this->redirect(session('eyou_referurl'));
        } else {
            // 未注册
            $username = substr($WeChatData['openid'], 6, 8);
            // 查询用户名是否已存在
            $result = $this->users_db->where('username', $username)->count();
            if (!empty($result)) {
                $username = $username . rand('100,999');
            }
            // 获取会员信息
            $get_userinfo = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $WeChatData["access_token"] . '&openid=' . $WeChatData["openid"] . '&lang=zh_CN';
            $UserInfo     = httpRequest($get_userinfo);
            $UserInfo     = json_decode($UserInfo, true);
            if (empty($UserInfo['nickname']) && empty($UserInfo['headimgurl'])) {
                $this->error('用户授权异常，建议清理手机缓存再进行登录', $this->root_dir.'/');
            }

            // 新增会员和微信绑定
            $UsersData = [
                'username'       => $username,
                'nickname'       => $UserInfo['nickname'],
                'open_id'        => $WeChatData['openid'],
                'password'       => '', // 密码默认为空
                'last_ip'        => clientIP(),
                'reg_time'       => getTime(),
                'last_login'     => getTime(),
                'is_activation'  => 1, // 微信注册会员，默认开启激活
                'register_place' => 2, // 前台微信注册会员
                'login_count'    => Db::raw('login_count+1'),
                'head_pic'       => $UserInfo['headimgurl'],
                'lang'           => $this->home_lang,
            ];
            // 查询默认会员级别，存入会员表
            $level_id           = $this->users_level_db->where([
                'is_system' => 1,
                'lang'      => $this->home_lang,
            ])->getField('level_id');
            $UsersData['level'] = $level_id;

            $users_id = $this->users_db->add($UsersData);
            if (!empty($users_id)) {
                // 新增成功，将会员信息存入session
                $GetUsers = $this->users_db->where('users_id', $users_id)->find();
                session('users_id', $GetUsers['users_id']);
                session('users', $GetUsers);
                setcookie('users_id', $GetUsers['users_id'], null);
                $this->redirect(session('eyou_referurl'));
            } else {
                $this->error('未知错误，无法继续！');
            }
        }
    }

    // 登陆
    public function login()
    {
        // 若已登录则重定向
        if ($this->users_id > 0) {
            $this->redirect('user/Users/centre');
            exit;
        }
        
        // 若为微信端并且开启微商城模式则重定向
        if (isWeixin() && !empty($this->usersConfig['shop_micro'])) {
            $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
            if (!empty($WeChatLoginConfig)) {
                $this->redirect('user/Users/ajax_wechat_login');
            }
        }

        // 若为微信端则重定向
        $website = input('param.website/s');
        if (isWeixin() && empty($website)) {
            $this->redirect('user/Users/users_select_login');
            exit;
        }

        // 默认开启验证码
        $is_vertify          = 1;
        $users_login_captcha = config('captcha.users_login');
        if (!function_exists('imagettftext') || empty($users_login_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }
        $this->assign('is_vertify', $is_vertify);

        if (IS_AJAX_POST) {
            $post             = input('post.');
            $post['username'] = trim($post['username']);

            if (empty($post['username'])) {
                $this->error('用户名不能为空！', null, ['status' => 1]);
            } else if (!preg_match("/^[\x{4e00}-\x{9fa5}\w\-\_\@\#]{2,30}$/u", $post['username'])) {
                $this->error('用户名不正确！', null, ['status' => 1]);
            }

            if (empty($post['password'])) {
                $this->error('密码不能为空！', null, ['status' => 1]);
            }

            if (1 == $is_vertify) {
                if (empty($post['vertify'])) {
                    $this->error('图片验证码不能为空！', null, ['status' => 1]);
                }
            }

            $users = $this->users_db->where([
                'username' => $post['username'],
                'is_del'   => 0,
                'lang'     => $this->home_lang,
            ])->find();
            if (!empty($users)) {
                if (!empty($users['admin_id'])) {
                    // 后台账号不允许在前台通过账号密码登录，只能后台登录时同步到前台
                    $this->error('前台禁止管理员登录！', null, ['status' => 1]);
                }

                if (empty($users['is_activation'])) {
                    $this->error('该会员尚未激活，请联系管理员！', null, ['status' => 1]);
                }

                $users_id = $users['users_id'];
                if (strval($users['password']) === strval(func_encrypt($post['password']))) {

                    // 处理判断验证码
                    if (1 == $is_vertify) {
                        $verify = new Verify();
                        if (!$verify->check($post['vertify'], "users_login")) {
                            $this->error('验证码错误', null, ['status' => 'vertify']);
                        }
                    }

                    // 判断是前台还是后台注册的会员，后台注册不受注册验证影响，1为后台注册，2为前台注册。
                    if (2 == $users['register_place']) {
                        $usersVerificationRow = M('users_config')->where([
                            'name' => 'users_verification',
                            'lang' => $this->home_lang,
                        ])->find();
                        if ($usersVerificationRow['update_time'] <= $users['reg_time']) {
                            // 判断是否需要后台审核
                            if ($usersVerificationRow['value'] == 1 && $users['is_activation'] == 0) {
                                $this->error('管理员审核中，请稍等！', null, ['status' => 2]);
                            }
                        }
                    }

                    // 会员users_id存入session
                    model('EyouUsers')->loginAfter($users);

                    // 回跳路径
                    $url = input('post.referurl/s', null, 'htmlspecialchars_decode,urldecode');
                    $this->success('登录成功', $url);
                } else {
                    $this->error('密码不正确！', null, ['status' => 1]);
                }
            } else {
                $this->error('该用户名不存在，请注册！', null, ['status' => 1]);
            }
        }

        /*微信登录插件 - 判断是否显示微信登录按钮*/
        $weapp_wxlogin = 0;
        if (is_dir('./weapp/WxLogin/')) {
            $wx         = Db::name('weapp')->field('data,status,config')->where(['code' => 'WxLogin'])->find();
            $wx['data'] = unserialize($wx['data']);
            if ($wx['status'] == 1 && $wx['data']['login_show'] == 1) {
                $weapp_wxlogin = 1;
            }
            // 使用场景 0 PC+手机 1 手机 2 PC
            $wx['config'] = json_decode($wx['config'], true);
            if (isMobile() && !in_array($wx['config']['scene'], [0,1])) {
                $weapp_wxlogin = 0;
            } else if (!isMobile() && !in_array($wx['config']['scene'], [0,2])) {
                $weapp_wxlogin = 0;
            }
        }
        $this->assign('weapp_wxlogin', $weapp_wxlogin);
        /*end*/

        /*QQ登录插件 - 判断是否显示QQ登录按钮*/
        $weapp_qqlogin = 0;
        if (is_dir('./weapp/QqLogin/')) {
            $qq         = Db::name('weapp')->field('data,status,config')->where(['code' => 'QqLogin'])->find();
            $qq['data'] = unserialize($qq['data']);
            if ($qq['status'] == 1 && $qq['data']['login_show'] == 1) {
                $weapp_qqlogin = 1;
            }
            // 使用场景 0 PC+手机 1 手机 2 PC
            $qq['config'] = json_decode($qq['config'], true);
            if (isMobile() && !in_array($qq['config']['scene'], [0,1])) {
                $weapp_qqlogin = 0;
            } else if (!isMobile() && !in_array($qq['config']['scene'], [0,2])) {
                $weapp_qqlogin = 0;
            }
        }
        $this->assign('weapp_qqlogin', $weapp_qqlogin);
        /*end*/

        /*微博插件 - 判断是否显示微博按钮*/
        $weapp_wblogin = 0;
        if (is_dir('./weapp/Wblogin/')) {
            $wb         = Db::name('weapp')->field('data,status,config')->where(['code' => 'Wblogin'])->find();
            $wb['data'] = unserialize($wb['data']);
            if ($wb['status'] == 1 && $wb['data']['login_show'] == 1) {
                $weapp_wblogin = 1;
            }
            // 使用场景 0 PC+手机 1 手机 2 PC
            $wb['config'] = json_decode($wb['config'], true);
            if (isMobile() && !in_array($wb['config']['scene'], [0,1])) {
                $weapp_wblogin = 0;
            } else if (!isMobile() && !in_array($wb['config']['scene'], [0,2])) {
                $weapp_wblogin = 0;
            }
        }
        $this->assign('weapp_wblogin', $weapp_wblogin);
        /*end*/

        // 跳转链接
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url("user/Users/centre");
        cookie('referurl', $referurl);
        $this->assign('referurl', $referurl);
        return $this->fetch('users_login');
    }

    // 会员注册
    public function reg()
    {
        if ($this->users_id > 0) {
            $this->redirect('user/Users/centre');
            exit;
        }

        $is_vertify        = 1; // 默认开启验证码
        $users_reg_captcha = config('captcha.users_reg');
        if (!function_exists('imagettftext') || empty($users_reg_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }
        $this->assign('is_vertify', $is_vertify);

        if (IS_AJAX_POST) {
            $post             = input('post.');
            $post['username'] = trim($post['username']);

            $users_reg_notallow = explode(',', getUsersConfigData('users.users_reg_notallow'));
            if (!empty($users_reg_notallow)) {
                if (in_array($post['username'], $users_reg_notallow)) {
                    $this->error('用户名为系统禁止注册！', null, ['status' => 1]);
                }
            }

            if (empty($post['username'])) {
                $this->error('用户名不能为空！', null, ['status' => 1]);
            } else if (!preg_match("/^[\x{4e00}-\x{9fa5}\w\-\_\@\#]{2,30}$/u", $post['username'])) {
                $this->error('请输入2-30位的汉字、英文、数字、下划线等组合', null, ['status' => 1]);
            }

            if (empty($post['password'])) {
                $this->error('登录密码不能为空！', null, ['status' => 1]);
            }

            if (empty($post['password2'])) {
                $this->error('重复密码不能为空！', null, ['status' => 1]);
            }

            if (1 == $is_vertify) {
                if (empty($post['vertify'])) {
                    $this->error('图片验证码不能为空！', null, ['status' => 1]);
                }
            }

            $count = $this->users_db->where([
                'username' => $post['username'],
                'lang'     => $this->home_lang,
            ])->count();
            if (!empty($count)) {
                $this->error('用户名已存在！', null, ['status' => 1]);
            }

            if (empty($post['password']) && empty($post['password2'])) {
                $this->error('登录密码不能为空！', null, ['status' => 1]);
            } else {
                if ($post['password'] != $post['password2']) {
                    $this->error('两次密码输入不一致！', null, ['status' => 1]);
                }
            }

            // 处理会员属性数据
            $ParaData = [];
            if (is_array($post['users_'])) {
                $ParaData = $post['users_'];
            }
            unset($post['users_']);

            // 处理提交的会员属性中必填项是否为空
            // 必须传入提交的会员属性数组
            $EmptyData = model('Users')->isEmpty($ParaData, 'reg');
            if (!empty($EmptyData)) {
                $this->error($EmptyData, null, ['status' => 1]);
            }

            // 处理提交的会员属性中邮箱和手机是否已存在
            // IsRequired方法传入的参数有2个
            // 第一个必须传入提交的会员属性数组
            // 第二个users_id，注册时不需要传入，修改时需要传入。
            $RequiredData = model('Users')->isRequired($ParaData, 'reg');
            if (!empty($RequiredData) && !is_array($RequiredData)) {
                $this->error($RequiredData, null, ['status' => 1]);
            }

            // 处理判断验证码
            if (1 == $is_vertify) {
                $verify = new Verify();
                if (!$verify->check($post['vertify'], "users_reg")) {
                    $this->error('图片验证码错误', null, ['status' => 'vertify']);
                }
            }

            if (!empty($RequiredData['email'])) {
                // 查询会员输入的邮箱并且为找回密码来源的所有验证码
                $RecordWhere = [
                    'source'   => 2,
                    'email'    => $RequiredData['email'],
                    'users_id' => 0,
                    'status'   => 0,
                    'lang'     => $this->home_lang,
                ];
                $RecordData  = [
                    'status'      => 1,
                    'update_time' => getTime(),
                ];
                // 更新数据
                $this->smtp_record_db->where($RecordWhere)->update($RecordData);
            }

            if (!empty($RequiredData['mobile'])) {
                // 查询会员输入的邮箱并且为找回密码来源的所有验证码
                $RecordWhere = [
                    'source' => 0,
                    'mobile' => $RequiredData['mobile'],
                    'is_use' => 0,
                    'lang'   => $this->home_lang
                ];
                $RecordData  = [
                    'is_use' => 1,
                    'update_time' => getTime()
                ];
                // 更新数据
                $this->sms_log_db->where($RecordWhere)->update($RecordData);
            }

            // 会员设置
            $users_verification = !empty($this->usersConfig['users_verification']) ? $this->usersConfig['users_verification'] : 0;

            // 处理判断是否为后台审核，verification=1为后台审核。
            if (1 == $users_verification) $data['is_activation'] = 0;

            // 添加会员到会员表
            $data['username']       = $post['username'];
            $data['nickname']       = !empty($post['nickname']) ? $post['nickname'] : $post['username'];
            $data['password']       = func_encrypt($post['password']);
            $data['is_mobile']      = !empty($ParaData['mobile_1']) ? 1 : 0;
            $data['is_email']       = !empty($ParaData['email_2']) ? 1 : 0;
            $data['last_ip']        = clientIP();
            $data['head_pic']       = ROOT_DIR . '/public/static/common/images/dfboy.png';
            $data['reg_time']       = getTime();
            $data['last_login']     = getTime();
            $data['register_place'] = 2;  // 注册位置，后台注册不受注册验证影响，1为后台注册，2为前台注册。
            $data['lang']           = $this->home_lang;

            $level_id      = $this->users_level_db->where([
                'is_system' => 1,
                'lang'      => $this->home_lang,
            ])->getField('level_id');
            $data['level'] = $level_id;

            $users_id = $this->users_db->add($data);

            // 判断会员是否添加成功
            if (!empty($users_id)) {
                // 批量添加会员属性到属性信息表
                if (!empty($ParaData)) {
                    $betchData    = [];
                    $usersparaRow = $this->users_parameter_db->where([
                        'lang'      => $this->home_lang,
                        'is_hidden' => 0,
                    ])->getAllWithIndex('name');
                    foreach ($ParaData as $key => $value) {
                        if (preg_match('/_code$/i', $key)) {
                            continue;
                        }

                        // 若为数组，则拆分成字符串
                        if (is_array($value)) $value = implode(',', $value);
                        
                        $para_id     = intval($usersparaRow[$key]['para_id']);
                        $betchData[] = [
                            'users_id' => $users_id,
                            'para_id'  => $para_id,
                            'info'     => $value,
                            'lang'     => $this->home_lang,
                            'add_time' => getTime(),
                        ];
                    }
                    $this->users_list_db->insertAll($betchData);
                }

                // 查询属性表的手机号码和邮箱地址,拼装数组$UsersListData
                $UsersListData                = model('Users')->getUsersListData('*', $users_id);
                $UsersListData['login_count'] = 1;
                $UsersListData['update_time'] = getTime();
                if (2 == $users_verification) {
                    // 若开启邮箱验证并且通过邮箱验证则绑定到会员
                    $UsersListData['is_email'] = 1;
                } else if (3 == $users_verification) {
                    // 若开启手机验证并且通过手机验证则绑定到会员
                    $UsersListData['is_mobile'] = 1;
                }
                // 同步修改会员信息
                $this->users_db->where('users_id', $users_id)->update($UsersListData);

                session('users_id', $users_id);
                if (session('users_id')) {
                    setcookie('users_id', $users_id, null);
                    if (empty($users_verification)) {
                        // 无需审核，直接登陆
                        $url = url('user/Users/centre');
                        $this->success('注册成功！', $url, ['status' => 3]);
                    } else if (1 == $users_verification) {
                        // 需要后台审核
                        session('users_id', null);
                        $url = url('user/Users/login');
                        $this->success('注册成功，等管理员激活才能登录！', $url, ['status' => 2]);
                    } else if (2 == $users_verification) {
                        // 注册成功
                        $url = url('user/Users/centre');
                        $this->success('注册成功，邮箱绑定成功，跳转至会员中心！', $url, ['status' => 0]);
                    } else if (3 == $users_verification) {
                        // 注册成功
                        $url = url('user/Users/centre');
                        $this->success('注册成功，手机绑定成功，跳转至会员中心！', $url, ['status' => 0]);
                    }
                } else {
                    $url = url('user/Users/login');
                    $this->success('注册成功，请登录！', $url, ['status' => 2]);
                }
            }
            $this->error('注册失败', null, ['status' => 4]);
        }

        // 会员属性资料信息
        $users_para = model('Users')->getDataPara('reg');
        $this->assign('users_para', $users_para);

        /*微信登录插件 - 判断是否显示微信登录按钮*/
        $weapp_wxlogin = 0;
        if (is_dir('./weapp/WxLogin/')) {
            $wx         = Db::name('weapp')->field('data,status,config')->where(['code' => 'WxLogin'])->find();
            $wx['data'] = unserialize($wx['data']);
            if ($wx['status'] == 1 && $wx['data']['login_show'] == 1) {
                $weapp_wxlogin = 1;
            }
            // 使用场景 0 PC+手机 1 手机 2 PC
            $wx['config'] = json_decode($wx['config'], true);
            if (isMobile() && !in_array($wx['config']['scene'], [0,1])) {
                $weapp_wxlogin = 0;
            } else if (!isMobile() && !in_array($wx['config']['scene'], [0,2])) {
                $weapp_wxlogin = 0;
            }
        }
        $this->assign('weapp_wxlogin', $weapp_wxlogin);
        /*end*/

        /*QQ登录插件 - 判断是否显示QQ登录按钮*/
        $weapp_qqlogin = 0;
        if (is_dir('./weapp/QqLogin/')) {
            $qq         = Db::name('weapp')->field('data,status,config')->where(['code' => 'QqLogin'])->find();
            $qq['data'] = unserialize($qq['data']);
            if ($qq['status'] == 1 && $qq['data']['login_show'] == 1) {
                $weapp_qqlogin = 1;
            }
            // 使用场景 0 PC+手机 1 手机 2 PC
            $qq['config'] = json_decode($qq['config'], true);
            if (isMobile() && !in_array($qq['config']['scene'], [0,1])) {
                $weapp_qqlogin = 0;
            } else if (!isMobile() && !in_array($qq['config']['scene'], [0,2])) {
                $weapp_qqlogin = 0;
            }
        }
        $this->assign('weapp_qqlogin', $weapp_qqlogin);
        /*end*/

        /*微博插件 - 判断是否显示微博按钮*/
        $weapp_wblogin = 0;
        if (is_dir('./weapp/Wblogin/')) {
            $wb         = Db::name('weapp')->field('data,status,config')->where(['code' => 'Wblogin'])->find();
            $wb['data'] = unserialize($wb['data']);
            if ($wb['status'] == 1 && $wb['data']['login_show'] == 1) {
                $weapp_wblogin = 1;
            }
            // 使用场景 0 PC+手机 1 手机 2 PC
            $wb['config'] = json_decode($wb['config'], true);
            if (isMobile() && !in_array($wb['config']['scene'], [0,1])) {
                $weapp_wblogin = 0;
            } else if (!isMobile() && !in_array($wb['config']['scene'], [0,2])) {
                $weapp_wblogin = 0;
            }
        }
        $this->assign('weapp_wblogin', $weapp_wblogin);
        /*end*/

        $html = $this->fetch('users_reg');
        if (isMobile()) {
            $str = <<<EOF
<div id="update_mobile_file" style="display: none;">
    <form id="form1" style="text-align: center;" >
        <input type="button" value="点击上传" onclick="up_f.click();" class="btn btn-primary form-control"/><br>
        <p><input type="file" id="up_f" name="up_f" onchange="MobileHeadPic();" style="display:none"/></p>
    </form>
</div>
</body>
EOF;
            $html = str_ireplace('</body>', $str, $html);
        }

        return $html;
    }

    // 会员中心
    public function centre()
    {
        $result = Db::name('users_menu')->where(['is_userpage' => 1, 'lang' => $this->home_lang])->find();
        $mca    = !empty($result['mca']) ? $result['mca'] : 'user/Users/index';
        $this->redirect($mca);
    }

    // 修改资料
    public function centre_update()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
/*            if (empty($this->users['password'])) {
                // 密码为空则表示第三方注册会员，强制设置密码
                if (empty($post['password'])) {
                    $this->error('第三方注册会员，为确保账号安全，请设置密码。');
                } else {
                    $password_new = func_encrypt($post['password']);
                }
            }*/

            $nickname = trim($post['nickname']);
            if (!empty($post['nickname']) && empty($nickname)) {
                $this->error('昵称不可为纯空格！');
            }

            $ParaData = [];
            if (is_array($post['users_'])) {
                $ParaData = $post['users_'];
            }
            unset($post['users_']);

            // 处理提交的会员属性中必填项是否为空
            // 必须传入提交的会员属性数组
            $EmptyData = model('Users')->isEmpty($ParaData);
            if ($EmptyData) {
                $this->error($EmptyData);
            }

            // 处理提交的会员属性中邮箱和手机是否已存在
            // IsRequired方法传入的参数有2个
            // 第一个必须传入提交的会员属性数组
            // 第二个users_id，注册时不需要传入，修改时需要传入。
            $RequiredData = model('Users')->isRequired($ParaData, $this->users_id);
            if ($RequiredData) {
                $this->error($RequiredData);
            }

            /*处理属性表的数据修改添加*/
            $row2 = $this->users_parameter_db->field('para_id,name')->getAllWithIndex('name');
            if (!empty($row2)) {
                foreach ($ParaData as $key => $value) {
                    if (!isset($row2[$key])) {
                        continue;
                    }

                    // 若为数组，则拆分成字符串
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }

                    $data                = [];
                    $para_id             = intval($row2[$key]['para_id']);
                    $where               = [
                        'users_id' => $this->users_id,
                        'para_id'  => $para_id,
                        'lang'     => $this->home_lang,
                    ];
                    $data['info']        = $value;
                    $data['update_time'] = getTime();

                    // 若信息表中无数据则添加
                    $row = $this->users_list_db->where($where)->count();
                    if (empty($row)) {
                        $data['users_id'] = $this->users_id;
                        $data['para_id']  = $para_id;
                        $data['lang']     = $this->home_lang;
                        $data['add_time'] = getTime();
                        $this->users_list_db->add($data);
                    } else {
                        $this->users_list_db->where($where)->update($data);
                    }
                }
            }

            // 查询属性表的手机和邮箱信息，同步修改会员信息
            $usersData             = model('Users')->getUsersListData('*', $this->users_id);
            $usersData['nickname'] = trim($post['nickname']);
            if (!empty($password_new)) {
                $usersData['password'] = $password_new;
            }
            $usersData['update_time'] = getTime();
            $return                   = $this->users_db->where('users_id', $this->users_id)->update($usersData);
            if ($return) {
                \think\Cache::clear('users_list');
                $this->success('操作成功');
            }
            $this->error('操作失败');
        }
        $this->error('访问错误！');
    }

    // 更改密码
    public function change_pwd()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['oldpassword'])) {
                $this->error('原密码不能为空！');
            } else if (empty($post['password'])) {
                $this->error('新密码不能为空！');
            } else if ($post['password'] != $post['password2']) {
                $this->error('重复密码与新密码不一致！');
            }

            $users = $this->users_db->field('password')->where([
                'users_id' => $this->users_id,
                'lang'     => $this->home_lang,
            ])->find();
            if (!empty($users)) {
                if (strval($users['password']) !== strval(func_encrypt($post['oldpassword']))) {
                    $this->error('原密码错误，请重新输入！');
                }

                $r = $this->users_db->where([
                    'users_id' => $this->users_id,
                    'lang'     => $this->home_lang,
                ])->update([
                    'password'    => func_encrypt($post['password']),
                    'update_time' => getTime(),
                ]);
                if ($r) {
                    $this->success('修改成功');
                }
                $this->error('修改失败');
            }
            $this->error('登录失效，请重新登录！');
        }

        return $this->fetch('users_change_pwd');
    }

    // 找回密码
    public function retrieve_password()
    {
        if ($this->users_id > 0) $this->redirect('user/Users/centre');

        if (!empty($this->usersConfig['users_retrieve_password']) && 2 == $this->usersConfig['users_retrieve_password']) {
            $this->redirect('user/Users/retrieve_password_mobile');
        }

        $is_vertify                 = 1; // 默认开启验证码
        $users_retrieve_pwd_captcha = config('captcha.users_retrieve_password');
        if (!function_exists('imagettftext') || empty($users_retrieve_pwd_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }
        $this->assign('is_vertify', $is_vertify);

        if (IS_AJAX_POST) {
            $post = input('post.');
            // POST数据基础判断
            if (empty($post['email'])) {
                $this->error('邮箱地址不能为空！');
            }
            if (1 == $is_vertify) {
                if (empty($post['vertify'])) {
                    $this->error('图片验证码不能为空！');
                }
            }
            if (empty($post['email_code'])) {
                $this->error('邮箱验证码不能为空！');
            }

            // 判断会员输入的邮箱是否存在
            $ListWhere = array(
                'info' => array('eq', $post['email']),
                'lang' => array('eq', $this->home_lang),
            );
            $ListData  = $this->users_list_db->where($ListWhere)->field('users_id')->find();
            if (empty($ListData)) {
                $this->error('邮箱不存在，不能找回密码！');
            }

            // 判断会员输入的邮箱是否已绑定
            $UsersWhere = array(
                'email' => array('eq', $post['email']),
                'lang'  => array('eq', $this->home_lang),
            );
            $UsersData  = $this->users_db->where($UsersWhere)->field('is_email')->find();
            if (empty($UsersData['is_email'])) {
                $this->error('邮箱未绑定，不能找回密码！');
            }

            // 查询会员输入的邮箱验证码是否存在
            $RecordWhere = [
                'code' => $post['email_code'],
                'lang' => $this->home_lang,
            ];
            $RecordData  = $this->smtp_record_db->where($RecordWhere)->field('status,add_time,email')->find();
            if (!empty($RecordData)) {
                // 邮箱验证码是否超时
                $time                   = getTime();
                $RecordData['add_time'] += Config::get('global.email_default_time_out');
                if ('1' == $RecordData['status'] || $RecordData['add_time'] <= $time) {
                    $this->error('邮箱验证码已被使用或超时，请重新发送！');
                } else {
                    // 图形验证码判断
                    if (1 == $is_vertify) {
                        $verify = new Verify();
                        if (!$verify->check($post['vertify'], "users_retrieve_password")) {
                            $this->error('图形验证码错误，请重新输入！');
                        }
                    }

                    session('users_retrieve_password_email', $post['email']); // 标识邮箱验证通过
                    $em  = rand(10, 99) . base64_encode($post['email']) . '/=';
                    $url = url('user/Users/reset_password', ['em' => base64_encode($em)]);
                    $this->success('操作成功', $url);
                }

            } else {
                $this->error('邮箱验证码不正确，请重新输入！');
            }
        }

        session('users_retrieve_password_email', null); // 标识邮箱验证通过

        /*检测会员邮箱属性是否开启*/
        $usersparamRow = $this->users_parameter_db->where([
            'name'      => ['LIKE', 'email_%'],
            'is_hidden' => 1,
            'lang'      => $this->home_lang,
        ])->find();
        if (!empty($usersparamRow)) {
            $this->error('会员邮箱属性已关闭，请联系网站管理员 ！');
        }
        /*--end*/

        return $this->fetch();
    }

    // 重置密码
    public function reset_password()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['password'])) {
                $this->error('新密码不能为空！');
            }
            if ($post['password'] != $post['password_']) {
                $this->error('两次密码输入不一致！');
            }

            $email = session('users_retrieve_password_email');
            if (!empty($email)) {
                $data   = [
                    'password'    => func_encrypt($post['password']),
                    'update_time' => getTime(),
                ];
                $return = $this->users_db->where([
                    'email' => $email,
                    'lang'  => $this->home_lang,
                ])->update($data);
                if ($return) {
                    session('users_retrieve_password_email', null); // 标识邮箱验证通过
                    $url = url('user/Users/login');
                    $this->success('重置成功！', $url);
                }
            }
            $this->error('重置失败！');
        }

        // 没有传入邮箱，重定向至找回密码页面
        $em    = input('param.em/s');
        $em    = base64_decode(input('param.em/s'));
        $em    = base64_decode(msubstr($em, 2, -2));
        $email = session('users_retrieve_password_email');
        if (empty($email) || !check_email($em) || $em != $email) {
            $this->redirect('user/Users/retrieve_password');
            exit;
        }
        $users = $this->users_db->where([
            'email' => $email,
            'lang'  => $this->home_lang,
        ])->find();

        if (!empty($users)) {
            // 查询会员输入的邮箱并且为找回密码来源的所有验证码
            $RecordWhere = [
                'source'   => 4,
                'email'    => $email,
                'users_id' => 0,
                'status'   => 0,
                'lang'     => $this->home_lang,
            ];
            // 更新数据
            $RecordData = [
                'status'      => 1,
                'update_time' => getTime(),
            ];
            $this->smtp_record_db->where($RecordWhere)->update($RecordData);
        }
        $this->assign('users', $users);
        return $this->fetch();
    }

    // 手机找回密码
    public function retrieve_password_mobile()
    {
        if ($this->users_id > 0) $this->redirect('user/Users/centre');

        if (!empty($this->usersConfig['users_retrieve_password']) && 1 == $this->usersConfig['users_retrieve_password']) {
            $this->redirect('user/Users/retrieve_password');
        }

        $is_vertify = 1; // 默认开启验证码
        $users_retrieve_pwd_captcha = config('captcha.users_retrieve_password');
        if (!function_exists('imagettftext') || empty($users_retrieve_pwd_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }
        $this->assign('is_vertify', $is_vertify);

        if (IS_AJAX_POST) {
            $post = input('post.');

            // POST数据基础判断
            if (empty($post['mobile'])) $this->error('请输入手机号码');
            if (empty($post['mobile_code'])) $this->error('请输入手机验证码');
            if (1 == $is_vertify) {
                if (empty($post['vertify'])) $this->error('请输入图片验证码');
            }

            // 判断会员输入的手机是否存在
            $ListWhere = array(
                'info' => array('eq', $post['mobile']),
                'lang' => array('eq', $this->home_lang)
            );
            $ListData  = $this->users_list_db->where($ListWhere)->field('users_id')->find();
            if (empty($ListData)) $this->error('手机号码不存在，不能找回密码！');

            // 判断会员输入的手机是否已绑定
            $UsersWhere = array(
                'mobile' => array('eq', $post['mobile']),
                'lang'   => array('eq', $this->home_lang)
            );
            $UsersData  = $this->users_db->where($UsersWhere)->field('is_mobile')->find();
            if (empty($UsersData['is_mobile'])) $this->error('手机号码未绑定，不能找回密码！');

            // 判断验证码是否存在并且是否可用
            $RecordWhere = [
                'mobile' => $post['mobile'],
                'code'   => $post['mobile_code'],
                'lang'   => $this->home_lang
            ];
            $RecordData = $this->sms_log_db->where($RecordWhere)->field('is_use, add_time')->order('id desc')->find();
            if (!empty($RecordData)) {
                // 验证码存在
                $time = getTime();
                $RecordData['add_time'] += Config::get('global.mobile_default_time_out');
                if (1 == $RecordData['is_use'] || $RecordData['add_time'] <= $time) {
                    $this->error('手机验证码已被使用或超时，请重新发送！');
                } else {
                    // 处理手机验证码
                    $RecordWhere = [
                        'source'   => 4,
                        'mobile'   => $post['mobile'],
                        'is_use'   => 0,
                        'lang'     => $this->home_lang
                    ];
                    // 更新数据
                    $RecordData = [
                        'is_use'      => 1,
                        'update_time' => $time
                    ];
                    $this->sms_log_db->where($RecordWhere)->update($RecordData);
                    session('users_retrieve_password_mobile', $post['mobile']);
                    $this->success('验证通过', url('user/Users/reset_password_mobile'));
                }
            } else {
                $this->error('手机验证码不正确，请重新输入！');
            }
        }

        session('users_retrieve_password_mobile', null);

        /*检测会员邮箱属性是否开启*/
        $usersparamRow = $this->users_parameter_db->where([
            'name'      => ['LIKE', 'mobile_%'],
            'is_hidden' => 1,
            'lang'      => $this->home_lang
        ])->find();
        if (!empty($usersparamRow)) $this->error('会员手机属性已关闭，请联系网站管理员！');
        /*--end*/

        return $this->fetch();
    }

    public function reset_password_mobile()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['password'])) $this->error('请输入新密码');
            if (empty($post['password_'])) $this->error('请输入确认新密码');
            if ($post['password'] != $post['password_']) $this->error('两次密码输入不一致！');

            $mobile = session('users_retrieve_password_mobile');
            if (!empty($mobile)) {
                $data   = [
                    'password'    => func_encrypt($post['password']),
                    'update_time' => getTime()
                ];
                $return = $this->users_db->where(['mobile'=>$mobile, 'lang'=>$this->home_lang])->update($data);
                if ($return) {
                    session('users_retrieve_password_mobile', null);
                    $url = url('user/Users/login');
                    $this->success('重置成功！', $url);
                }
            }
            $this->error('重置失败！');
        }

        // 没有手机号则重定向至找回密码页面
        $mobile = session('users_retrieve_password_mobile');
        if (empty($mobile)) $this->redirect('user/Users/retrieve_password_mobile');
        
        // 查询会员信息
        $username = $this->users_db->where(['mobile'=>$mobile, 'lang'=>$this->home_lang])->getField('username');
        $this->assign('username', $username);
        return $this->fetch();   
    }

    public function edit_users_head_pic()
    {
        if (IS_AJAX_POST) {
            $filename = input('param.filename/s', '');
            if (!empty($filename) && !is_http_url($filename)) {
                $head_pic_url = $filename;
                if (!empty($head_pic_url)) {
                    $usersData['head_pic']    = $head_pic_url;
                    $usersData['update_time'] = getTime();
                    $return                   = $this->users_db->where([
                        'users_id' => $this->users_id,
                        'lang'     => $this->home_lang,
                    ])->update($usersData);
                }
                if ($return) {
                    /*同步头像到管理员表对应的管理员*/
                    if (!empty($this->users['admin_id'])) {
                        Db::name('admin')->where(['admin_id'=>$this->users['admin_id']])->update([
                            'head_pic'  => $head_pic_url,
                            'update_time'   => getTime(),
                        ]);
                    }
                    /*end*/
                    $this->success('操作成功！');
                } else {
                    $this->error('操作失败！');
                }
            } else {
                $this->error('上传本地图片错误！');
            }
        }
    }

    public function bind_email()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (!empty($post['email']) && !empty($post['email_code'])) {
                // 邮箱格式验证是否正确
                if (!check_email($post['email'])) {
                    $this->error('邮箱格式不正确！');
                }

                // 是否已存在相同邮箱地址
                $ListWhere = [
                    'users_id' => ['NEQ', $this->users_id],
                    'info'     => $post['email'],
                    'lang'     => $this->home_lang,
                ];
                $ListData  = $this->users_list_db->where($ListWhere)->count();
                if (!empty($ListData)) {
                    $this->error('该邮箱已存在，不可绑定！');
                }

                // 判断验证码是否存在并且是否可用
                $RecordWhere = [
                    'email'    => $post['email'],
                    'code'     => $post['email_code'],
                    'users_id' => $this->users_id,
                    'lang'     => $this->home_lang,
                ];
                $RecordData  = $this->smtp_record_db->where($RecordWhere)->field('record_id,email,status,add_time')->find();
                if (!empty($RecordData)) {
                    // 验证码存在
                    $time                   = getTime();
                    $RecordData['add_time'] += Config::get('global.email_default_time_out');
                    if (1 == $RecordData['status'] || $RecordData['add_time'] <= $time) {
                        // 验证码不可用
                        $this->error('邮箱验证码已被使用或超时，请重新发送！');
                    } else {
                        // 查询会员输入的邮箱并且为绑定邮箱来源的所有验证码
                        $RecordWhere = [
                            'source'   => 3,
                            'email'    => $RecordData['email'],
                            'users_id' => $this->users_id,
                            'status'   => 0,
                            'lang'     => $this->home_lang,
                        ];

                        // 更新数据
                        $RecordData = [
                            'status'      => 1,
                            'update_time' => $time,
                        ];
                        $this->smtp_record_db->where($RecordWhere)->update($RecordData);

                        // 匹配查询邮箱
                        $ParaWhere = [
                            'name'      => ['LIKE', "email_%"],
                            'is_system' => 1,
                            'lang'      => $this->home_lang,
                        ];
                        $ParaData  = $this->users_parameter_db->where($ParaWhere)->field('para_id')->find();

                        // 修改会员属性表信息
                        $listCount = $this->users_list_db->where([
                            'para_id'  => $ParaData['para_id'],
                            'users_id' => ['EQ', $this->users_id],
                            'lang'     => $this->home_lang,
                        ])->count();
                        if (empty($listCount)) { // 后台新增会员，没有会员属性记录的情况
                            $ListData = [
                                'users_id' => $this->users_id,
                                'para_id'  => $ParaData['para_id'],
                                'info'     => $post['email'],
                                'lang'     => $this->home_lang,
                                'add_time' => $time,
                            ];
                            $IsList   = $this->users_list_db->where($ListWhere)->add($ListData);
                        } else {
                            $ListWhere = [
                                'users_id' => $this->users_id,
                                'para_id'  => $ParaData['para_id'],
                                'lang'     => $this->home_lang,
                            ];
                            $ListData  = [
                                'info'        => $post['email'],
                                'update_time' => $time,
                            ];
                            $IsList    = $this->users_list_db->where($ListWhere)->update($ListData);
                        }

                        if (!empty($IsList)) {
                            // 同步修改会员表邮箱地址，并绑定邮箱地址到会员账号
                            $UsersData = [
                                'users_id'    => $this->users_id,
                                'is_email'    => '1',
                                'email'       => $post['email'],
                                'update_time' => $time,
                            ];
                            $this->users_db->update($UsersData);
                            \think\Cache::clear('users_list');
                            $this->success('操作成功！');
                        } else {
                            $this->error('未知错误，邮箱地址修改失败，请重新获取验证码！');
                        }
                    }
                } else {
                    $this->error('输入的邮箱地址和邮箱验证码不一致，请重新输入！');
                }
            }
        }
        $title = input('param.title/s');
        $this->assign('title', $title);
        return $this->fetch();
    }

    // 绑定手机
    public function bind_mobile()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (!empty($post['mobile']) && !empty($post['mobile_code'])) {
                // 手机格式验证是否正确
                if (!check_mobile($post['mobile'])) $this->error('手机格式不正确！');
                
                // 是否已存在相同手机号码
                $ListWhere = [
                    'users_id' => ['NEQ', $this->users_id],
                    'info'     => $post['mobile'],
                    'lang'     => $this->home_lang
                ];
                $ListData  = $this->users_list_db->where($ListWhere)->count();
                if (!empty($ListData)) $this->error('手机号码已存在，不可绑定！');

                // 判断验证码是否存在并且是否可用
                $RecordWhere = [
                    'mobile'   => $post['mobile'],
                    'code'     => $post['mobile_code'],
                    'lang'     => $this->home_lang
                ];
                $RecordData = $this->sms_log_db->where($RecordWhere)->field('is_use, add_time')->order('id desc')->find();
                if (!empty($RecordData)) {
                    // 验证码存在
                    $time = getTime();
                    $RecordData['add_time'] += Config::get('global.mobile_default_time_out');
                    if (1 == $RecordData['is_use'] || $RecordData['add_time'] <= $time) {
                        // 验证码不可用
                        $this->error('手机验证码已被使用或超时，请重新发送！');
                    } else {
                        // 查询会员输入的邮箱并且为绑定邮箱来源的所有验证码
                        $RecordWhere = [
                            'source'   => 1,
                            'mobile'   => $post['mobile'],
                            'is_use'   => 0,
                            'lang'     => $this->home_lang
                        ];
                        // 更新数据
                        $RecordData = [
                            'is_use'      => 1,
                            'update_time' => $time
                        ];
                        $this->sms_log_db->where($RecordWhere)->update($RecordData);

                        // 匹配查询手机
                        $ParaWhere = [
                            'name'      => ['LIKE', "mobile_%"],
                            'is_system' => 1,
                            'lang'      => $this->home_lang
                        ];
                        $ParaData  = $this->users_parameter_db->where($ParaWhere)->field('para_id')->find();

                        // 修改会员属性表信息
                        $listCount = $this->users_list_db->where([
                            'para_id'  => $ParaData['para_id'],
                            'users_id' => ['EQ', $this->users_id],
                            'lang'     => $this->home_lang
                        ])->count();

                        if (empty($listCount)) {
                            // 后台新增会员，没有会员属性记录的情况
                            $ListData = [
                                'users_id' => $this->users_id,
                                'para_id'  => $ParaData['para_id'],
                                'info'     => $post['mobile'],
                                'lang'     => $this->home_lang,
                                'add_time' => $time
                            ];
                            $IsList = $this->users_list_db->where($ListWhere)->add($ListData);
                        } else {
                            $ListWhere = [
                                'users_id' => $this->users_id,
                                'para_id'  => $ParaData['para_id'],
                                'lang'     => $this->home_lang
                            ];
                            $ListData  = [
                                'info' => $post['mobile'],
                                'update_time' => $time
                            ];
                            $IsList = $this->users_list_db->where($ListWhere)->update($ListData);
                        }

                        if (!empty($IsList)) {
                            // 同步修改会员表邮箱地址，并绑定邮箱地址到会员账号
                            $UsersData = [
                                'users_id'    => $this->users_id,
                                'is_mobile'   => 1,
                                'mobile'      => $post['mobile'],
                                'update_time' => $time
                            ];
                            $this->users_db->update($UsersData);
                            \think\Cache::clear('users_list');
                            $this->success('操作成功！');
                        } else {
                            $this->error('未知错误，手机号码修改失败，请重新获取验证码！');
                        }
                    }
                } else {
                    $this->error('输入的手机号码和手机验证码不一致，请重新输入！');
                }
            }
        }
        $title = input('param.title/s');
        $this->assign('title', $title);
        return $this->fetch();
    }

    // 退出登陆
    public function logout()
    {
        session('users_id', null);
        session('users', null);
        // session('open_id',null);
        setcookie('users_id', '', getTime() - 3600);

        // 跳转链接
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ROOT_DIR . '/';
        $this->redirect($referurl);
    }
}