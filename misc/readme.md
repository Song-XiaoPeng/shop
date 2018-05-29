### 客户相关
1. u_customer                  客户基础表
phone_no(手机号) password nickname(昵称) openid avator ip

### 管理员相关
2. s_manager                   管理员基础表
username(用户名) password(密码) role_id(角色id) is_forbidden(是否禁止)

4. g_goods

### 基础设置表
s_system_setting
1. sms_account sms_pwd 短信网关账号
2. appid app_secret app_token 公众号appid

### 商品表
g_goods
name(商品名称) amount(商品数量) price(商品价格) detail(商品详情) pic(商品图片)

### 分类表
g_categroy
name(分类名称) 

### 商品与分类关联表(多对多)
g_goods_category
goods_id category_id

### 订单表
o_order
- 字段
    订单号 
    订单状态 未支付 已支付未发货 已发货 已签收

流程：
- 加入购物车：
1. 加入购物车-》选择数量

2. 直接购买-》选择购买数量-》提交订单（首先在浏览器端判断是否购买数量超过库存，超过的话提示超过购买数量）-》（购买数量小于库存）调出支付界面

3. 购物车界面-》提交订单

- 提交订单
1. 选择：商品id、商品购买数量amount

2. 点击提交订单，调后台提交订单接口

3. 提交订单接口：
    1. 查看订单的对应商品的库存
    2. 根据订单数量和库存，查看是否库存不足-》如果库存不足，则生成订单失败
    3. 订单对应商品库存充足-》生成订单成功-》将订单详情保存至订单表-》生成唯一订单号
    4. 返回订单号

### 支付
流程：
1. 提交订单后，调出支付界面
2. 支付成功扣减相应库存
3. 支付失败-》生成订单-》供下次支付








直接购买-》调支付界面


















