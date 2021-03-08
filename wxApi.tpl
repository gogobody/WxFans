<?php
/**
  * cnwper.com MP Api
  */

//define your token
define("CNWPER_WEIXIN_TPL_TOKEN", "{{ CNWPER_WEIXIN_TPL_TOKEN }}");
define("CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_PATH", {{ CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_PATH }});
define("CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_FILENAME", "{{ CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_FILENAME }}");
define("CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_EXPIRE", {{ CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_EXPIRE }}*60);
define("CNWPER_WEIXIN_TPL_WEIXIN_REPLY_KEYWORD", "{{ CNWPER_WEIXIN_TPL_WEIXIN_REPLY_KEYWORD }}");
define("CNWPER_WEIXIN_TPL_WEIXIN_REPLY_TEMPLATE", "{{ CNWPER_WEIXIN_TPL_WEIXIN_REPLY_TEMPLATE }}");
define("CNWPER_WEIXIN_TPL_HOME_URL", "{{ CNWPER_WEIXIN_TPL_HOME_URL }}");
define('CNWPER_WEIXIN_TPL_COOKIE_NAME', "{{ CNWPER_WEIXIN_TPL_COOKIE_NAME }}");
define('CNWPER_WEIXIN_TPL_CODE_TYPE', "{{ CNWPER_WEIXIN_TPL_CODE_TYPE }}");
define('CNWPER_WEIXIN_TPL_CODE_LEN', "{{ CNWPER_WEIXIN_TPL_CODE_LEN }}");


$wechatObj = new WxApi();

