!function(u){var m={};m.dialog=function(e,i,t,a){(a=a||{}).containerName||(a.containerName="modal-message");var n=$("#"+a.containerName);0==n.length&&($(document.body).append('<div id="'+a.containerName+'" class="modal animated" tabindex="-1" role="dialog" aria-hidden="true"></div>'),n=$("#"+a.containerName));var o='<div class="modal-dialog modal-sm">\t<div class="modal-content">';if(e&&(o+='<div class="modal-header">\t<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>\t<h3>'+e+"</h3></div>"),i&&($.isArray(i)?o+='<div class="modal-body">正在加载中</div>':o+='<div class="modal-body">'+i+"</div>"),t&&(o+='<div class="modal-footer">'+t+"</div>"),o+="\t</div></div>",n.html(o),i&&$.isArray(i)){function s(e){n.find(".modal-body").html(e)}2==i.length?$.post(i[0],i[1]).success(s):$.get(i[0]).success(s)}return n},m.image=function(e,c,l){require(["webuploader","cropper","previewer"],function(i){var a,n,o,t,s,r=m.querystring("i"),d=m.querystring("j");defaultOptions={pick:{id:"#filePicker",label:"点击选择图片",multiple:!1},auto:!0,swf:"./resource/componets/webuploader/Uploader.swf",server:"./index.php?i="+r+"&j="+d+"&c=utility&a=file&do=upload&type=image&thumb=0",chunked:!1,compress:!1,fileNumLimit:1,fileSizeLimit:4194304,fileSingleSizeLimit:4194304,crop:!1,preview:!1,name:""},"android"==m.agent()&&(defaultOptions.sendAsBinary=!0),l=$.extend({},defaultOptions,l),e&&(e=$(e),l.pick={id:e,multiple:l.pick.multiple}),l.multiple&&(l.pick.multiple=l.multiple,l.fileNumLimit=8),l.crop&&(l.auto=!1,l.pick.multiple=!1,l.preview=!1,i.Uploader.register({"before-send-file":"cropImage"},{cropImage:function(t){if(!t||!t._cropData)return!1;var a,n,e=t._cropData;return t=this.request("get-file",t),n=i.Deferred(),a=new i.Lib.Image,n.always(function(){a.destroy(),a=null}),a.once("error",n.reject),a.once("load",function(){a.crop(e.x,e.y,e.width,e.height,e.scale)}),a.once("complete",function(){var e,i;try{e=a.getAsBlob(),i=t.size,t.source=e,t.size=e.size,t.trigger("resize",e.size,i),n.resolve()}catch(e){n.resolve()}}),t._info&&a.info(t._info),t._meta&&a.meta(t._meta),a.loadFromBlob(t.source),n.promise()}})),n=i.create(l),e.data("uploader",n),l.preview&&(o=mui.previewImage({footer:u.util.templates["image.preview.html"]}),$(o.element).find(".js-cancel").click(function(){o.close()}),$(document).on("click",".js-submit",function(e){var i=$(o.element).find(".mui-slider-group .mui-active").index(),t=l.preview;if(o.groups[t]&&o.groups[t][i]&&o.groups[t][i].el){var a="./index.php?i="+r+"&j="+d+"&c=utility&a=file&do=delete&type=image",n=$(o.groups[t][i].el).data("id");$.post(a,{id:n},function(e){e=$.parseJSON(e);$(o.groups[t][i].el).remove(),o.close()})}return e.stopPropagation(),!1})),n.on("fileQueued",function(t){m.loading().show(),l.crop&&n.makeThumb(t,function(e,i){n.file=t,e||a.preview(i)},1,1)}),n.on("uploadSuccess",function(e,i){if(i.error&&i.error.message)m.toast(i.error.message,"error");else{n.on("uploadFinished",function(){m.loading().close(),n.reset(),a.reset()});var t=$('<img src="'+i.url+'" data-preview-src="" data-preview-group="'+l.preview+'" />');l.preview&&o.addImage(t[0]),$.isFunction(c)&&c(i)}}),n.onError=function(e){a.reset(),m.loading().close(),"Q_EXCEED_SIZE_LIMIT"!=e?"Q_EXCEED_NUM_LIMIT"!=e?alert("错误信息: "+e):m.toast("单次最多上传8张"):alert("错误信息: 图片大于 4M 无法上传.")},a={preview:function(e){return(t=$(u.util.templates["avatar.preview.html"])).css("height",$(u).height()),$(document.body).prepend(t),(s=t.find("img")).attr("src",e),s.cropper({aspectRatio:l.aspectRatio?l.aspectRatio:1,viewMode:1,dragMode:"move",autoCropArea:1,restore:!1,guides:!1,highlight:!1,cropBoxMovable:!1,cropBoxResizable:!1}),t.find(".js-submit").on("click",function(){var e=s.cropper("getData"),i=a.getImageSize().width/n.file._info.width;e.scale=i,n.file._cropData={x:e.x,y:e.y,width:e.width,height:e.height,scale:e.scale},n.upload()}),t.find(".js-cancel").one("click",function(){t.remove(),n.reset()}),m.loading().close(),this},getImageSize:function(){var e=s.get(0);return{width:e.naturalWidth,height:e.naturalHeight}},reset:function(){return $(".js-avatar-preview").remove(),n.reset(),this}}})},m.map=function(o,s){require(["map"],function(e){(o=o||{}).lng||(o.lng=116.403851),o.lat||(o.lat=39.915177);var i=new e.Point(o.lng,o.lat),a=new e.Geocoder,n=$("#map-dialog");if(0==n.length){function t(e){a.getPoint(e,function(e){map.panTo(e),marker.setPosition(e),marker.setAnimation(BMAP_ANIMATION_BOUNCE),setTimeout(function(){marker.setAnimation(null)},3600)})}(n=m.dialog("请选择地点",'<div class="form-group"><div class="input-group"><input type="text" class="form-control" placeholder="请输入地址来直接查找相关位置"><div class="input-group-btn"><button class="btn btn-default"><i class="icon-search"></i> 搜索</button></div></div></div><div id="map-container" style="height:400px;"></div>','<button type="button" class="btn btn-default" data-dismiss="modal">取消</button><button type="button" class="btn btn-primary">确认</button>',{containerName:"map-dialog"})).find(".modal-dialog").css("width","80%"),n.modal({keyboard:!1}),map=m.map.instance=new e.Map("map-container"),map.centerAndZoom(i,12),map.enableScrollWheelZoom(),map.enableDragging(),map.enableContinuousZoom(),map.addControl(new e.NavigationControl),map.addControl(new e.OverviewMapControl),marker=m.map.marker=new e.Marker(i),marker.setLabel(new e.Label("请您移动此标记，选择您的坐标！",{offset:new e.Size(10,-20)})),map.addOverlay(marker),marker.enableDragging(),marker.addEventListener("dragend",function(e){var i=marker.getPosition();a.getLocation(i,function(e){n.find(".input-group :text").val(e.address)})}),n.find(".input-group :text").keydown(function(e){13==e.keyCode&&t($(this).val())}),n.find(".input-group button").click(function(){t($(this).parent().prev().val())})}n.off("shown.bs.modal"),n.on("shown.bs.modal",function(){marker.setPosition(i),map.panTo(marker.getPosition())}),n.find("button.btn-primary").off("click"),n.find("button.btn-primary").on("click",function(){if($.isFunction(s)){var t=m.map.marker.getPosition();a.getLocation(t,function(e){var i={lng:t.lng,lat:t.lat,label:e.address};s(i)})}n.modal("hide")}),n.modal("show")})},m.toast=function(e,i,t){if(t&&"success"!=t){if("error"==t)a=mui.toast('<div class="mui-toast-icon"><span class="fa fa-exclamation-circle"></span></div>'+e)}else var a=mui.toast('<div class="mui-toast-icon"><span class="fa fa-check"></span></div>'+e);if(i)var n=3,o=setInterval(function(){if(n<=0)return clearInterval(o),void(location.href=i);n--},1e3);return a},m.loading=function(e){e=e||"show";var i={};if((t=$(".js-toast-loading")).size()<=0)var t=$('<div class="mui-toast-container mui-active js-toast-loading"><div class="mui-toast-message"><div class="mui-toast-icon"><span class="fa fa-spinner fa-spin"></span></div>加载中</div></div>');return i.show=function(){document.body.appendChild(t[0])},i.close=function(){t.remove()},i.hide=function(){t.remove()},"show"==e?i.show():"close"==e&&i.close(),i},m.message=function(e,i,t,a){var n=$("<div>"+u.util.templates["message.html"]+"</div>");if(n.attr("class","mui-content fadeInUpBig animated "+mui.className("backdrop")),n.on(mui.EVENT_MOVE,mui.preventDefault),n.css("background-color","#efeff4"),a&&n.find(".mui-desc").html(a),i){var o=i.replace("##auto");if(n.find(".mui-btn-success").attr("href",o),-1<i.indexOf("##auto"))var s=5,r=setInterval(function(){if(s<=0)return clearInterval(r),void(location.href=o);n.find(".mui-btn-success").html(s+"秒后自动跳转"),s--},1e3)}n.find(".mui-btn-success").click(function(){if(i){var e=i.replace("##auto");location.href=e}else history.go(-1)}),t=t||"success",n.find(".title").html(e),n.find(".mui-message-icon span").attr("class","mui-msg-"+t),$("html").append(n[0])},m.alert=function(e,i,t,a){return mui.alert(e,i,t,a)},m.confirm=function(e,i,t,a){return mui.confirm(e,i,t,a)},m.pay=function(t){if((t=$.extend({},{enableMethod:[],defaultMethod:"wechat",payMethod:"wechat",orderTitle:"",orderTid:"",goodsTag:"",success:function(){},faild:function(){},finish:function(){}},t)).orderFee&&!(t.orderFee<=0)){!t.defaultMethod&&t.payMethod&&(t.defaultMethod=t.payMethod);function i(e){"main"==e?(s.find(".js-main-modal").show().addClass("fadeInRight animated"),s.find(".js-switch-pay-modal").hide(),s.find(".js-switch-modal").hide()):"pay"==e&&(s.find(".js-main-modal").hide(),s.find(".js-switch-pay-modal").show().addClass("fadeInRight animated"),s.find(".js-switch-modal").show())}var e,a,n=mui.className("active"),o=mui.className("backdrop"),s=0<$("#pay-detail-modal").size()?$("#pay-detail-modal"):$('<div class="mui-modal '+n+' js-pay-detail-modal" id="pay-detail-modal"></div>'),r=((e=document.createElement("div")).classList.add(o),e.addEventListener(mui.EVENT_MOVE,mui.preventDefault),e.addEventListener("click",function(e){if(s)return s.remove(),$(r).remove(),document.body.setAttribute("style",""),!1}),e);return m.loading().show(),t.enableMethod&&1<t.enableMethod.length?$.post("index.php?i="+u.sysinfo.uniacid+"&j="+u.sysinfo.acid+"&c=entry&m=core&do=paymethod",{module:t.module,tid:t.orderTid,title:t.orderTitle,fee:t.orderFee},function(e){if(m.loading().hide(),s.html(e),r.setAttribute("style",""),$(document.body).append(s),$(document.body).append(r),function(e){e?($(".mui-content")[0].setAttribute("style","overflow:hidden;"),document.body.setAttribute("style","overflow:hidden;")):($(".mui-content")[0].setAttribute("style",""),document.body.setAttribute("style",""))}(!0),s.find(".js-switch-modal").click(function(){i("main")}),s.find(".js-switch-pay").click(function(){i("pay")}),s.find(".js-switch-pay-close").click(function(){s.remove(),$(r).remove(),document.body.setAttribute("style","")}),s.find(".js-order-title").html(t.orderTitle),s.find(".js-pay-fee").html(t.orderFee),!(0<s.find(".js-switch-pay-modal li").size()))return m.toast("暂无有效支付方式"),s.remove(),$(r).remove(),document.body.setAttribute("style",""),!1;t.enableMethod&&0<t.enableMethod.length?s.find(".js-switch-pay-modal li").each(function(){-1==$.inArray($(this).data("method"),t.enableMethod)&&$(this).remove()}):s.find(".js-switch-pay-modal li").each(function(){t.enableMethod.push($(this).data("method"))})}):"wechat"==t.payMethod?(a=0,"miniprogram"===u.__wxjs_environment&&(a=1),$.post("index.php?i="+u.sysinfo.uniacid+"&j="+u.sysinfo.acid+"&c=entry&m=core&do=pay&iswxapp="+a,{method:t.payMethod,tid:t.orderTid,title:t.orderTitle,fee:t.orderFee,module:t.module,goods_tag:t.goodsTag},function(e){if(m.loading().hide(),(e=$.parseJSON(e)).message.errno){if("2"==e.message.errno)return void m.message("确认您的支付身份，跳转支付中",e.message.message,"success");var i={errno:e.message.errno,message:e.message.message};return t.fail(i),void t.complete(i)}payment=e.message.message,WeixinJSBridge.invoke("getBrandWCPayRequest",{appId:payment.appId,timeStamp:payment.timeStamp,nonceStr:payment.nonceStr,package:payment.package,signType:payment.signType,paySign:payment.paySign},function(e){if("get_brand_wcpay_request:ok"==e.err_msg){var i={errno:0,message:e.err_msg};t.success(i),t.complete(i)}else if("get_brand_wcpay_request:cancel"==e.err_msg){i={errno:-1,message:e.err_msg};t.complete(i)}else{i={errno:-2,message:e.err_msg};t.fail(i),t.complete(i)}})})):"alipay"==t.payMethod?(m.loading().hide(),$.post("index.php?i="+u.sysinfo.uniacid+"&j="+u.sysinfo.acid+"&c=entry&m=core&do=pay",{method:t.payMethod,tid:t.orderTid,title:t.orderTitle,fee:t.orderFee,module:t.module},function(e){if(m.loading().hide(),(e=$.parseJSON(e)).message.errno){var i={errno:e.message.errno,message:e.message.message};return t.fail(i),void t.complete(i)}require(["../payment/alipay/ap.js"],function(){_AP.pay(e.message.message)})})):(m.loading().hide(),$.post("index.php?i="+u.sysinfo.uniacid+"&j="+u.sysinfo.acid+"&c=entry&m=core&do=pay",{method:t.payMethod,tid:t.orderTid,title:t.orderTitle,fee:t.orderFee,module:t.module},function(e){if(e=$.parseJSON(e),m.loading().hide(),e.message.errno){var i={errno:e.message.errno,message:e.message.message};return t.fail(i),void t.complete(i)}location.href=e.message.message})),!0}m.toast("请确认支付金额","","error")},m.poppicker=function(e,t){require(["mui.datepicker"],function(){mui.ready(function(){var i=new mui.PopPicker({layer:e.layer||1});i.setData(e.data),$.isFunction(e.setSelectedValue)&&e.setSelectedValue(i.pickers),i.show(function(e){$.isFunction(t)&&t(e),i.dispose()})})})},m.districtpicker=function(i,o){require(["mui.districtpicker"],function(n){mui.ready(function(){var e={layer:3,data:n},a={};$.map(n,function(e,t){if(e.text==o.province){if(a.province=t,!n[t].children)return;$.map(n[t].children,function(e,i){if(e.text==o.city){if(a.city=i,!n[t].children[i].children)return;return console.dir(n[t].children[i].children),void $.map(n[t].children[i].children,function(e,i){e.text!=o.district||(a.district=i)})}})}}),e.setSelectedValue=function(e){console.dir(a),a.province&&e[0].setSelectedIndex(a.province),a.city&&e[1].setSelectedIndex(a.city),a.district&&e[2].setSelectedIndex(a.district)},m.poppicker(e,i)})})},m.datepicker=function(e,t){require(["mui.datepicker"],function(){mui.ready(function(){var i;i=new mui.DtPicker(e),console.dir(i),i.show(function(e){$.isFunction(t)&&t(e),i.dispose()})})})},m.querystring=function(e){var i=location.search.match(new RegExp("[?&]"+e+"=([^&]+)","i"));return null==i||i.length<1?"":i[1]},m.tomedia=function(e,i){if(!e)return"";if(0==e.indexOf("./addons"))return u.sysinfo.siteroot+e.replace("./","");-1!=e.indexOf(u.sysinfo.siteroot)&&-1==e.indexOf("/addons/")&&(e=e.substr(e.indexOf("images/"))),0==e.indexOf("./resource")&&(e="app/"+e.substr(2));var t=e.toLowerCase();return-1!=t.indexOf("http://")||-1!=t.indexOf("https://")?e:e=i||!u.sysinfo.attachurl_remote?u.sysinfo.attachurl_local+e:u.sysinfo.attachurl_remote+e},m.sendCode=function(e,i){var t={btnElement:"",showElement:"",showTips:"%s秒后重新获取",btnTips:"重新获取验证码",successCallback:arguments[3]};if("object"!=typeof i){var a=e;e=i;i={btnElement:$(a),showElement:$(a),showTips:"%s秒后重新获取",btnTips:"重新获取验证码",successCallback:arguments[2]}}else i=$.extend({},t,i);if(!e)return i.successCallback("1","请填写正确的帐号");if(!/^1[3|4|5|7|8][0-9]{9}$/.test(e)&&!/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/.test(e))return i.successCallback("1","格式错误");var n=60;i.showElement.html(i.showTips.replace("%s",n)),i.showElement.attr("disabled",!0);var o=setInterval(function(){--n<=0?(clearInterval(o),n=60,i.showElement.html(i.btnTips),i.showElement.attr("disabled",!1)):i.showElement.html(i.showTips.replace("%s",n))},1e3),s={};s.receiver=e,s.uniacid=u.sysinfo.uniacid,$.post("../web/index.php?c=utility&a=verifycode",s,function(e){return 0==e.message.errno?i.successCallback("0","验证码发送成功"):i.successCallback("1",e.message.message)},"json")},m.loading1=function(){var e="modal-loading",i=$("#"+e);return 0==i.length&&($(document.body).append('<div id="'+e+'" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true"></div>'),i=$("#"+e),html='<div class="modal-dialog">\t<div style="text-align:center; background-color: transparent;">\t\t<img style="width:48px; height:48px; margin-top:100px;" src="../attachment/images/global/loading.gif" title="正在努力加载...">\t</div></div>',i.html(html)),i.modal("show"),i.next().css("z-index",999999),i},m.loaded1=function(){var e=$("#modal-loading");0<e.length&&e.modal("hide")},m.cookie={prefix:"",set:function(e,i,t){expires=new Date,expires.setTime(expires.getTime()+1e3*t),document.cookie=this.name(e)+"="+escape(i)+"; expires="+expires.toGMTString()+"; path=/"},get:function(e){for(cookie_name=this.name(e)+"=",cookie_length=document.cookie.length,cookie_begin=0;cookie_begin<cookie_length;){if(value_begin=cookie_begin+cookie_name.length,document.cookie.substring(cookie_begin,value_begin)==cookie_name){var i=document.cookie.indexOf(";",value_begin);return-1==i&&(i=cookie_length),unescape(document.cookie.substring(value_begin,i))}if(cookie_begin=document.cookie.indexOf(" ",cookie_begin)+1,0==cookie_begin)break}return null},del:function(e){new Date;document.cookie=this.name(e)+"=; expires=Thu, 01-Jan-70 00:00:01 GMT; path=/"},name:function(e){return this.prefix+e}},m.agent=function(){var e=navigator.userAgent,i=-1<e.indexOf("Android")||-1<e.indexOf("Linux"),t=!!e.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/);return i?"android":t?"ios":"unknown"},m.removeHTMLTag=function(e){if("string"==typeof e)return e=(e=(e=(e=(e=e.replace(/<script[^>]*?>[\s\S]*?<\/script>/g,"")).replace(/<style[^>]*?>[\s\S]*?<\/style>/g,"")).replace(/<\/?[^>]*>/g,"")).replace(/\s+/g,"")).replace(/&nbsp;/gi,"")},m.card=function(){$.post("./index.php?c=utility&a=card",{uniacid:u.sysinfo.uniacid,acid:u.sysinfo.acid},function(e){if(m.loading().hide(),0==(e=$.parseJSON(e)).message.errno)return m.message("没有开通会员卡功能","","info"),!1;1==e.message.errno&&wx.ready(function(){wx.openCard({cardList:[{cardId:e.message.message.card_id,code:e.message.message.code}]})}),2==e.message.errno&&(location.href="./index.php?i="+u.sysinfo.uniacid+"&c=mc&a=card&do=mycard"),3==e.message.errno&&(alert("由于会员卡升级到微信官方会员卡，需要您重新领取并激活会员卡"),wx.ready(function(){wx.addCard({cardList:[{cardId:e.message.message.card_id,cardExt:e.message.message.card_ext}],success:function(e){}})}))})},"function"==typeof define&&define.amd?define(function(){return m}):u.util=m}(window),function(e){e["avatar.preview.html"]='<div class="fadeInDownBig animated js-avatar-preview avatar-preview" style="position:relative; width:100%;z-index:9999"><img src="" alt="" class="cropper-hidden"><div class="bar-action mui-clearfix"><a href="javascript:;" class="mui-pull-left js-cancel">取消</a> <a href="javascript:;" class="mui-pull-right mui-text-right js-submit">选取</a></div></div>',e["image.preview.html"]='<div class="bar-action mui-clearfix"><a href="javascript:;" class="mui-pull-left js-cancel">取消</a> <a href="javascript:;" class="mui-pull-right mui-text-right js-submit">删除</a></div>',e["message.html"]='<div class="mui-content-padded"><div class="mui-message"><div class="mui-message-icon"><span></span></div><h4 class="title"></h4><p class="mui-desc"></p><div class="mui-button-area"><a href="javascript:;" class="mui-btn mui-btn-success mui-btn-block">确定</a></div></div></div>'}(this.window.util.templates=this.window.util.templates||{});