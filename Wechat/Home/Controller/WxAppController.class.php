<?php 
namespace Home\Controller;
use Think\Controller;
/*
 *微信的页面
 */
class WxAppController extends Controller {
    protected $app_id;  //微信账号id
    protected $secret;  //微信公众号的密钥
    
    /*通用方法，此类的入口文件
     *author:caodi
     *date:2015-09-28
     */
    public function _initialize() {
        $this->app_id = C("APPID");
        $this->secret = C("APPSECRET");
    }

    /*微信页面的入口文件(我的页面)
     *author:caodi
     *date:2015-09-28
     */
    public function my_info() {
        $this->display("Index/index");
    }
}
?>