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
 * Date: 2019-06-25
 */

namespace app\user\logic;

use think\Model;
use think\Db;
use think\Request;
use think\Config;

/**
 * 支付宝回调逻辑处理
 * @package user\Logic
 */
class PayLogic extends Model
{
    private $home_lang = 'cn';

    /**
     * 初始化操作
     */
    public function initialize() {
        parent::initialize();

        $this->home_lang             = get_home_lang();
        $this->users_db              = Db::name('users');               // 会员数据表
        $this->users_money_db        = Db::name('users_money');         // 会员金额明细表
        $this->shop_order_db         = Db::name('shop_order');          // 订单主表
        $this->shop_order_details_db = Db::name('shop_order_details');  // 订单明细表
        $this->users_type_manage_db  = Db::name('users_type_manage');   // 会员升级分类价格表
    }

    public function alipay_return()
    {
        if (!empty($_POST)) {
            foreach($_POST as $key => $value){
                $_GET[$key] = $value;
            }
        }
        $param = $data = $_GET;

        // 支付宝配置信息
        $where = [
            'pay_id' => 2,
            'pay_mark' => 'alipay'
        ];
        $pay_alipay_config = Db::name('pay_api_config')->where($where)->getField('pay_info');
        if (empty($pay_alipay_config)) {
            $pay_alipay_config = getUsersConfigData('pay.pay_alipay_config');
            if (empty($pay_alipay_config)) return false;
        }
        $pay_alipay_config = unserialize($pay_alipay_config);

        // 新旧版处理
        switch ($pay_alipay_config['version']) {
            // 新版支付宝
            case '0':
                // 新版获取RSA2加密返回，返回bool值
                $Return = $this->GetNewAliPayRsa2Return($data, $pay_alipay_config);
                if (!empty($Return)) {
                    $return = $this->NewAliPayProcessing($param);
                    if (1 == $param['is_ailpay_notify']) {
                        echo $return; exit;
                    }else{
                        return $return;
                    }
                }else{
                    if (1 == $param['is_ailpay_notify']) {
                        echo 'fail'; exit;
                    }else{
                        $msg = [
                            'code' => 0,
                            'msg'  => '订单验证失败！',
                        ];
                        return $msg;
                    }                      
                }
                break;
            
            // 旧版支付宝
            case '1':
                // 旧版获取MD5加密的sign
                $Sign = $this->GetOldAliPayMd5Sign($data, $pay_alipay_config['code']);
                if ($Sign == $data['sign']){;
                    $return = $this->OldAliPayProcessing($param);
                    if (1 == $param['is_ailpay_notify']) {
                        echo $return; exit;
                    }else{
                        return $return;
                    }
                }else{
                    if (1 == $param['is_ailpay_notify']) {
                        echo "fail"; exit;
                    }else{
                        $msg = [
                            'code' => 0,
                            'msg'  => '订单验证失败！',
                        ];
                        return $msg;
                    }
                }
                break;
        }
    }

    // 新版
    private function NewAliPayProcessing($param = array())
    {   
        // 实际付款金额
        $order_amount = $param['total_amount'];
        if ('2' == $param['transaction_type']) {
            // 商城订单购买支付回调处理
            $return = $this->ShopOrderProcessing($param, $order_amount);
            return $return;

        }else if ('1' == $param['transaction_type']) {
            // 会员充值或升级支付回调处理
            $return = $this->MoneyOrderProcessing($param, $order_amount);
            return $return;
        }
    }

    // 旧版
    private function OldAliPayProcessing($param = array())
    {   
        // 实际付款金额
        $order_amount = $param['total_fee'];
        if ('2' == $param['transaction_type']) {
            // 商城订单购买支付回调处理
            $return = $this->ShopOrderProcessing($param, $order_amount);
            return $return;

        }else if ('1' == $param['transaction_type']) {
            // 会员充值或升级支付回调处理
            $return = $this->MoneyOrderProcessing($param, $order_amount);
            return $return;
        }
    }

