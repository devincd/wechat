<?php 
namespace Home\Controller;
use Think\Controller;
define("TOKEN","weixin");
class WechatController extends Controller {
    
    protected $User;     //微信用户对象  
    /*微信入口
     *author:caodi
     *date:2015-09-22
     */
    public function wechat() {
        DLOG("微信入口记录的时间","run","caodi");
        if ($_GET['echostr'] != NULL ) {  
            echo $_GET['echostr'];
            exit;
        }
        //微信只会在第一次在URL中带echostr参数，以后就不会带这个参数了
        if ($this->checkSignature()) { //success!
            $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
            //extract post data
            if (!empty($postStr)) {
                libxml_disable_entity_loader(true);
                $postObj = simplexml_load_string($postStr,"SimpleXMLElement",LIBXML_NOCDATA);
                $this->$User = $postObj;
                //根据消息类型将信息分发
               // $this->route($postObj);
                //exit;

                //以下为测试用的
                $toUsername = $postObj->ToUserName;
                $fromUsername = $postObj->FromUserName;
                $keyword = trim($postObj->Content);
                $msyType = trim($postObj->MsgType); //消息类型
                $event = trim($postObj->Event); //事件类型
                $time = time();
                $result = json_encode($postObj);
                DLOG("消息的参数".$result,"run","caodi");
                $textTpl = "<xml>
                            <ToUserName><![CDATA[%s]]></ToUserName>
                            <FromUserName><![CDATA[%s]]></FromUserName>
                            <CreateTime>%s</CreateTime>
                            <MsgType><![CDATA[%s]]></MsgType>
                            <Content><![CDATA[%s]]></Content>
                            <FuncFlag>0</FuncFlag>
                            </xml>";
                if ($keyword == "?" || $keyword == "？") {
                    $msgType = "text";
                    $contentStr = date("Y-m-d H:i:s",time());
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    echo $resultStr;
                }
            }
        } else {
            echo "error";
        }
    }

    /*wechat身份验证
     *author:caodi
     *date:2015-09-22
     */
    public function checkSignature() {
        //you must define TOKEN by yourself
        if (!defined("TOKEN")) {
            throw new Exception("TOKEN is not defined!");
        }
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce     = $_GET["nonce"];

        $token = TOKEN;
        $tmpArr = array($token,$timestamp,$nonce);
        sort($tmpArr,SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }

    }

    /*根据微信的消息类型来进行的分发
     *author:caodi
     *date:2015-09-23
     */
    public function route($postObj) {
        $msgType = trim($postObj->MsgType);
        switch ($msgType) {
            //(1)接受的为消息推送
            case "text":
                $this->reponse_text($postObj);
                break;
            case "image":
                $this->reponse_image($postObj);
                break;
            case "voice":
                $this->reponse_voice($postObj);
                break;
            //(2)接受的为事件推送
            case "event":
                $event = $postObj->Event;
                switch ($event) {
                    case "subscribe":
                        $this->subscribe($postObj);
                        break;
                    case "unsubscribe":
                        $this->unsubscribe($postObj);
                        break;
                    //自定义菜单的事件功能
                }

        }
    }

    /*微信用户关注微信号事件(获取用户的基本信息存入到用户表中去)
     *author:caodi
     *date:2015-09-23
     */
    public function subscribe($postObj) {
        $open_id = $postObj->OpenID;
        $create_time = $postObj->CreateTime;
        $UserDao = M("t_user");
        //(1)根据用户的open_id去 https://api.weixin.qq.com/cgi-bin/user/info?access_token=ACCESS_TOKEN&openid=OPENID&lang=zh_CN
        $access_token = get_access_token();
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$open_id."&lang=zh_CN";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //要求保存的结果到字符串还是输出在屏幕上，非0表示保存到字符串中
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //对认证来源的检查，0表示阻止对证书的合法性检查
        $result = curl_exec($ch);
        curl_close($ch);
        $user_info = json_decode($result,true);
        //(2)将得到的用户信息保存到数据库中去
    }

    /*自定义菜单的生成
     *author:caodi
     *date:2015-09-24
     */
    public function create_menu() {
        include_once(APP_PATH."Admin/Conf/menu_config.php");
        $data = $menu_config;
        $access_token = "tTcvmh5G3ntyQfHIx-EIyxCjorpkwDZ7uNdohDzDnrEXpQtR4O00YTHR7mYm0dsIvzpa29cqhVIpNjSI1C5KRLzM-niuXK07HEtt6Blzazk";
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        var_dump($result);
        exit;
    }

    /*通过OAuth2.0的网页授权(自定义菜单中，获取用户的openID同时进入我的任务页)
     *author:caodi
     *date:2015-09-24
     */
    public function my_task () {
        $code = $_GET['code'];
        $oprn_id = $this->code_to_openID($code);
        echo "<center><h1>{$open_id}</h1></center>";
    }

    /*由OAuth2.0获取到的code转化成用户的openID
     *author:caodi
     *date:2015-09-24
     */
    public function code_to_openID($code) {
        if (empty($code) == true) {
            DLOG("获取的code为空","run","caodi");
            exit;
        }
        $appid = "wx66808704f67b0f0a";
        $secret = "c0613151a084c9af308d958a534ff27f";
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$appid."&secret=".$secret."&code=".$code."&grant_type=authorization_code";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        $user_info = json_decode($result,true);
        $open_id = $user_info['openid'];
        return $open_id;
    }

}
?>