// 是否需要校验安全白名单设置
if(!isset($_GET["echostr"])){
    if (isset($_GET["cnwper"]) && $_GET['cnwper'] === 'check_captcha') {
        if (isset($_POST["captcha"]) && strlen(trim($_POST["captcha"]))===CNWPER_WEIXIN_TPL_CODE_LEN) {
            $captcha = new CaptchaApi();
            $check = $captcha->check(trim(strip_tags($_POST["captcha"])));
            if ($check) {
                // 有效期默认设置10年，需要调整修改+之后的数字即可，单位(秒)
                setcookie(
                    CNWPER_WEIXIN_TPL_COOKIE_NAME,
                    md5(CNWPER_WEIXIN_TPL_TOKEN . CNWPER_WEIXIN_TPL_COOKIE_NAME . 'cnwper.com'),
                    time()+86400*365*10,
                    '/'
                );
                exit("200");
            } else {
                exit("400");
            }  // 需要做数据校验
        } else {
            exit("300");  // 不存在 captcha 参数 或 captcha 字数不对
        }
    } else if (isset($_GET["url_captcha"]) && $_GET['url_captcha'] === 'get_captcha') {
        $captcha = new CaptchaApi();
        $captcha_code = $captcha->generate();
        if ( $captcha_code!==False ) {
            ?>

            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0">
            </head>
            <style>
                *{margin: 0;padding: 0;}

                .text{ margin: 50px auto; padding: 10px;line-height:160%; color: #666;}
                .text span{font-weight:600;color:#393D49;}
            </style>
            <body>
                <p class="text">
            <?php
                echo sprintf(CNWPER_WEIXIN_TPL_WEIXIN_REPLY_TEMPLATE, '<span>' . $captcha_code . '</span>', '<span>' . CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_EXPIRE/60 . '</span>');
            ?>
                </p>
            </body>
            </html>
        <?php
        } else {
            echo "验证码生成出错，请联系管理员解决。";
        }
    } else {
        $wechatObj->responseMsg();
    }
} else {
	$wechatObj->valid();
}


class WxApi
{
    public function valid()
    {
        $echoStr = $_GET["echostr"];

        //valid signature , option
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }

    public function responseMsg()
    {
        //get post data, May be due to the different environments
        $postStr = file_get_contents('php://input');
		
        //extract post data
        if (!empty($postStr)){
                /* libxml_disable_entity_loader is to prevent XML eXternal Entity Injection,
                   the best way is to check the validity of xml by yourself */
                libxml_disable_entity_loader(true);
                $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                $fromUsername = $postObj->FromUserName;
                $toUsername = $postObj->ToUserName;
                $msgType = $postObj->MsgType;
                $keyword = trim($postObj->Content);
                $time = time();

                if(!empty( $keyword ) && $keyword == CNWPER_WEIXIN_TPL_WEIXIN_REPLY_KEYWORD && $msgType == 'text')
                {
                    $textTpl = "<xml>
  <ToUserName><![CDATA[%s]]></ToUserName>
  <FromUserName><![CDATA[%s]]></FromUserName>
  <CreateTime>%s</CreateTime>
  <MsgType><![CDATA[%s]]></MsgType>
  <Content><![CDATA[%s]]></Content>
</xml>";
                    $msgType = "text";

                    $captchaObj = new CaptchaApi();
                    $captcha = $captchaObj->generate();
                    if($captcha){
                        $contentStr = sprintf(CNWPER_WEIXIN_TPL_WEIXIN_REPLY_TEMPLATE, $captcha, CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_EXPIRE/60);
                    } else {
                        $contentStr = '验证码服务生成异常，请联系管理员，谢谢。';
                    }

                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
					header("Content-type: application/xml");
                    echo $resultStr;
                }else{
                    echo "success";
                }

        } else {
            echo "";
            exit;
        }
    }
        
    private function checkSignature()
    {
        // you must define CNWPER_WEIXIN_TPL_TOKEN by yourself
        if (!defined("CNWPER_WEIXIN_TPL_TOKEN")) {
            throw new Exception('CNWPER_WEIXIN_TPL_TOKEN is not defined!');
        }
        
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
                
        $token = CNWPER_WEIXIN_TPL_TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        // use SORT_STRING rule
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
}


class CaptchaApi
{
    // 纯数字
    public function generate_code($length = 4) {
        return rand(pow(10,($length-1)), pow(10,$length)-1);
    }
    public function generate() {
        date_default_timezone_set('Asia/Shanghai');
        if (CNWPER_WEIXIN_TPL_CODE_TYPE == 'easy'){
            $captcha = $this->generate_code(CNWPER_WEIXIN_TPL_CODE_LEN);
            if ($this->cache($captcha)) {
                return $captcha;
            } else {
                return FALSE;
            }
        }else{
            $min = floor(date("i")/2);
            $day = date("d");
            $day = ltrim($day,0);
            $url = CNWPER_WEIXIN_TPL_HOME_URL;
            $captcha = sha1($min.$url.CNWPER_WEIXIN_TPL_TOKEN);
            $captcha = substr($captcha , $day , CNWPER_WEIXIN_TPL_CODE_LEN);
            if ($this->cache($captcha)) {
                return $captcha;
            } else {
                return FALSE;
            }
        }

    }

    /**
     * check 传入的验证码是否有效：
     *      1. 是否匹配
     *      2. 是否过期
     * @param $captcha - 验证码
     * @return bool
     */
    public function check($captcha) {
        try{
            $_bool = False;
            $captcha_caches = file_get_contents(join(DIRECTORY_SEPARATOR, [CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_PATH, CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_FILENAME]));
            $captcha_caches = json_decode($captcha_caches, True);
            if (array_key_exists($captcha, $captcha_caches)) {
                // 存在于列表时，未超时返回成功，超时返回失败。
                if ( $captcha_caches[$captcha] >= time()) { $_bool = True; }

                // 销毁验证过的数据
                $this->destroy($captcha, $captcha_caches, $_bool);
            }
            return $_bool;
        } catch (Exception $e) {
            echo $e->getMessage();
            return False;
        }
    }

    /**
     * caches 为一个list，
     * cache存储格式: captcha => expire,
     * @param $captcha - 验证码
     * @return bool
     */
    private function cache($captcha) {
        $captcha_caches = @file_get_contents(join(DIRECTORY_SEPARATOR, [CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_PATH, CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_FILENAME]));

        if ($captcha_caches===False) { $captcha_caches = ""; }// 当结果为False时，表示文件不存在。直接赋值空字符串。
        $captcha_caches = json_decode($captcha_caches, True);  // 以 list 形式取出，当无法解析字符串内容时，返回null
        if (!$captcha_caches) { $captcha_caches = []; }  // 没有正确结果时，初始化。

        try {
            $captcha_caches[$captcha] = time() + CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_EXPIRE;
            file_put_contents(
                join(DIRECTORY_SEPARATOR, [CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_PATH, CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_FILENAME]),
                json_encode($captcha_caches),
                LOCK_EX
            );
            return TRUE;
        } catch (Exception $e) {
            echo $e->getMessage();
            return FALSE;
        }

    }

    /**
     * 销毁验证码数据
     * @param string $captcha - 要被销毁的验证码
     * @param array $captcha_caches - 验证码缓存合集
     * @param bool $_bool - 参数为 True 时，只销毁当前传入验证码
     *                      参数为 False 时，缓存合集中超时验证码并一起销毁
     */
    private function destroy($captcha, $captcha_caches, $_bool = True) {
        unset($captcha_caches[$captcha]);

        // 销毁过期验证码
        if (!$_bool) {
            $current_time = time();
            foreach($captcha_caches as $k=>$v){
                if ($current_time > $v) { unset($captcha_caches[$k]); }
            }
        }

        file_put_contents(
            join(DIRECTORY_SEPARATOR, [CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_PATH, CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_FILENAME]),
            json_encode($captcha_caches),
            LOCK_EX
        );
    }

    /**
     * 检测设定的cache文件目录级是否存在，没有则创建
     */
    private function _cache_path_exist() {}

    /**
     * 统一的存储与提取方案，便于后期扩展其他存储方式
     */
    private function _cache_save() {}
}
