<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * WxFans
 * <div class="wxFansSet"><a style="width:fit-content" id="wxfans">版本检测中..</div>&nbsp;</div><style>.wxFansSet{margin-top: 5px;}.wxFansSet a{background: #ff5a8f;padding: 5px;color: #fff;}</style>
 * <script>var wxfversion="1.0.2";function update_detec(){var container=document.getElementById("wxfans");if(!container){return}var ajax=new XMLHttpRequest();container.style.display="block";ajax.open("get","https://api.github.com/repos/gogobody/WxFans/releases/latest");ajax.send();ajax.onreadystatechange=function(){if(ajax.readyState===4&&ajax.status===200){var obj=JSON.parse(ajax.responseText);var newest=obj.tag_name;if(newest>wxfversion){container.innerHTML="发现新主题版本："+obj.name+'。下载地址：<a href="'+obj.zipball_url+'">点击下载</a>'+"<br>您目前的版本:"+String(wxfversion)+"。"+'<a target="_blank" href="'+obj.html_url+'">👉查看新版亮点</a>'}else{container.innerHTML="您目前的版本:"+String(wxfversion)+"。"+"您目前使用的是最新版。"}}}};update_detec();</script>
 * @package WxFans 一款公众号涨粉插件，支持动态验证码
 * @author <a href="https://www.ijkxs.com">即刻学术<br> gogobody</a>
 * @version 1.0.2
 * @link https://www.ijkxs.com
 */

define('CNWPER_WEIXIN_TPL_FILE', 'wxApi.tpl');
define('CNWPER_WEIXIN_COOKIE_NAME', 'cnwper_weixin_secret_code');

class WxFans_Plugin implements Typecho_Plugin_Interface
{

    // 默认加密首尾标签对 // 不要修改
    protected static $pluginNodeStart = '<!--wxfans start-->';
    protected static $pluginNodeEnd = '<!--wxfans end-->';

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx_1001 = array(__CLASS__, 'cnwper_weixin_secret');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx_1001 = array(__CLASS__, 'excerptEx');

        Typecho_Plugin::factory('admin/write-post.php')->bottom = array(__CLASS__, 'render');
        Typecho_Plugin::factory('admin/write-page.php')->bottom = array(__CLASS__, 'render');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        $options = Helper::options()->plugin('WxFans');
        @unlink(join(DIRECTORY_SEPARATOR, [$_SERVER['DOCUMENT_ROOT'], $options->api_filename.'.php']));
    }

    public static function get_plugins_info(){
        $plugin_name = 'WxFans'; //改成你的插件名
        Typecho_Widget::widget('Widget_Plugins_List@activated', 'activated=1')->to($activatedPlugins);
        $activatedPlugins = json_decode(json_encode($activatedPlugins),true);
        $plugins_list = $activatedPlugins['stack'];
        $plugins_info = array();
        for ($i=0;$i<count($plugins_list);$i++){
            if($plugins_list[$i]['title'] == $plugin_name){
                $plugins_info = $plugins_list[$i];
                break;
            }
        }
        if(count($plugins_info)<1){
            return false;
        }else{
            return $plugins_info['version'];
        }
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        ?>
        <div>
            <h4>WxFan插件由即刻学术开发</h4>
            <div>
                <a href="https://github.com/gogobody/WxFans">点我查看使用说明</a>
            </div>
        </div>
        <?php
        /** 分类名称 */
        $switch = new Typecho_Widget_Helper_Form_Element_Radio('switch', array(
            true => '开启',
            false => '关闭'
        ), false, _t('开启/关闭插件'),'');
        $form->addInput($switch);

        $token = new Typecho_Widget_Helper_Form_Element_Text('token', null, '', '开发者TOKEN');
        $form->addInput($token);

        $mp_qr = new Typecho_Widget_Helper_Form_Element_Text('mp_qr', null, '', '公众号图片URL');
        $form->addInput($mp_qr);

        $replay_keyword = new Typecho_Widget_Helper_Form_Element_Text('replay_keyword', null, '验证码', '验证码获取关键字');
        $form->addInput($replay_keyword);

        $cache_expire = new Typecho_Widget_Helper_Form_Element_Text('cache_expire', null, '2', '验证码有效时间', '单位：分钟，示范：2');
        $form->addInput($cache_expire);

        $api_filename = new Typecho_Widget_Helper_Form_Element_Text('api_filename', null, 'api', '接口文件文件名', '设置生成的公众号接口文件文件名，比如 api，不建议修改');
        $form->addInput($api_filename);

        $replay_template = new Typecho_Widget_Helper_Form_Element_Text('replay_template', null, '您的验证码为：【%s】，验证码有效期为[%s]分钟，请抓紧使用，过期需重新申请', '验证码回复模版', '以 %s 作为替换符，第一个%s为验证码位置，第二个%s为有效期位置');
        $form->addInput($replay_template);


        $cache_filename = new Typecho_Widget_Helper_Form_Element_Text('cache_filename', null, 'cnwper_wx_caches.data', '默认缓存数据保存文件名', '默认缓存数据保存文件名，可以不用管');
        $cache_filename->setAttribute('disabled',true);
        $form->addInput($cache_filename);

        $code_type = new Typecho_Widget_Helper_Form_Element_Radio('code_type', array(
            'easy' => '简单数字',
            'hard' => '数字加字母'
        ), 'easy', _t('验证码难度'),'');
        $form->addInput($code_type);

        $code_len = new Typecho_Widget_Helper_Form_Element_Text('code_len',null, 4, _t('验证码长度'),'');
        $form->addInput($code_len);
//        'cache_storage' => '',  // 默认为file，先保留选项，后期扩展。也可以考虑SESSION        'cache_filename' => "cnwper_wx_caches.data",

        $expire = new Typecho_Widget_Helper_Form_Element_Text('expire',null, 31536000, _t('过期时间'),'默认一年（输入秒数）');
        $form->addInput($expire);

    }

    /**
     * 手动保存配置句柄
     * @param $config array 插件配置
     */
    public static function configHandle($config)
    {
        self::cnwper_weixin_api_generate($config);
        Helper::configPlugin('WxFans', $config);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }


    /**
     * 插件实现方法
     *
     * @access public
     * @param $cnwper_weixin_options
     * @return string
     */
    public static function cnwper_weixin_api_generate($cnwper_weixin_options)
    {
        $options = Helper::options();

        if ($cnwper_weixin_options['switch']) {
//        if ($cnwper_weixin_options['token']) {
//            // 对参数进行各种判断，如果有缺漏或者不符合要求的返回失败
//        }
            $tpl_content = file_get_contents($options->pluginDir('WxFans') . '/WxFans/' . CNWPER_WEIXIN_TPL_FILE);
            $search = array(
                '{{ CNWPER_WEIXIN_TPL_TOKEN }}',
                '{{ CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_PATH }}',
                '{{ CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_FILENAME }}',
                '{{ CNWPER_WEIXIN_TPL_CAPTCHA_CACHE_EXPIRE }}',
                '{{ CNWPER_WEIXIN_TPL_WEIXIN_REPLY_KEYWORD }}',
                '{{ CNWPER_WEIXIN_TPL_WEIXIN_REPLY_TEMPLATE }}',
                '{{ CNWPER_WEIXIN_TPL_HOME_URL }}',
                '{{ CNWPER_WEIXIN_TPL_COOKIE_NAME }}',
                '{{ CNWPER_WEIXIN_TPL_CODE_TYPE }}',
                '{{ CNWPER_WEIXIN_TPL_CODE_LEN }}',
                '{{ CNWPER_WEIXIN_EXPIRE_TIME }}'

            );
            $replace = array(
                $cnwper_weixin_options['token'],
                'dirname(__FILE__)',
                $cnwper_weixin_options['cache_filename'],
                $cnwper_weixin_options['cache_expire'],
                $cnwper_weixin_options['replay_keyword'],
                $cnwper_weixin_options['replay_template'],
                $options->siteUrl, // need recheck
                CNWPER_WEIXIN_COOKIE_NAME,
                $cnwper_weixin_options['code_type'],
                $cnwper_weixin_options['code_len'],
                $cnwper_weixin_options['expire']

            );
            // 用正则去替换模板源文件中的变量符号{$varname}, 改用 简单的 str替换 就能满足需求
            $res = str_replace($search, $replace, $tpl_content);
            //编译后文件写入某个目录
            file_put_contents(
                join(DIRECTORY_SEPARATOR, [$_SERVER['DOCUMENT_ROOT'], $cnwper_weixin_options['api_filename'] . '.php']),
                $res
            );
        } else {
            @unlink(join(DIRECTORY_SEPARATOR, [$_SERVER['DOCUMENT_ROOT'], $cnwper_weixin_options['api_filename'] . '.php']));
        }
    }

    /**
     * 自动输出摘要
     * @access public
     * @return string
     */
    public static function excerptEx($html, $widget, $lastResult){
        $html = empty( $lastResult ) ? $html : $lastResult;
        $WxPassRule='/'.self::$pluginNodeStart.'([\s\S]*?)'.self::$pluginNodeEnd.'/i';
        preg_match_all($WxPassRule, $html, $hide_words);
        if(!$hide_words[0]){
            $WxPassRule='/&lt;!--wxfans start--&gt;([\s\S]*?)&lt;!--wxfans end--&gt;/i';
        }
        $html=trim($html);
        if (preg_match_all($WxPassRule, $html, $hide_words)){
            $html = str_replace($hide_words[0], '', $html);
        }
        $html=Typecho_Common::subStr(strip_tags($html), 0, 140, "...");
        return $html;
    }


    public static function cnwper_weixin_secret($content, $widget, $lastResult)
    {
        $options = Helper::options();
        $cnwper_weixin_options = $options->plugin('WxFans');
        $content = empty( $lastResult ) ? $content : $lastResult;

        if (!$cnwper_weixin_options->switch) {
            return $content;
        }
        $WxPassRule='/'.self::$pluginNodeStart.'([\s\S]*?)'.self::$pluginNodeEnd.'/i';

        preg_match_all($WxPassRule, $content, $secret_content);
        if(!$secret_content[0]){
            $WxPassRule='/&lt;!--wxfans start--&gt;([\s\S]*?)&lt;!--wxfans end--&gt;/i';
        }
        if (preg_match_all($WxPassRule, $content, $secret_content)) {
            $cnwper_weixin_cookie = md5($cnwper_weixin_options->token . CNWPER_WEIXIN_COOKIE_NAME . 'ijkxs.com');
            $_cnwper_weixin_cookie = isset($_COOKIE[CNWPER_WEIXIN_COOKIE_NAME]) ? $_COOKIE[CNWPER_WEIXIN_COOKIE_NAME] : '';
            if ($_cnwper_weixin_cookie != $cnwper_weixin_cookie) {
                $secret_notice = '
                <link rel="stylesheet" id="pure_css-css"  href="'.$options->siteUrl.'usr/plugins/WxFans/assets/css/wxfans.min.css" type="text/css"/>
                <div class="wxfans">
                    <div class="cm-grid cm-card secret_view">
                        <div class="cm-row">
                            <div class="cm-col-md-4">
                                <img src="' . $cnwper_weixin_options->mp_qr . '" class="cm-resp-img">
                            </div>
                            <div class="cm-col-md-8">
                                <div class="hide_content_info" style="margin:10px 0">
                                    <div class="cm-alert primary">
                                          隐藏内容，扫码公众号查看，发【' . $cnwper_weixin_options->replay_keyword . '】获验证码
                                    </div>
                                    <div style="display: flex">
                                    <input type="text" id="captcha_input" placeholder="输入验证码并提交"> &nbsp;&nbsp;
                                    <input id="check_secret_view" class="cm-btn success" type="button" value="提交"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/jquery@2.1.0/dist/jquery.min.js?ver=2.1"></script>
                <script>
                    $("#check_secret_view").click(function () {
                        var captcha = $("#captcha_input").val()
                        var ajax_data = {
                            captcha: captcha
                        };
                        $.post("' . Typecho_Common::url($cnwper_weixin_options->api_filename . '.php?cnwper=check_captcha',$options->siteUrl) . '", ajax_data, function (c) {
                            c = $.trim(c);
                            if (c === "200") {
                                location.reload();
                            } else {
                                alert("您的验证码错误");
                            }
                        });
                    });
                    </script>';
                $content = str_replace($secret_content[0], $secret_notice, $content);
            }
        }
        return $content;
    }

    public static function add_button()
    {
        $dir = Helper::options()->pluginUrl . '/WxFollowView/editer.js';
        echo "<script type=\"text/javascript\" src=\"{$dir}\"></script>";
    }

    public static function render()
    {
        $pluginNodeStart = self::$pluginNodeStart;
        $pluginNodeEnd = self::$pluginNodeEnd;

        echo "<script>
                $(function() {
                    if($('#wmd-button-row').length>0)$('#wmd-button-row').append('<li class=\"wmd-spacer wmd-spacer1\" id=\"wmd-spacer5\"></li><li class=\"wmd-button\" id=\"wmd-secret-button\" title=\"加密\"><img src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAABoVBMVEUAAAC9vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb29vb2QpK6QpK68vb29vb29vb29vb28vL27vLy9vb29vb29vb28vb2QpK6QpK60uLq8vL29vb26u7wAN2xMfpe5u7y9vb28vL20uLqQpK6QpK6QpK6QpK6PpK6XqLCerLOTpa+QpK6QpK6Spa+dq7KXqLCPpK6QpK6QpK6QpK6QpK6QpK6QpK6Po66QpK6QpK6QpK6QpK6Po66QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6OoqyYq7QAITiQpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6Jnqlee4pgfYuQpK6QpK6QpK5yi5hgfYtgfYuQpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK6QpK7///+aiJ4ZAAAAinRSTlMAAAAAJWBgJQAAAE7V4eDVTgAAINinKCeh1yAAAAAAU+kwAAAn4lQAAAAAYeAhAAEZ1mEAAAAAE4LO+besrbX1zoITAABT87apra2tram281MAAF/gIQACACHgXwAAX+AfAAVBX+AfAEHuAF/gIQBT87isrbjzABOCrKysrIISAAAAAAMDAwMAAAD0HrYnAAAAAWJLR0SKhWh3dgAAAAd0SU1FB94MChAKAKhkkWAAAADOSURBVBjTY2BgYGBkYmZhZWPnYGJkgABOLm4eXj5+AUFOqICQsIiomLiEpJQ0mCsjKyevoKikrKKqpi4rAxTQ0NTS1tHV0zcwNDI2MQUKmJlbWFpZ29ja2Ts4OjkDBVxc3dw9PL28fXz9/AMCgQJBwSGhYeEREeFhkVHRMUCB2Lj4hMSk5OSkxIT4uFiQQEpqWnpGZmZGelpqCnYBdC1Z2Tm5KIbm5RcUFhUDQVFJKdjasvKKyqpqIKiqqa2rBwo0NDY1t7QCQUtbe0cnAwAYTECfAJIwMQAAACV0RVh0ZGF0ZTpjcmVhdGUAMjAxNi0wOS0xN1QxNToyMToyNSswODowME6zKwsAAAAldEVYdGRhdGU6bW9kaWZ5ADIwMTQtMTItMTBUMTY6MTA6MDArMDg6MDD4IEsDAAAATXRFWHRzb2Z0d2FyZQBJbWFnZU1hZ2ljayA3LjAuMS02IFExNiB4ODZfNjQgMjAxNi0wOS0xNyBodHRwOi8vd3d3LmltYWdlbWFnaWNrLm9yZ93ZpU4AAAAYdEVYdFRodW1iOjpEb2N1bWVudDo6UGFnZXMAMaf/uy8AAAAYdEVYdFRodW1iOjpJbWFnZTo6SGVpZ2h0ADEyOEN8QYAAAAAXdEVYdFRodW1iOjpJbWFnZTo6V2lkdGgAMTI40I0R3QAAABl0RVh0VGh1bWI6Ok1pbWV0eXBlAGltYWdlL3BuZz+yVk4AAAAXdEVYdFRodW1iOjpNVGltZQAxNDE4MTk5MDAwaPjnQgAAABJ0RVh0VGh1bWI6OlNpemUAMS4zNUtCy0Y24gAAAF90RVh0VGh1bWI6OlVSSQBmaWxlOi8vL2hvbWUvd3d3cm9vdC9zaXRlL3d3dy5lYXN5aWNvbi5uZXQvY2RuLWltZy5lYXN5aWNvbi5jbi9zcmMvMTE4MjAvMTE4MjAyOC5wbmdgSnSkAAAAAElFTkSuQmCC\"/></li>');	
                    $(document).on('click', '#wmd-secret-button', function() {		
                        getValue(\"text\", \"{$pluginNodeStart}请输入加密内容{$pluginNodeEnd}\");
                    }
                    );
                });
                function getValue(objid, str) {
                    var myField = document.getElementById(\"\" + objid);
                    //IE浏览器
                    if (document.selection) {
                        myField.focus();
                        sel = document.selection.createRange();
                        sel.text = str;
                        sel.select();
                    }
                    //火狐/网景 浏览器
                    else if (myField.selectionStart || myField.selectionStart == '0') {
                        //得到光标前的位置
                        var startPos = myField.selectionStart;
                        //得到光标后的位置
                        var endPos = myField.selectionEnd;
                        // 在加入数据之前获得滚动条的高度
                        var restoreTop = myField.scrollTop;
                        myField.value = myField.value.substring(0, startPos) + str + myField.value.substring(endPos, myField.value
                            .length);
                        //如果滚动条高度大于0
                        if (restoreTop > 0) {
                            // 返回
                            myField.scrollTop = restoreTop;
                        }
                        myField.focus();
                        myField.selectionStart = startPos + str.length;
                        myField.selectionEnd = startPos + str.length;
                    } else {
                        myField.value += str;
                        myField.focus();
                    }
                }
            </script>";

    }
}