    // 商城订单购买支付回调处理
    private function ShopOrderProcessing($param = array(), $order_amount = null)
    {
        if (!empty($param['out_trade_no']) && !empty($param['trade_no'])) {
            $OrderWhere = [
                'lang'       => $this->home_lang,
                // 订单号
                'order_code' => $param['out_trade_no'],
                // 实际支付金额
                'order_amount' => $order_amount,
            ];
            $OrderData = $this->shop_order_db->where($OrderWhere)->find();
            if (!empty($OrderData)) {
                // 支付宝付款成功后，订单并未修改状态时，修改订单状态并返回
                if (0 == $OrderData['order_status']) {
                    // 当前时间
                    $time = getTime();
                    // 将会员ID追加到条件中
                    $OrderWhere['users_id'] = $OrderData['users_id'];
                    // 更新商城订单主表
                    $UpOrderData = [
                        'order_status' => 1,
                        'pay_name'     => 'alipay', // 支付宝支付
                        'wechat_pay_type' => '',    // 清空微信标记
                        'pay_details'  => serialize($param),
                        'pay_time'     => $time,
                        'update_time'  => $time,
                    ];
                    $ReturnId = $this->shop_order_db->where($OrderWhere)->update($UpOrderData);
                    if (!empty($ReturnId)) {
                        // 更新商城订单副表
                        $DetailsData['update_time'] = $time;
                        $this->shop_order_details_db->where('order_id',$OrderData['order_id'])->update($DetailsData);
                        // 添加订单操作记录
                        AddOrderAction($OrderData['order_id'],$OrderData['users_id'],'0','1','0','1','支付成功！','会员使用支付宝完成支付！');
                        if (1 == $param['is_ailpay_notify']) {
                            return "success";
                        }else{
                            $msg = [
                                'code' => 1,
                                'msg'  => '订单支付完成！',
                                'url'  => url('user/Shop/shop_centre'),
                            ];
                            return $msg;
                        }
                    }
                }else{
                    if (1 == $param['is_ailpay_notify']) {
                        return "success";
                    }else{
                        $msg = [
                            'code' => 1,
                            'msg'  => '订单支付完成！',
                            'url'  => url('user/Shop/shop_centre'),
                        ];
                        return $msg;
                    }
                }
            }
        }

        if (1 == $param['is_ailpay_notify']) {
            return "fail";
        }else{
            $msg = [
                'code' => 0,
                'msg'  => '订单处理失败，如已确认付款，请联系管理员！',
                'url'  => '',
            ];
            return $msg;
        }
    }

    // 会员充值或升级支付回调处理
    private function MoneyOrderProcessing($param = array(), $order_amount = null)
    {
        if (!empty($param['out_trade_no']) && !empty($param['trade_no'])) {
            // 付款成功
            $MoneyWhere = [
                'lang'         => $this->home_lang,
                // 实际付款金额
                'money'        => $order_amount,
                // 订单号
                'order_number' => $param['out_trade_no'],
            ];
            $MoneyData = $this->users_money_db->where($MoneyWhere)->find();
            // 支付宝订单统一处理
            $msg = $this->MoneyUnifiedProcessing($param, $MoneyData, $order_amount);
            return $msg;
        }

        if (1 == $param['is_ailpay_notify']) {
            return "fail";
        }else{
            $msg = [
                'code' => 1,
                'msg'  => '订单处理失败，如已确认付款，请联系管理员！',
                'url'  => '',
            ];
            return $msg;
        }
    }

    // 支付宝订单处理流程
    // 参数1为支付宝返回数据集
    // 参数2为充值记录表数据集
    // 参数3为订单实际付款金额
    private function MoneyUnifiedProcessing($param, $MoneyData, $PayMoney){
        // 支付宝付款成功后，订单并未修改状态时，修改订单状态并返回
        if ($MoneyData['status'] == 1) {
            // 当前时间
            $time = getTime();
            // 更新条件
            $where = [
                'moneyid'  => $MoneyData['moneyid'],
                'users_id' => $MoneyData['users_id'],
            ];
            // 更新数据
            $UpMoneyData = [
               'status'          => 2,
               'pay_method'      => 'alipay',
               'wechat_pay_type' => '',
               'pay_details'     => serialize($param),
               'update_time'     => $time,
            ];
            // 若类型为会员升级则删除订单详情
            if (0 == $MoneyData['cause_type']) {
                unset($UpMoneyData['pay_details']);
            }
            $ReturnId = $this->users_money_db->where($where)->update($UpMoneyData);
            if (!empty($ReturnId)) {
                $UpUsersData = [];
                $ReturnId    = '';
                if (1 == $MoneyData['cause_type']) {
                    // 会员充值
                    // 更新会员金额
                    $UpUsersData['users_money'] = Db::raw('users_money+'.$PayMoney);
                    $UpUsersData['update_time'] = $time;
                }else if (0 == $MoneyData['cause_type']) {
                    // 会员升级
                    // 更新会员级别和天数
                    $UpUsersData = $this->GetUsersUpgradeData($MoneyData, $UsersData);
                }

                if (!empty($UpUsersData)) {
                    $ReturnId = $this->users_db->where('users_id',$MoneyData['users_id'])->update($UpUsersData);
                }
                if (!empty($ReturnId)) {
                    if (1 == $MoneyData['cause_type']) {
                        // 业务处理完成，订单已完成
                        $UpMoneyData_ = [
                            'status'      => 3,
                            'update_time' => $time,
                        ];
                        $this->users_money_db->where($where)->update($UpMoneyData_);
                    }
                    if (1 == $param['is_ailpay_notify']) {
                        return "success";
                    }else{
                        $msg = [
                            'code' => 1,
                            'msg'  => '支付完成',
                            'url'  => url('user/Level/level_centre'),
                        ];
                        return $msg;
                    }
                }
            }
            
            if (1 == $param['is_ailpay_notify']) {
                return "success";
            }else{
                $msg = [
                    'code' => 0,
                    'msg'  => '支付成功，系统未处理成功，请联系管理员',
                    'url'  => '',
                ];
                return $msg;
            }
        }

        if (1 == $param['is_ailpay_notify']) {
            return "success";
        }else{
            $msg = [
                'code' => 1,
                'msg'  => '支付完成',
                'url'  => url('user/Level/level_centre'),
            ];
            return $msg;
        }
    }

