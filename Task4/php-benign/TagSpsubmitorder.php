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
 * Date: 2019-4-13
 */

namespace think\template\taglib\eyou;

use think\Config;
use think\Request;
use think\Db;

/**
 * 提交订单
 */
load_trait('controller/Jump');
class TagSpsubmitorder extends Base
{ 
    use \traits\controller\Jump;

    /**
     * 会员ID
     */
    public $users_id = 0;
    public $users    = [];
    
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        // 会员信息
        $this->users    = session('users');
        $this->users_id = session('users_id');
        $this->users_id = !empty($this->users_id) ? $this->users_id : 0;
    }

    /**
     * 获取提交订单数据
     */
    public function getSpsubmitorder()
    {
        // 获取解析数据
        // $querystr   = input('param.querystr/s');
        // $hash   = input('param.hash/s');
        // $auth_code = tpCache('system.system_auth_code');
        // if(!empty($querystr) && md5("payment".$querystr.$auth_code) != $hash) $this->error('无效订单！');

        // 数据解析拆分
        // parse_str(mchStrCode($querystr,'DECODE'), $querydata);
        // $aid = !empty($querydata['aid']) ? intval($querydata['aid']) : 0;
        // $num = !empty($querydata['product_num']) ? intval($querydata['product_num']) : 0;
        // $spec_value_id = !empty($querydata['spec_value_id']) ? $querydata['spec_value_id'] : '';

        // 获取解析数据
        $GetMd5 = input('param.querystr/s');
        $querystr = cookie($GetMd5);
        if(empty($querystr)) $this->error('无效链接！');

        // 赋值数据
        $product_id = $aid = !empty($querystr['aid']) ? $querystr['aid'] : 0;
        $product_num = $num = !empty($querystr['product_num']) ? $querystr['product_num'] : 0;
        $spec_value_id = !empty($querystr['spec_value_id']) ? $querystr['spec_value_id'] : '';

        if (!empty($aid)) {
            if ($num >= 1) {
                // 立即购买查询条件
                $ArchivesWhere = [
                    'a.aid'  => $aid,
                    'a.lang' => $this->home_lang,
                ];
                if (!empty($spec_value_id)) $ArchivesWhere['b.spec_value_id'] = $spec_value_id;
                
                $field = 'a.aid, a.title, a.litpic, a.users_price, a.stock_count, a.prom_type, a.attrlist_id, b.spec_price, b.spec_stock, b.spec_value_id, c.spec_is_select';
                $result['list'] = Db::name('archives')->field($field)
                    ->alias('a')
                    ->join('__PRODUCT_SPEC_VALUE__ b', 'a.aid = b.aid', 'LEFT')
                    ->join('__PRODUCT_SPEC_DATA__ c', 'a.aid = c.aid', 'LEFT')
                    ->where($ArchivesWhere)
                    ->limit('0, 1')
                    ->select();

                if (empty($result['list'][0]['spec_is_select'])) {
                    $result['list'][0]['spec_price']    = '';
                    $result['list'][0]['spec_stock']    = '';
                    $result['list'][0]['spec_value_id'] = '';
                }
                $submit_order_type = 1;
                $result['list'][0]['product_num'] = $num;
                $result['list'][0]['ProductHidden'] = '<input type="hidden" name="Md5Value" value="' . $GetMd5 . '"> <input type="hidden" name="type" value="' . $submit_order_type . '">';

                /*// 加密不允许更改的数据值
                $aid  = mchStrCode($aid, 'ENCODE');
                $num  = mchStrCode($num, 'ENCODE');
                $type = mchStrCode(1, 'ENCODE'); // 1表示直接下单购买，不走购物车
                $spec_value_id  = mchStrCode($spec_value_id, 'ENCODE');
                $result['list'][0]['ProductHidden'] = '<input type="hidden" name="aid" value="'.$aid.'"> <input type="hidden" name="num" value="'.$num.'"> <input type="hidden" name="type" value="'.$type.'"> <input type="hidden" name="spec_value_id[]" value="'.$spec_value_id.'">';*/
            } else {
                action('user/Shop/shop_under_order', false);
                exit;
            }
        } else {
            // 购物车查询条件
            $CartWhere = [
                'a.users_id' => $this->users_id,
                'a.lang'     => $this->home_lang,
                'a.selected' => 1,
            ];
            $field = 'a.*, b.aid, b.title, b.litpic, b.users_price, b.stock_count, b.prom_type, b.attrlist_id, c.spec_price, c.spec_stock, d.spec_is_select';
            $result['list'] = Db::name('shop_cart')->field($field)
                ->alias('a')
                ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                ->join('__PRODUCT_SPEC_DATA__ d', 'a.product_id = d.aid and a.spec_value_id = d.spec_value_id', 'LEFT')
                ->where($CartWhere)
                ->order('a.add_time desc')
                ->select();
            $submit_order_type = 0;
        }

        // 获取商城配置信息
        $ConfigData = getUsersConfigData('shop');

        // 如果产品数据为空则调用商城控制器的方式返回提示,中止运行
        if (empty($result['list'])) {
            action('user/Shop/shop_under_order', false);
            exit;
        }

        $controller_name = 'Product';
        $array_new = get_archives_data($result['list'],'aid');

        // 返回data拼装
        $result['data'] = [
            // 温馨提示内容,为空则不展示
            'shop_prompt'        => !empty($ConfigData['shop_prompt']) ? $ConfigData['shop_prompt'] : '',
            // 是否开启线下支付(货到付款)
            'shop_open_offline'  => !empty($ConfigData['shop_open_offline']) ? $ConfigData['shop_open_offline'] : 0,
            // 是否开启运费设置
            'shop_open_shipping' => !empty($ConfigData['shop_open_shipping']) ? $ConfigData['shop_open_shipping'] : 0,
            // 初始化总额
            'TotalAmount'        => 0,
            // 初始化总数
            'TotalNumber'        => 0,
            // 提交来源:0购物车;1直接下单
            'submit_order_type'  => $submit_order_type,
            // 1表示为虚拟订单
            'PromType'           => 1,
        ];
        $level_discount = $this->users['level_discount'];
        foreach ($result['list'] as $key => $value) {
            /* 未开启多规格则执行 */
            if (!isset($ConfigData['shop_open_spec']) || empty($ConfigData['shop_open_spec'])) {
                $value['spec_value_id'] = $value['spec_price'] = $value['spec_stock'] = 0;
                $result['list'][$key]['spec_value_id'] = $result['list'][$key]['spec_price'] = $result['list'][$key]['spec_stock'] = 0;
            }
            /* END */

            // 购物车商品存在规格并且价格不为空，则覆盖商品原来的价格
            if (!empty($value['spec_value_id']) && $value['spec_price'] >= 0) {
                // 规格价格覆盖商品原价
                $value['users_price'] = $value['spec_price'];
            }
            // 计算折扣后的价格
            if (!empty($level_discount)) {
                // 折扣率百分比 100 != $level_discount
                $discount_price = $level_discount / 100;
                // 会员折扣价
                $result['list'][$key]['users_price'] = $value['users_price'] * $discount_price;
            }

            // 购物车商品存在规格并且库存不为空，则覆盖商品原来的库存
            if (!empty($value['spec_stock'])) {
                // 规格库存覆盖商品库存
                $value['stock_count'] = $value['spec_stock'];
                $result['list'][$key]['stock_count'] = $value['spec_stock'];
            }

            if ($value['product_num'] > $value['stock_count']) {
                $result['list'][$key]['product_num'] = $value['stock_count'];
                $result['list'][$key]['stock_count'] = $value['stock_count'];
            }

            // 若库存为空则清除这条数据
            if (empty($value['stock_count'])) {
                unset($result['list'][$key]);
                continue;
            }
        }

        if (empty($result['list'])) $this->error('商品库存不足或已过期！');

        // 产品数据处理
        foreach ($result['list'] as $key => $value) {
            if ($value['users_price'] >= 0 && !empty($value['product_num'])) {
                // 计算小计
                $result['list'][$key]['subtotal'] = sprintf("%.2f", $value['users_price'] * $value['product_num']);
                // 计算合计金额
                $result['data']['TotalAmount'] += $result['list'][$key]['subtotal'];
                $result['data']['TotalAmount'] = sprintf("%.2f", $result['data']['TotalAmount']);
                // 计算合计数量
                $result['data']['TotalNumber'] += $value['product_num'];
                // 判断订单类型，目前逻辑：一个订单中，只要存在一个普通产品(实物产品，需要发货物流)，则为普通订单
                // 0表示为普通订单，1表示为虚拟订单，虚拟订单无需发货物流，无需选择收货地址，无需计算运费
                if (empty($value['prom_type'])) {
                    $result['data']['PromType'] = '0';
                }
            }

            // 产品页面链接
            $result['list'][$key]['arcurl'] = urldecode(arcurl('home/'.$controller_name.'/view', $array_new[$value['aid']]));

            // 图片处理
            $result['list'][$key]['litpic'] = handle_subdir_pic(get_default_pic($value['litpic']));
             
            // 若不存在则重新定义,避免报错
            if (empty($result['list'][$key]['ProductHidden'])) {
                $result['list'][$key]['ProductHidden'] = '<input type="hidden" name="spec_value_id[]" value="'.$value['spec_value_id'].'">';
            }

            // 产品旧参数属性处理
            $result['list'][$key]['attr_value'] = '';
            if (!empty($value['aid'])) { 
                $attrData   = Db::name('product_attr')->where('aid', $value['aid'])->field('attr_value, attr_id')->select();
                foreach ($attrData as $val) {
                    $attr_name  = Db::name('product_attribute')->where('attr_id',$val['attr_id'])->field('attr_name')->find();
                    $result['list'][$key]['attr_value'] .= $attr_name['attr_name'].'：'.$val['attr_value'].'<br/>';
                }
            }

            // 规格处理
            $result['list'][$key]['product_spec'] = '';
            if (!empty($value['spec_value_id'])) {
                $spec_value_id = explode('_', $value['spec_value_id']);
                if (!empty($spec_value_id)) {
                    $SpecWhere = [
                        'aid'           => $value['aid'],
                        'lang'          => $this->home_lang,
                        'spec_value_id' => ['IN',$spec_value_id]
                    ];
                    $ProductSpecData = Db::name("product_spec_data")->where($SpecWhere)->field('spec_name, spec_value')->select();
                    foreach ($ProductSpecData as $spec_value) {
                        $result['list'][$key]['product_spec'] .= $spec_value['spec_name'].'：'.$spec_value['spec_value'].'<br/>';
                    }
                }
            }
        }
        
        // 封装初始金额隐藏域
        $result['data']['TotalAmountOld'] = '<input type="hidden" id="TotalAmount_old" value="'.$result['data']['TotalAmount'].'">';
        // 封装订单支付方式隐藏域
        $result['data']['PayTypeHidden']  = '<input type="hidden" name="payment_method" id="payment_method" value="0">';
        // 封装添加收货地址JS
        if (isWeixin() && !isWeixinApplets()) {
            $result['data']['ShopAddAddr'] = " onclick=\"GetWeChatAddr();\" ";
            $data['shop_add_address']        = url('user/Shop/shop_get_wechat_addr');
        }else{
            $result['data']['ShopAddAddr']  = " onclick=\"ShopAddAddress();\" ";
            $data['shop_add_address']       = url('user/Shop/shop_add_address');
        }

        // 封装UL的ID,用于添加收货地址
        $result['data']['UlHtmlId']       = " id=\"UlHtml\" ";
        // 封装选择支付方式JS
        $result['data']['OnlinePay']      = " onclick=\"ColorS('zxzf')\" id=\"zxzf\"  ";
        $result['data']['DeliveryPay']    = " onclick=\"ColorS('hdfk')\" id=\"hdfk\"  ";
        // 封装运费信息
        if (empty($result['data']['shop_open_shipping'])) {
            $result['data']['Shipping'] = " 免运费 ";
        }else{
            $result['data']['Shipping'] = " <span id=\"template_money\">￥0.00</span> ";
        }
        // 封装全部产品总额ID,用于计算总额
        $result['data']['TotalAmountId'] = " id=\"TotalAmount\" ";
        // 封装返回购物车链接
        $result['data']['ReturnCartUrl'] = url('user/Shop/shop_cart_list');
        // 封装提交订单JS
        $result['data']['ShopPaymentPage'] = " onclick=\"ShopPaymentPage();\" ";
        // 封装表单验证隐藏域
        static $request = null;
        if (null == $request) { $request = Request::instance(); }  
        $token = $request->token();
        $result['data']['TokenValue'] = " <input type=\"hidden\" name=\"__token__\" value=\"{$token}\"/> ";

        // 传入JS参数
        $data['shop_edit_address'] = url('user/Shop/shop_edit_address');
        $data['shop_del_address']  = url('user/Shop/shop_del_address');
        $data['shop_inquiry_shipping']  = url('user/Shop/shop_inquiry_shipping');
        $data['shop_payment_page'] = url('user/Shop/shop_payment_page');
        if (isWeixin() || isMobile()) {
            $data['addr_width']  = '100%';
            $data['addr_height'] = '100%';
        }else{
            $data['addr_width']  = '350px';
            $data['addr_height'] = '480px';
        }
        $data_json = json_encode($data);
        $version   = getCmsVersion();
        // 循环中第一个数据带上JS代码加载
        $result['data']['hidden'] = <<<EOF
<script type="text/javascript">
    var b1decefec6b39feb3be1064e27be2a9 = {$data_json};
</script>
<script type="text/javascript" src="{$this->root_dir}/public/static/common/js/tag_spsubmitorder.js?v={$version}"></script>
EOF;

        if (empty($result['list'])) {
            action('user/Shop/shop_under_order', false);
            exit;
        }

        return $result;
    }
}