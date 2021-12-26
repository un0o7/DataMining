<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 易而优团队 by 陈风任 <491085389@qq.com>
 * Date: 2020-03-31
 */

namespace app\common\logic;

/**
 * Description of SmsLogic
 *
 * 短信类
 */
class SmsLogic 
{
    private $config;
    
    public function __construct($sms_config = []) 
    {
        $this->config = !empty($sms_config) ? $sms_config : tpCache('sms');
    }

    /**
     * 发送短信逻辑
     * @param unknown $source
     */
    public function sendSms($source = null, $sender = null, $params = [], $unique_id = 0)
    {
        $smsTemp = M('sms_template')->where("send_scene", $source)->find();
        if (empty($smsTemp) || empty($smsTemp['sms_sign']) || empty($smsTemp['sms_tpl_code'])|| empty($smsTemp['tpl_content'])){
            return $result = ['status' => -1, 'msg' => '尚未正确配置短信模板，请联系管理员！'];
        }
        if (0 == $smsTemp['is_open']) return $result = ['status' => -1, 'msg' => '模板类型已关闭，请先开启'];

        $content = !empty($params['content']) ? $params['content'] : false;
        $code    = !empty($params['code']) ? $params['code'] : $content;

        if(empty($unique_id)){
            $session_id = session_id();
        }else{
            $session_id = $unique_id;
        }

        if (strpos($smsTemp['tpl_content'], 'code') !== false) { 
            $smsParams = array(
                0 => "{\"code\":\"$code\"}",
                1 => "{\"code\":\"$code\"}",
                2 => "{\"code\":\"$code\"}",
                3 => "{\"code\":\"$code\"}",
                4 => "{\"code\":\"$code\"}",
                5 => "{\"code\":\"$code\"}",
            );
        } else if (strpos($smsTemp['tpl_content'], 'content') !== false) {
            $smsParams = array(
                0 => "{\"content\":\"$content\"}",
                1 => "{\"content\":\"$content\"}",
                2 => "{\"content\":\"$content\"}",
                3 => "{\"content\":\"$content\"}",
                4 => "{\"content\":\"$content\"}",
                5 => "{\"content\":\"$content\"}",
            );
        } else if (strpos($smsTemp['tpl_content'], 'name') !== false) {
            $smsParams = array(
                0 => "{\"name\":\"$name\"}",
                1 => "{\"name\":\"$name\"}",
                2 => "{\"name\":\"$name\"}",
                3 => "{\"name\":\"$name\"}",
                4 => "{\"name\":\"$name\"}",
                5 => "{\"name\":\"$name\"}",
            );
        }
        $smsParam = $smsParams[$source];

        //提取发送短信内容
        $msg = $smsTemp['tpl_content'];
        $params_arr = json_decode($smsParam);
        foreach ($params_arr as $k => $v) {
            $msg = str_replace('${' . $k . '}', $v, $msg);
        }

        //发送记录存储数据库
        $log_id = M('sms_log')->insertGetId(array('source' => $source, 'mobile' => $sender, 'code' => $code, 'add_time' => time(), 'status' => 0, 'msg' => $msg, 'is_use' => 0, 'error_msg'=>''));
        if ($sender != '' && check_mobile($sender)) {
            // 如果是正常的手机号码才发送
            try {
                $resp = $this->realSendSms($sender, $smsTemp['sms_sign'], $smsParam, $smsTemp['sms_tpl_code']);
            } catch (\Exception $e) {
                $resp = ['status' => -1, 'msg' => $e->getMessage()];
            }
            if ($resp['status'] == 1) {
                // 修改发送状态为成功
                M('sms_log')->where(array('id' => $log_id))->save(array('status' => 1));
            } else {
                // 发送失败, 将发送失败信息保存数据库
                M('sms_log')->where(array('id' => $log_id))->update(array('error_msg'=>$resp['msg']));
            }
            return $resp;
        } else {
           return $result = ['status' => -1, 'msg' => '接收手机号不正确['.$sender.']'];
        }
        
    }

    private function realSendSms($mobile, $smsSign, $smsParam, $templateCode)
    {
        if (config('sms_debug') == true) {
            return array('status' => 1, 'msg' => '专用于越过短信发送');
        }
        $type = (int)$this->config['sms_platform'] ?: 1;
        switch($type) {
            case 1:
                $result = $this->sendSmsByAliyun($mobile, $smsSign, $smsParam, $templateCode);
                break;
            default:
                $result = ['status' => -1, 'msg' => '不支持的短信平台'];
        }
        
        return $result;
    }

    /**
     * 发送短信（阿里云短信）
     * @param $mobile  手机号码
     * @param $code    验证码
     * @return bool    短信发送成功返回true失败返回false
     */
    private function sendSmsByAliyun($mobile, $smsSign, $smsParam, $templateCode)
    {
        include_once './vendor/aliyun-php-sdk-core/Config.php';
        include_once './vendor/Dysmsapi/Request/V20170525/SendSmsRequest.php';
        
        $accessKeyId = $this->config['sms_appkey'];
        $accessKeySecret = $this->config['sms_secretkey'];
        if (empty($accessKeyId) || empty($accessKeySecret)){
            return array('status' => -1, 'msg' => '请设置短信平台appkey和secretKey');
        }
        //短信API产品名
        $product = "Dysmsapi";
        //短信API产品域名
        $domain = "dysmsapi.aliyuncs.com";
        //暂时不支持多Region
        $region = "cn-hangzhou";

        //初始化访问的acsCleint
        $profile = \DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
        \DefaultProfile::addEndpoint("cn-hangzhou", "cn-hangzhou", $product, $domain);
        $acsClient= new \DefaultAcsClient($profile);

        $request = new \Dysmsapi\Request\V20170525\SendSmsRequest;
        //必填-短信接收号码
        $request->setPhoneNumbers($mobile);
        //必填-短信签名
        $request->setSignName($smsSign);
        //必填-短信模板Code
        $request->setTemplateCode($templateCode);
        //选填-假如模板中存在变量需要替换则为必填(JSON格式)
        $request->setTemplateParam($smsParam);
        //选填-发送短信流水号
        //$request->setOutId("1234");
        
        //发起访问请求
        $resp = $acsClient->getAcsResponse($request);
        
        //短信发送成功返回True，失败返回false
        if ($resp && $resp->Code == 'OK') {
            return array('status' => 1, 'msg' => $resp->Code);
        } else {
            return array('status' => -1, 'msg' => $resp->Message . '. Code: ' . $resp->Code);
        }
    }
} 