    // 获取会员升级更新数组
    private function GetUsersUpgradeData($MoneyData = array())
    {
        $time = getTime();
        // 会员期限定义数组
        $limit_arr = Config::get('global.admin_member_limit_arr');
        // 查询会员升级级别
        $MoneyDataCause = unserialize($MoneyData['cause']);
        $type = $this->users_type_manage_db->where('level_id',$MoneyDataCause['level_id'])->getField('limit_id');
        // 到期天数
        $maturity_days = $limit_arr[$type]['maturity_days'];

        // 更新会员属性表的数组
        $result = [
            'level'       => $MoneyDataCause['level_id'],
            'update_time' => $time,
            'level_maturity_days' => Db::raw('level_maturity_days+'.($maturity_days)),
        ];

        // 查询会员开通会员级别时间和天数
        $UsersData = $this->users_db->field('open_level_time,level_maturity_days')->find($MoneyData['users_id']);
        // 36600为终身天数，若数据库中的值大于则不执行，反之执行
        if ($UsersData['level_maturity_days'] < '36600') {
            // 计算逻辑，会员开通的时间戳+(会员到期天数*每天的秒数)
            $maturity_time = $UsersData['open_level_time'] + ($UsersData['level_maturity_days'] * 86400);
            // 判断是否到期，到期则执行
            if ($maturity_time < $time) {
                // 会员已到期，追加数组
                $result['open_level_time']     = $time;
                $result['level_maturity_days'] = $maturity_days;
            }
        }

        return $result;
    }

    // 旧版加密方式,验证订单是否正确
    private function GetOldAliPayMd5Sign($param = array(), $code = null)
    {
        // 对关联数组按照键名进行升序排序
        ksort($param);
        reset($param);

        // 去除指定参数并拼装成字符串
        $sign = '';
        foreach ($param as $key => $value)
        {
            if ($key != 'sign' && $key != 'sign_type' && $key != 'transaction_type' && $key != 'is_ailpay_notify' && $key != 'm' && $key != 'c' && $key != 'a')
            {
                $sign .= "$key=$value&";
            }
        }

        // 参数拼装处理并加密为MD5返回
        $sign = md5(substr($sign, 0, -1).$code);
        return $sign;
    }

    // 新版加密方式,验证订单是否正确
    private function GetNewAliPayRsa2Return($data = array(), $pay_alipay_config = array())
    {
        // 参数拼装
        $config = [
            'app_id'               => $pay_alipay_config['app_id'],
            'charset'              => 'UTF-8',
            'sign_type'            => 'RSA2',
            'gatewayUrl'           => 'https://openapi.alipay.com/gateway.do',
            'alipay_public_key'    => $pay_alipay_config['alipay_public_key'],
            'merchant_private_key' => $pay_alipay_config['merchant_private_key'],
        ];

        // 引入支付宝SDK
        vendor('alipay.pagepay.service.AlipayTradeService');
        // 实例化
        $alipaySevice = new \AlipayTradeService($config);

        // 删除参数
        unset($data['m']);
        unset($data['c']);
        unset($data['a']);
        unset($data['transaction_type']);
        unset($data['is_ailpay_notify']);

        // 获取返回值
        $return = $alipaySevice->check($data);
        return $return;
    } 
}