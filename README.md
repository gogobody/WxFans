# WxFans
一款 typecho 微信公账号涨粉插件，支持动态验证码

下载后插件文件夹改名为 WxFans 后启用。

## 第一、插件的设置
![](https://cdn.jsdelivr.net/gh/gogobody/blog-img/blogimg/20210308110547.png)
1. 开发者TOKEN  
这个如果我们不采用公众号API接口的话，那这里就随便填写。建议不要用API，否则会使得其他预设值的自动回复关键字失效。

2. 公众号URL  
这个是我们需要在前端显示的公众号二维码的图片。尺寸适当。

3. 验证码获取关键字  
根据我们预设值要对应后面微信公众号自动回复调用一致。

4. 验证码有效时间  
一般设置 2 分钟。单位是默认的。

5. 接口文件名  
这个是会在我们网站根目录生成的PHP文件，对应后面要设置到自动回复的返回URL。

6. 回复模板  
这个一般默认，也可以根据自己需要微调。

## 第二、公众号设置
我们在插件配置完毕之后，就需要在公众号设置自动回复。
![](https://cdn.jsdelivr.net/gh/gogobody/blog-img/blogimg/20210308111024.png)

这里我们在公众号自动回复设置一条。回复内容需要设置注意：
```html
<a href="http://我们的网站URL/api.php?url_captcha=get_captcha">查看验证码</a>
```

这里我们看到上面需要注意的。对应我们插件设置的api 接口文件名称，后面的尾巴（url_captcha=get_captcha）是固定的。

## 第三、如何隐藏内容
![](https://cdn.jsdelivr.net/gh/gogobody/blog-img/blogimg/20210308111223.png)
插件已经集成后台编辑器里了。如果没有的话可以插入一下内容：
```html
<!--wxfans start-->请输入加密内容<!--wxfans end-->
```