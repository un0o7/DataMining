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

/**
 * 逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
class AjaxLogic extends Model
{
    private $request = null;
    private $admin_lang = 'cn';
    private $main_lang = 'cn';

    /**
     * 析构函数
     */
    function  __construct() {
        $this->request = request();
        $this->admin_lang = get_admin_lang();
        $this->main_lang = get_main_lang();
    }

    /**
     * 进入登录页面需要异步处理的业务
     */
    public function login_handle()
    {
        $this->saveBaseFile(); // 存储后台入口文件路径，比如：/login.php
        $this->clear_session_file(); // 清理过期的data/session文件
    }

    /**
     * 进入欢迎页面需要异步处理的业务
     */
    public function welcome_handle()
    {
        $this->saveBaseFile(); // 存储后台入口文件路径，比如：/login.php
        $this->renameInstall(); // 重命名安装目录，提高网站安全性
        $this->del_adminlog(); // 只保留最近三个月的操作日志
        $this->syn_smtp_config(); // 同步插件【邮箱发送】的配置信息到内置表中
        tpversion(); // 统计装载量，请勿删除，谢谢支持！
    }
    
    /**
     * 只保留最近三个月的操作日志
     */
    private function del_adminlog()
    {
        $mtime = strtotime("-1 month");
        Db::name('admin_log')->where([
            'log_time'  => ['lt', $mtime],
            ])->delete();
        // 临时清理无效图片
        @unlink('./public/plugins/Ueditor/themes/default/images/worwdpasdte.png');
    }

    /**
     * 重命名安装目录，提高网站安全性
     * 在 Admin@login 和 Index@index 操作下
     */
    private function renameInstall()
    {
        $install_path = ROOT_PATH.'install';
        if (is_dir($install_path) && file_exists($install_path)) {
            $install_time = DEFAULT_INSTALL_DATE;
            $constsant_path = APP_PATH.'admin/conf/constant.php';
            if (file_exists($constsant_path)) {
                require_once($constsant_path);
                defined('INSTALL_DATE') && $install_time = INSTALL_DATE;
            }
            $new_path = ROOT_PATH.'install_'.$install_time;
            @rename($install_path, $new_path);
        } else { // 修补v1.1.6版本删除的安装文件 install.lock
            if(!empty($_SESSION['isset_install_lock']))
                return true;
            $_SESSION['isset_install_lock'] = 1;

            $install_time = DEFAULT_INSTALL_DATE;
            $constsant_path = APP_PATH.'admin/conf/constant.php';
            if (file_exists($constsant_path)) {
                require_once($constsant_path);
                defined('INSTALL_DATE') && $install_time = INSTALL_DATE;
            }
            $filename = ROOT_PATH.'install_'.$install_time.DS.'install.lock';
            if (!file_exists($filename)) {
                @file_put_contents($filename, '');
            }
        }
    }

    /**
     * 存储后台入口文件路径，比如：/login.php
     * 在 Admin@login 和 Index@index 操作下
     */
    private function saveBaseFile()
    {
        $baseFile = $this->request->baseFile();
        /*多语言*/
        if (is_language()) {
            $langRow = \think\Db::name('language')->field('mark')->order('id asc')->select();
            foreach ($langRow as $key => $val) {
                tpCache('web', ['web_adminbasefile'=>$baseFile], $val['mark']);
            }
        } else { // 单语言
            tpCache('web', ['web_adminbasefile'=>$baseFile]);
        }
        /*--end*/
    }

    /**
     * 清理过期的data/session文件
     */
    private function clear_session_file()
    {
        $path = \think\Config::get('session.path');
        if (!empty($path) && file_exists($path)) {
            $web_login_expiretime = tpCache('web.web_login_expiretime');
            empty($web_login_expiretime) && $web_login_expiretime = config('login_expire');
            $files = glob($path.'/sess_*');
            foreach ($files as $key => $file) {
                $filemtime = filemtime($file);
                if (getTime() - intval($filemtime) > $web_login_expiretime) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * 同步插件【邮箱发送】的配置信息到内置表中 -- 兼容1.3.0之前版本
     */
    private function syn_smtp_config()
    {
        $smtp_syn_weapp = tpCache('smtp.smtp_syn_weapp'); // 是否同步插件【邮箱发送】的配置
        if (empty($smtp_syn_weapp)) {

            /*同步之前安装邮箱插件的配置信息*/
            $data = \think\Db::name('weapp')->where('code','Smtpmail')->getField('data');
            if (!empty($data)) {
                $data = unserialize($data);
                if (is_array($data) && !empty($data)) {
                    foreach ($data as $key => $val) {
                        if (!in_array($key, ['smtp_server','smtp_port','smtp_user','smtp_pwd','smtp_from_eamil'])) {
                            unset($data[$key]);
                        }
                    }
                }
            }
            /*--end*/

            $data['smtp_syn_weapp'] = 1;

            /*多语言*/
            if (!is_language()) {
                tpCache('smtp',$data);
            } else {
                $smtp_tpl_db = \think\Db::name('smtp_tpl');
                $smtptplList = $smtp_tpl_db->field('tpl_id,lang')->getAllWithIndex('lang');
                $smtptplRow = $smtp_tpl_db->field('tpl_id,lang',true)
                    ->where('lang', get_main_lang())
                    ->order('tpl_id asc')
                    ->select();

                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    /*同步多语言邮件模板表数据*/
                    if (empty($smtptplList[$val['mark']]) && !empty($smtptplRow)) {
                        foreach ($smtptplRow as $key2 => $val2) {
                            $smtptplRow[$key2]['lang'] = $val['mark'];
                        }
                        model('SmtpTpl')->saveAll($smtptplRow);
                    }
                    /*--end*/
                    tpCache('smtp', $data, $val['mark']);
                }
            }
            /*--end*/
        }
    }

    /**
     * 升级前台会员中心的模板文件
     */
    public function update_template($type = '')
    {
        if (!empty($type)) {
            if ('users' == $type) {
                if (file_exists(ROOT_PATH.'template/'.TPL_THEME.'pc/users') || file_exists(ROOT_PATH.'template/'.TPL_THEME.'mobile/users')) {
                    $upgrade = getDirFile(DATA_PATH.'backup'.DS.'tpl');
                    if (!empty($upgrade) && is_array($upgrade)) {
                        delFile(DATA_PATH.'backup'.DS.'template_www');
                        // 升级之前，备份涉及的源文件
                        foreach ($upgrade as $key => $val) {
                            $val_tmp = str_replace("template/", "template/".TPL_THEME, $val);
                            $source_file = ROOT_PATH.$val_tmp;
                            if (file_exists($source_file)) {
                                $destination_file = DATA_PATH.'backup'.DS.'template_www'.DS.$val_tmp;
                                tp_mkdir(dirname($destination_file));
                                @copy($source_file, $destination_file);
                            }
                        }

                        // 递归复制文件夹
                        $this->recurse_copy(DATA_PATH.'backup'.DS.'tpl', rtrim(ROOT_PATH, DS));
                    }
                    /*--end*/
                }
            }
        }
    }

    /**
     * 自定义函数递归的复制带有多级子目录的目录
     * 递归复制文件夹
     *
     * @param string $src 原目录
     * @param string $dst 复制到的目录
     * @return string
     */                        
    //参数说明：            
    //自定义函数递归的复制带有多级子目录的目录
    private function recurse_copy($src, $dst)
    {
        $planPath_pc = "template/".TPL_THEME."pc/";
        $planPath_m = "template/".TPL_THEME."mobile/";
        $dir = opendir($src);

        /*pc和mobile目录存在的情况下，才拷贝会员模板到相应的pc或mobile里*/
        $dst_tmp = str_replace('\\', '/', $dst);
        $dst_tmp = rtrim($dst_tmp, '/').'/';
        if (stristr($dst_tmp, $planPath_pc) && file_exists($planPath_pc)) {
            tp_mkdir($dst);
        } else if (stristr($dst_tmp, $planPath_m) && file_exists($planPath_m)) {
            tp_mkdir($dst);
        }
        /*--end*/

        while (false !== $file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $needle = '/template/'.TPL_THEME;
                    $needle = rtrim($needle, '/');
                    $dstfile = $dst . '/' . $file;
                    if (!stristr($dstfile, $needle)) {
                        $dstfile = str_replace('/template', $needle, $dstfile);
                    }
                    $this->recurse_copy($src . '/' . $file, $dstfile);
                }
                else {
                    if (file_exists($src . DIRECTORY_SEPARATOR . $file)) {
                        /*pc和mobile目录存在的情况下，才拷贝会员模板到相应的pc或mobile里*/
                        $rs = true;
                        $src_tmp = str_replace('\\', '/', $src . DIRECTORY_SEPARATOR . $file);
                        if (stristr($src_tmp, $planPath_pc) && !file_exists($planPath_pc)) {
                            continue;
                        } else if (stristr($src_tmp, $planPath_m) && !file_exists($planPath_m)) {
                            continue;
                        }
                        /*--end*/
                        $rs = @copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                        if($rs) {
                            @unlink($src . DIRECTORY_SEPARATOR . $file);
                        }
                    }
                }
            }
        }
        closedir($dir);
    }

    // 只同步一次每个留言栏目的字段列表前4个显示(v1.5.0节点去掉)
    public function syn_guestbook_attribute()
    {
        $syn_gb_attribute_showlist = tpCache('syn.syn_gb_attribute_showlist');
        if (empty($syn_gb_attribute_showlist)) {
            $arctypeRow = Db::name('arctype')->field('id')->where('current_channel', 8)->select();
            foreach ($arctypeRow as $key => $val) {
                $attr_ids = Db::name('guestbook_attribute')->where('typeid', $val['id'])->order('attr_id asc')->limit(4)->column('attr_id');
                $attr_id = end($attr_ids);
                Db::name('guestbook_attribute')->where([
                    'typeid'    => $val['id'],
                    'attr_id'   => ['elt', intval($attr_id)],
                ])->update([
                    'is_showlist'   => 1,
                    'update_time'   => getTime(),
                ]);
            }
            tpCache('syn', ['syn_gb_attribute_showlist'=>1]);
        }
    }
    
    // 记录当前是多语言还是单语言到文件里
    public function system_langnum_file()
    {
        model('Language')->setLangNum();
    }

    // 只同步一次微信登录配置信息(v1.5.1节点去掉)
    public function syn_wechat_login_config()
    {
        $syn_wechat_login_config = tpCache('syn.syn_wechat_login_config');
        if (empty($syn_wechat_login_config)) {
            $ResultData = getUsersConfigData('pay.pay_wechat_config');
            $value = !empty($ResultData) ? unserialize($ResultData) : [];
            if (!empty($value['appid']) && !empty($value['appsecret'])) {
                $SynData = [
                    'appid' => $value['appid'],
                    'appsecret' => $value['appsecret'],
                    'wechat_name' => '',
                    'wechat_pic' => ''
                ];
                $Data['wechat']['wechat_login_config'] = serialize($SynData);
                foreach ($Data as $key => $val) {
                    getUsersConfigData($key, $val);
                }
                tpCache('syn', ['syn_wechat_login_config'=>1]);
            }
        }
    }

    // 只同步一次微信支付、支付宝支付配置(v1.5.1节点去掉)
    public function SynPayConfig()
    {
        /*同步微信支付*/
        $syn_wechat_pay_config = tpCache('syn.syn_wechat_pay_config', [], 'cn');
        if (empty($syn_wechat_pay_config)) {
            $ResultData = getUsersConfigData('pay.pay_wechat_config');
            $value = !empty($ResultData) ? unserialize($ResultData) : [];
            if (!empty($value['appid']) && !empty($value['mchid']) && !empty($value['key'])) {
                $SynData['pay_info'] = serialize($value);
                $SynData['update_time'] = getTime();
                $where = [
                    'pay_id' => 1,
                    'pay_mark' => 'wechat',
                    'system_built' => 1
                ];
                $ResultID = Db::name('pay_api_config')->where($where)->update($SynData);
                if (!empty($ResultID)) tpCache('syn', ['syn_wechat_pay_config' => 1], 'cn');
            }
        }
        /* END */

        /*同步支付宝支付*/
        $syn_alipay_pay_config = tpCache('syn.syn_alipay_pay_config', [], 'cn');
        if (empty($syn_alipay_pay_config)) {
            $ResultData = getUsersConfigData('pay.pay_alipay_config');
            $value = !empty($ResultData) ? unserialize($ResultData) : [];
            if ( (!empty($value['app_id']) && !empty($value['merchant_private_key']) && !empty($value['alipay_public_key'])) || (!empty($value['account']) && !empty($value['code']) && !empty($value['id'])) ) {
                $SynData['pay_info'] = serialize($value);
                $SynData['update_time'] = getTime();
                $where = [
                    'pay_id' => 2,
                    'pay_mark' => 'alipay',
                    'system_built' => 1
                ];
                $ResultID = Db::name('pay_api_config')->where($where)->update($SynData);
                if (!empty($ResultID)) tpCache('syn', ['syn_alipay_pay_config' => 1], 'cn');
            }
        }
        /* END */
    }

    // 删除多余Minipro的文件(v1.5.1节点去掉)
    public function admin_logic_unlink()
    {
        $syn_admin_logic_unlink = tpCache('syn.syn_admin_logic_unlink', [], 'cn');
        if (empty($syn_admin_logic_unlink)) {

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->field('mark')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('php', ['php_weapp_plugin_open'=>1], $val['mark']);
                }
            } else { // 单语言
                tpCache('php', ['php_weapp_plugin_open'=>1]);
            }
            /*--end*/

            session('isset_author', null);

            // 删除多余的文件
            $files = [
                'application/admin/controller/Minipro.php',
                'application/admin/model/Minipro.php',
                'application/admin/model/MiniproCategory.php',
                'application/admin/model/MiniproHelp.php',
                'application/admin/model/MiniproPage.php',
                'application/admin/model/MiniproTabbar.php',
                'application/admin/template/minipro/',
                'application/api/controller/Minipro.php',
                'application/api/controller/MiniproBase.php',
                'application/api/model/Minipro.php',
                'application/api/model/MiniproCategory.php',
                'application/api/model/MiniproPage.php',
                'application/common/logic/MiniproLogic.php',
                'application/common/model/Minipro.php',
                'application/common/model/MiniproBase.php',
                'application/common/model/MiniproCategory.php',
                'application/common/model/MiniproHelp.php',
                'application/common/model/MiniproPage.php',
                'application/common/model/MiniproSetting.php',
                'application/common/model/MiniproTabbar.php',
                'data/schema/ey_minipro.php',
                'data/schema/ey_minipro_category.php',
                'data/schema/ey_minipro_help.php',
                'data/schema/ey_minipro_page.php',
                'data/schema/ey_minipro_setting.php',
                'data/schema/ey_minipro_tabbar.php',
                'public/static/common/minipro/',
            ];
            foreach ($files as $key => $val) {
                if (file_exists($val)) {
                    if (is_file($val)) {
                        @unlink('./' . $val);
                    } else if (is_dir($val)) {
                        delFile('./' . $val, true);
                    }
                }
            }
            tpCache('syn', ['syn_admin_logic_unlink'=>1], 'cn');
        }
    }

    /**
     * 纠正允许上传文件类型(v1.5.1节点去掉)
     */
    public function admin_logic_update_basic()
    {
        $syn_admin_logic_update_basic = tpCache('syn.syn_admin_logic_update_basic', [], 'cn');
        if (empty($syn_admin_logic_update_basic)) {
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->field('mark')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    $file_type = tpCache('basic.file_type', [], $val['mark']);
                    $file_types = explode('|', $file_type);
                    foreach ($file_types as $_k => $_v) {
                        if ('xsl' == trim($_v)) {
                            $file_types[$_k] = 'xls';
                        }
                    }
                    $file_type = implode('|', $file_types);
                    tpCache('basic', ['file_type'=>$file_type], $val['mark']);
                }
            } else { // 单语言
                $file_type = tpCache('basic.file_type');
                $file_types = explode('|', $file_type);
                foreach ($file_types as $key => $val) {
                    if ('xsl' == trim($val)) {
                        $file_types[$key] = 'xls';
                    }
                }
                $file_type = implode('|', $file_types);
                tpCache('basic', ['file_type'=>$file_type]);
            }
            /*--end*/
            tpCache('syn', ['syn_admin_logic_update_basic'=>1], 'cn');
        }
    }

    /**
     * 同步手机短信模板
     * @return [type] [description]
     */
    public function syn_admin_logic_sms_template()
    {
        $syn_admin_logic_sms_template = tpCache('syn.syn_admin_logic_sms_template', [], 'cn');
        if (empty($syn_admin_logic_sms_template)) {
            if (is_language()) {
                // 多语言
                $langRow = \think\Db::name('language')->field('mark')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    $array[] = [
                        'tpl_title' => '账号注册',
                        'sms_sign' => '',
                        'sms_tpl_code' => '',
                        'tpl_content' => '验证码为 ${content} ，请在30分钟内输入验证。',
                        'send_scene' => 0,
                        'is_open' => 1,
                        'lang' => $val['mark'],
                        'add_time' => getTime(),
                        'update_time' => getTime()
                    ];
                    $array[] = [
                        'tpl_title' => '手机绑定',
                        'sms_sign' => '',
                        'sms_tpl_code' => '',
                        'tpl_content' => '验证码为 ${content} ，请在30分钟内输入验证。',
                        'send_scene' => 1,
                        'is_open' => 1,
                        'lang' => $val['mark'],
                        'add_time' => getTime(),
                        'update_time' => getTime()
                    ];
                    $array[] = [
                        'tpl_title' => '找回密码',
                        'sms_sign' => '',
                        'sms_tpl_code' => '',
                        'tpl_content' => '验证码为 ${content} ，请在30分钟内输入验证。',
                        'send_scene' => 4,
                        'is_open' => 1,
                        'lang' => $val['mark'],
                        'add_time' => getTime(),
                        'update_time' => getTime()
                    ];
                    $array[] = [
                        'tpl_title' => '订单通知',
                        'sms_sign' => '',
                        'sms_tpl_code' => '',
                        'tpl_content' => '您有新的消息：${content}，请注意查收！',
                        'send_scene' => 5,
                        'is_open' => 1,
                        'lang' => $val['mark'],
                        'add_time' => getTime(),
                        'update_time' => getTime()
                    ];
                }
            } else {
                // 单语言
                $array[0] = [
                    'tpl_title' => '账号注册',
                    'sms_sign' => '',
                    'sms_tpl_code' => '',
                    'tpl_content' => '验证码为 ${content} ，请在30分钟内输入验证。',
                    'send_scene' => 0,
                    'is_open' => 1,
                    'lang' => $this->admin_lang,
                    'add_time' => getTime(),
                    'update_time' => getTime()
                ];
                $array[1] = [
                    'tpl_title' => '手机绑定',
                    'sms_sign' => '',
                    'sms_tpl_code' => '',
                    'tpl_content' => '验证码为 ${content} ，请在30分钟内输入验证。',
                    'send_scene' => 1,
                    'is_open' => 1,
                    'lang' => $this->admin_lang,
                    'add_time' => getTime(),
                    'update_time' => getTime()
                ];
                $array[2] = [
                    'tpl_title' => '找回密码',
                    'sms_sign' => '',
                    'sms_tpl_code' => '',
                    'tpl_content' => '验证码为 ${content} ，请在30分钟内输入验证。',
                    'send_scene' => 4,
                    'is_open' => 1,
                    'lang' => $this->admin_lang,
                    'add_time' => getTime(),
                    'update_time' => getTime()
                ];
                $array[3] = [
                    'tpl_title' => '订单通知',
                    'sms_sign' => '',
                    'sms_tpl_code' => '',
                    'tpl_content' => '您有新的消息：${content}，请注意查收！',
                    'send_scene' => 5,
                    'is_open' => 1,
                    'lang' => $this->admin_lang,
                    'add_time' => getTime(),
                    'update_time' => getTime()
                ];
            }
            // 批量新增
            $r = Db::name('sms_template')->insertAll($array);
            if ($r !== false) {
                tpCache('syn', ['syn_admin_logic_sms_template'=>1], 'cn');
            }
        }
    }

    /**
     * 纠正栏目层级的错误
     * @return [type] [description]
     */
    public function admin_logic_update_arctype()
    {
        $syn_admin_logic_update_arctype = tpCache('syn.syn_admin_logic_update_arctype', [], 'cn');
        if (empty($syn_admin_logic_update_arctype)) {
            $saveData = [];
            $arctypeRow = Db::name('arctype')->field('id,dirpath,grade,seo_description')->select();
            foreach ($arctypeRow as $key => $val) {
                if (empty($val['seo_description'])) {
                    $val['seo_description'] = '';
                }
                $dirpath = trim($val['dirpath'], '/');
                $dirpath_arr = explode('/', $dirpath);
                $count = count($dirpath_arr);
                if (1 < $count) {
                    $val['grade'] = $count - 1;
                } else {
                    $val['grade'] = 0;
                }
                $saveData[] = $val;
            }
            $r = model('Arctype')->saveAll($saveData);
            if ($r !== false) {
                \think\Cache::clear("arctype");
                tpCache('syn', ['syn_admin_logic_update_arctype'=>1], 'cn');
            }
        }
    }

    /**
     * 纠正未审核文档tag标签显示问题
     */
    public function admin_logic_update_tag()
    {
        $syn_admin_logic_update_tag = tpCache('syn.syn_admin_logic_update_tag', [], 'cn');
        if (empty($syn_admin_logic_update_tag)) {
            try{
                $archives = Db::name('archives')->field('aid,arcrank')->where([
                    'arcrank'   => -1,
                ])->getAllWithIndex('aid');
                if (!empty($archives)) {
                    $aids = array_keys($archives);
                    Db::name('taglist')->where([
                        'aid'   => ['IN', $aids],
                    ])->update(['arcrank'=>-1,'update_time'=>getTime()]);
                }
            }catch(\Exception $e){}
            tpCache('syn', ['syn_admin_logic_update_tag'=>1], 'cn');
        }
    }

    /**
     * 同步视频模型内置的附加表字段
     */
    public function admin_logic_video_addfields()
    {
        $syn_admin_logic_video_addfields = tpCache('syn.syn_admin_logic_video_addfields', [], 'cn');
        if (empty($syn_admin_logic_video_addfields)) {
            try{
                $totalRow = Db::name('channelfield_bind')->count();
                if (!empty($totalRow)) {
                    $channel_id = 5;
                    $fieldLogic = new \app\admin\logic\FieldLogic;
                    $fieldLogic->synChannelTableColumns($channel_id);

                    $addData = [];
                    $result = Db::name('channelfield')->field('id')->where(['channel_id'=>$channel_id,'ifmain'=>0])->select();
                    foreach ($result as $key => $val) {
                        $addData[] = [
                            'typeid'      => 0,
                            'field_id'    => $val['id'],
                            'add_time'    => getTime(),
                            'update_time' => getTime(),
                        ];
                    }
                    !empty($addData) && model('ChannelfieldBind')->saveAll($addData);
                }
            }catch(\Exception $e){}
            tpCache('syn', ['syn_admin_logic_video_addfields'=>1], 'cn');
        }
    }

    /**
     * 纠正tagindex标签被误删的tag
     */
    public function admin_logic_add_tag()
    {
        $syn_admin_logic_add_tag = tpCache('syn.syn_admin_logic_add_tag', [], 'cn');
        if (empty($syn_admin_logic_add_tag)) {
            try{
                /*多语言*/
                if (is_language()) {
                    $langRow = Db::name('language')->field('mark')->order('id asc')->select();
                    foreach ($langRow as $key => $val) {
                        $taglistgroup = Db::name('taglist')->field('tag,tid as id,typeid,count(tid) as total')->where(['lang'=>$val['mark']])->group('tag')->getAllWithIndex('tag');
                        $tagindexgroup = Db::name('tagindex')->field('tag,id,typeid,count(id) as total')->where(['lang'=>$val['mark']])->group('tag')->getAllWithIndex('tag');
                        if (empty($taglistgroup)) {
                            Db::name('tagindex')->where([
                                'lang'=>$val['mark'],
                            ])->delete();
                        } else {
                            empty($tagindexgroup) && $tagindexgroup = [];
                            $result = array_diff_key($taglistgroup, $tagindexgroup);
                            if (!empty($result)) {
                                $saveData = [];
                                foreach ($result as $_k => $_v) {
                                    $_v['seo_description'] = '';
                                    $_v['lang'] = $val['mark'];
                                    $_v['weekup'] = getTime();
                                    $_v['monthup'] = getTime();
                                    $_v['add_time'] = getTime();
                                    $saveData[] = $_v;
                                }
                                model('Tagindex')->insertAll($saveData);
                            }
                        }
                    }
                } else { // 单语言
                    $taglistgroup = Db::name('taglist')->field('tag,tid as id,typeid,count(tid) as total')->group('tag')->getAllWithIndex('tag');
                    $tagindexgroup = Db::name('tagindex')->field('tag,id,typeid,count(id) as total')->group('tag')->getAllWithIndex('tag');
                    if (empty($taglistgroup)) {
                        Db::name('tagindex')->where([
                            'id'  => ['egt', 1],
                        ])->delete();
                    } else {
                        empty($tagindexgroup) && $tagindexgroup = [];
                        $result = array_diff_key($taglistgroup, $tagindexgroup);
                        if (!empty($result)) {
                            $saveData = [];
                            foreach ($result as $_k => $_v) {
                                $_v['seo_description'] = '';
                                $_v['lang'] = get_main_lang();
                                $_v['weekup'] = getTime();
                                $_v['monthup'] = getTime();
                                $_v['add_time'] = getTime();
                                $saveData[] = $_v;
                            }
                            model('Tagindex')->insertAll($saveData);
                        }
                    }
                }
                /*--end*/
            }catch(\Exception $e){}
            tpCache('syn', ['syn_admin_logic_add_tag'=>1], 'cn');
        }
    }

    /**
     * 纠正会员属性的内置手机号码和邮箱地址
     */
    public function admin_logic_users_parameter()
    {
        $syn_admin_logic_users_parameter = tpCache('syn.syn_admin_logic_users_parameter', [], 'cn');
        if (empty($syn_admin_logic_users_parameter)) {
            try{
                Db::name('users_parameter')->where(['name'=>'mobile_1'])->update([
                    'dtype' => 'mobile',
                    'update_time'   => getTime(),
                ]);
                Db::name('users_parameter')->where(['name'=>'email_2'])->update([
                    'dtype' => 'email',
                    'update_time'   => getTime(),
                ]);
            }catch(\Exception $e){}
            tpCache('syn', ['syn_admin_logic_users_parameter'=>1], 'cn');
        }
    }

    /**
     * 根据下载模型自动开启会员中心的【我的下载】
     */
    public function admin_logic_users_download()
    {
        $syn_admin_logic_users_download = tpCache('syn.syn_admin_logic_users_download', [], 'cn');
        if (empty($syn_admin_logic_users_download)) {
            try{
                $status = Db::name('channeltype')->where([
                        'nid'   => 'download',
                        'is_del'    => 0,
                    ])->getField('status');
                Db::name('users_menu')->where([
                        'mca'   => 'user/Download/index',
                        'lang'  => get_main_lang(),
                    ])->update([
                        'status'    => intval($status),
                        'update_time' => getTime(),
                    ]);
            }catch(\Exception $e){}
            tpCache('syn', ['syn_admin_logic_users_download'=>1], 'cn');
        }
    }

    /**
     * 补充栏目新增的栏目顶级ID字段的值
     * @return [type] [description]
     */
    public function admin_logic_arctype_topid()
    {
        $syn_admin_logic_arctype_topid = tpCache('syn.syn_admin_logic_arctype_topid', [], 'cn');
        if (empty($syn_admin_logic_arctype_topid)) {
            $level_1_arr = Db::name('arctype')->field('id, topid')->where('grade', 1)->getAllWithIndex('id');
            if (!empty($level_1_arr)) {
                $saveData = [];
                $level_2_arr = Db::name('arctype')->field('id, parent_id')->where(['grade'=>2, 'topid'=>0])->select();
                foreach ($level_2_arr as $key => $val) {
                    $topid = !empty($level_1_arr[$val['parent_id']]) ? intval($level_1_arr[$val['parent_id']]['topid']) : 0;
                    $saveData[] = [
                        'id'    => $val['id'],
                        'topid' => $topid,
                        'update_time'   => getTime(),
                    ];
                }
                if (!empty($saveData)) {
                    $r = model('Arctype')->saveAll($saveData);
                    if ($r !== false) {
                        \think\Cache::clear("arctype");
                        tpCache('syn', ['syn_admin_logic_arctype_topid'=>1], 'cn');
                    }
                }
            }
        }

        $syn_admin_logic_arctype_topid2 = tpCache('syn.syn_admin_logic_arctype_topid2', [], 'cn');
        if (empty($syn_admin_logic_arctype_topid2)) {
            $result = Db::name('channelfield')->field('id')->where([
                    'channel_id'    => -99,
                    'name'  => 'topid',
                ])->find();
            if (!empty($result)) {
                $r = Db::name('channelfield')->where([
                        'channel_id'    => -99,
                        'name'  => 'topid',
                    ])->update([
                        'ifsystem'  => 1,
                        'update_time'   => getTime(),
                    ]);
                if ($r !== false) {
                    tpCache('syn', ['syn_admin_logic_arctype_topid2'=>1], 'cn');
                }
            }
        }
    }

    /**
     * 同步专题模型内置的附加表字段
     */
    public function admin_logic_special_addfields()
    {
        $syn_admin_logic_special_addfields = tpCache('syn.syn_admin_logic_special_addfields', [], 'cn');
        if (empty($syn_admin_logic_special_addfields)) {
            try{
                $totalRow = Db::name('channelfield_bind')->count();
                if (!empty($totalRow)) {
                    $channel_id = 7;
                    $fieldLogic = new \app\admin\logic\FieldLogic;
                    $fieldLogic->synChannelTableColumns($channel_id);

                    $addData = [];
                    $result = Db::name('channelfield')->field('id')->where(['channel_id'=>$channel_id,'ifmain'=>0])->select();
                    foreach ($result as $key => $val) {
                        $addData[] = [
                            'typeid'      => 0,
                            'field_id'    => $val['id'],
                            'add_time'    => getTime(),
                            'update_time' => getTime(),
                        ];
                    }
                    !empty($addData) && model('ChannelfieldBind')->saveAll($addData);
                }
            }catch(\Exception $e){}
            tpCache('syn', ['syn_admin_logic_special_addfields'=>1], 'cn');
        }
    }

    /**
     * 生成session会话设置文件
     */
    public function admin_logic_session_conf()
    {
        $syn_admin_logic_session_conf = tpCache('syn.syn_admin_logic_session_conf', [], 'cn');
        if (empty($syn_admin_logic_session_conf)) {
            try{
                $session_file = APP_PATH.'admin/conf/session_conf.php';
                if (!file_exists($session_file)) {
                    $session_conf = [];
                    $web_login_expiretime = tpCache('web.web_login_expiretime');
                    $session_conf['expire'] = !empty($web_login_expiretime) ? $web_login_expiretime : config('login_expire');
                    $str_session_conf = '<?php'.PHP_EOL.'$session_1600593464 = json_encode('.var_export($session_conf,true).');'.PHP_EOL.'define(\'EY_SESSION_CONF\', $session_1600593464);';
                    @file_put_contents(APP_PATH . 'admin/conf/session_conf.php', $str_session_conf);
                }
            }catch(\Exception $e){}
            tpCache('syn', ['syn_admin_logic_session_conf'=>1], 'cn');
        }
    }
}
