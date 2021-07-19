<?php
/**
 * 手动创建平台
 * [WeEngine System] Copyright (c) 2014 W7.CC.
 */
defined('IN_IA') or exit('Access Denied');

load()->model('module');
load()->model('miniapp');
load()->model('phoneapp');
load()->model('user');
load()->classs('wxapp.platform');

$dos = array('display', 'save_account', 'check_params', 'get_user_info', 'load_groups', 'get_authurl');
$do = in_array($do, $dos) ? $do : 'display';
$sign = safe_gpc_string($_GPC['sign']);
if (empty($account_all_type_sign[$sign])) {
	$error_msg = '所需创建的账号类型不存在, 请重试.';
	empty($_W['isajax']) ? message($error_msg, '', 'error') : iajax(-1, $error_msg);
}

if ('load_groups' == $do) {
	$group_keys = array();
	if (user_is_vice_founder($_W['uid'])) {
		$founder_own_table = table('users_founder_own_uni_groups');
		$founder_own_uni_groups = $founder_own_table->getOwnUniGroupsByFounderUid($_W['uid']);
		$group_keys = array_keys((array) $founder_own_uni_groups);
	}
	$unigroups = uni_groups($group_keys);

	foreach ($unigroups as $key => $group) {
		if (empty($group[$sign])) {
			unset($unigroups[$key]); //unset没有所需支持类型应用的权限组
		}
	}
	iajax(0, $unigroups);
}

if ('get_user_info' == $do) {
	if (!$_W['isfounder']) {
		iajax(-1, '非法请求数据！');
	}

	$uid = intval($_GPC['uid'][0]);
	$user = user_single(array('uid' => $uid));
	if (empty($user)) {
		iajax(-1, '用户不存在或是已经被删除', '');
	}
	$info = array(
		'uid' => $user['uid'],
		'username' => $user['username'],
		'group' => user_group_detail_info($user['groupid']),
		'endtime' => user_end_time($user['uid']),
		'modules' => array(),
	);
	$info['package'] = empty($info['group']['package']) ? array() : iunserializer($info['group']['package']);

	$user_modules = user_modules($user['uid']);
	if (!empty($user_modules)) {
		foreach ($user_modules as $module) {
			if (1 != $module['issystem'] && MODULE_SUPPORT_ACCOUNT == $module[$sign . '_support']) {
				$info['modules'][] = $module;
			}
		}
	}
	iajax(0, $info);
}

$sign_title = $account_all_type_sign[$sign]['title'];
$create_account_type = $account_all_type_sign[$sign]['contain_type'][0];

$user_account_num = permission_user_account_num($_W['uid']);
if (empty($_W['isfounder']) && $user_account_num["{$sign}_limit"] <= 0) {
	$error_msg = $sign_title . '创建数量已达上限！';
	empty($_W['isajax']) ? message($error_msg, '', 'error') : iajax(-1, $error_msg);
}
$_W['breadcrumb'] = '新建平台账号';
if ('display' == $do) {
	$modules = user_modules($_W['uid']);
	foreach ($modules as $k => $module) {
		if (1 == $module['issystem'] || MODULE_SUPPORT_ACCOUNT != $module[$sign . '_support']) {
			unset($modules[$k]); //unset 没有所需支持类型的应用
		} else {
			$modules[$k]['support'] = $sign . '_support';
		}
	}
	if ($_W['isajax']) {
		$data = array(
			'modules' => $modules,
		);
		iajax(0, $data);
	}

}

if ('get_authurl' == $do) {
	if (empty($_W['setting']['platform']['authstate'])) {
		iajax(-1, '请先开启微信开放平台');
	} else {
		if ('account' == $sign) {
			$account_platform = new WeixinPlatform();
		}
		if ('wxapp' == $sign) {
			$account_platform = new WxappPlatform();
		}
		$preauthcode = $account_platform->getPreauthCode();
		if (is_error($preauthcode)) {
			iajax(-1, $preauthcode['message']);
		}
		$authurl = sprintf(ACCOUNT_PLATFORM_API_LOGIN, $_W['setting']['platform']['appid'],
				$preauthcode, urlencode($GLOBALS['_W']['siteroot'] . 'index.php?c=account&a=auth&do=forward'), ACCOUNT_PLATFORM_API_LOGIN_ACCOUNT);
		iajax(0, $authurl);
	}
}

if ('save_account' == $do || 'check_params' == $do) {
	$post = array();
	$post['step'] = safe_gpc_string(trim($_GPC['step']));
	$post['name'] = safe_gpc_string(trim($_GPC['name']));
	$post['description'] = safe_gpc_string($_GPC['description']);
	$post['owner_uid'] = intval($_GPC['owner_uid']);
	$post['version'] = safe_gpc_string(trim($_GPC['version']));

	if (empty($post['step']) || 'base_info' == $post['step']) {
		if (empty($post['name'])) {
			iajax(-1, $sign_title . '名称不能为空');
		}
		$check_uniacname = table('account')->searchWithTitle($post['name'])->searchWithType($create_account_type)->searchAccountList();
		if (!empty($check_uniacname)) {
			iajax(-1, "该名称'{$post['name']}'已经存在");
		}
		//验证appid唯一性
		if (in_array($sign, array(ACCOUNT_TYPE_SIGN))) {
			$appid = safe_gpc_string($_GPC['key']);
		} else {
			$appid = safe_gpc_string($_GPC['appid']);
		}
		if (!empty($appid)) {
			$hasAppid = uni_get_account_by_appid($appid, $create_account_type);
			if (!empty($hasAppid)) {
				iajax(-1, "{$hasAppid['key_title']}已被{$hasAppid['type_title']}[ {$hasAppid['name']} ]使用");
			}
		}
	}
	if (empty($post['step']) || 'account_modules' == $post['step']) {
		if ($_W['isfounder']) {//创始人才有此步骤的操作权限
			if (!empty($post['owner_uid']) && !user_is_founder($post['owner_uid'], true)) {
				$create_account_info = permission_user_account_num($post['owner_uid']);
				if ($create_account_info[$sign . '_limit'] <= 0) {
					iajax(-1, '您所设置的主管理员所在的用户组可添加的公众号数量已达上限，请选择其他人做主管理员！');
				}
			}
		}
	}
	if ('check_params' == $do) {
		iajax(0);
	}
	
	if (empty($post['step'])) {
		if (in_array($sign, array(PHONEAPP_TYPE_SIGN, WXAPP_TYPE_SIGN, ALIAPP_TYPE_SIGN, BAIDUAPP_TYPE_SIGN, TOUTIAOAPP_TYPE_SIGN))) {
			if (!preg_match('/^[0-9]{1,2}\.[0-9]{1,2}(\.[0-9]{1,2})?$/', $post['version'])) {
				iajax(-1, '版本号错误，只能是数字、点，数字最多2位，例如 1.1.1 或1.2');
			}
		}
	}

}

if ('save_account' == $do) {
	if (!uni_account_can_create($sign)) {
		iajax(-1, '平台账号数量超过限制，请删除无用的平台账号或联系网站创始人购买！');
	}
	$attachment_url = array();
	if (!empty($_GPC['headimg'])) {
		$headimg = safe_gpc_path($_GPC['headimg']);
		if (file_is_image($headimg)) {
			$attachment_url[] = str_replace($_W['siteroot'] . 'attachment/', '', $headimg);
		}
	}
	if (!empty($_GPC['qrcode'])) {
		$qrcode = safe_gpc_path($_GPC['qrcode']);
		if (file_is_image($qrcode)) {
			$attachment_url[] = str_replace($_W['siteroot'] . 'attachment/', '', $qrcode);
		}
	}
	// step1 基础信息和权限数据处理
	if (in_array($sign, array(ACCOUNT_TYPE_SIGN, WEBAPP_TYPE_SIGN, PHONEAPP_TYPE_SIGN))) {
		$account_insert = array(
			'groupid' => 0,
			'default_acid' => 0,
			'name' => $post['name'],
			'description' => $post['description'],
			'title_initial' => get_first_pinyin($post['name']),
			'createtime' => TIMESTAMP,
			'create_uid' => intval($_W['uid']),
			'logo' => empty($headimg) ? '' : $headimg,
			'qrcode' => empty($qrcode) ? '' : $qrcode,
		);
		pdo_insert('uni_account', $account_insert);
		$uniacid = pdo_insertid();
		if (empty($uniacid)) {
			iajax(-1, "添加{$sign_title}失败, 请重试");
		}
		$account_data = array('name' => $post['name'], 'type' => $create_account_type);
		if (ACCOUNT_TYPE_SIGN == $sign) {
			$account_data['account'] = safe_gpc_string(trim($_GPC['account']));
		}
		if (ACCOUNT_TYPE_SIGN == $sign) {
			$account_data['original'] = safe_gpc_string(trim($_GPC['original']));
			$account_data['level'] = intval($_GPC['level']);
			$account_data['key'] = safe_gpc_string(trim($_GPC['key']));
			$account_data['secret'] = safe_gpc_string(trim($_GPC['secret']));
		}
		$acid = account_create($uniacid, $account_data);
		if (empty($acid)) {
			iajax(-1, "添加{$sign_title}信息失败");
		}
		pdo_update('uni_account', array('default_acid' => $acid), array('uniacid' => $uniacid));

		// 角色
		if (empty($_W['isfounder'])) {
			uni_account_user_role_insert($uniacid, $_W['uid'], ACCOUNT_MANAGE_NAME_OWNER);
		}
		cache_build_account_modules($uniacid);

		

		// 其他数据
		if (in_array($sign, array(ACCOUNT_TYPE_SIGN))) {
			pdo_insert('mc_groups', array('uniacid' => $uniacid, 'title' => '默认会员组', 'isdefault' => 1));
			$fields = pdo_getall('profile_fields');
			if (is_array($fields)) {
				foreach ($fields as $field) {
					pdo_insert('mc_member_fields', array(
						'uniacid' => $uniacid,
						'fieldid' => $field['id'],
						'title' => $field['title'],
						'available' => $field['available'],
						'displayorder' => $field['displayorder'],
					));
				}
			}
		}
		// 公众号数据
		if (ACCOUNT_TYPE_SIGN == $sign) {
			//当是认证服务号的时候设置权限到借用oauth中
			$oauth = uni_setting($uniacid, array('oauth'));
			if ($acid && empty($oauth['oauth']['account']) && !empty($account_data['key']) && !empty($account_data['secret']) && ACCOUNT_SERVICE_VERIFY == $account_data['level']) {
				pdo_update('uni_settings',
					array('oauth' => iserializer(array('account' => $acid, 'host' => $oauth['oauth']['host']))),
					array('uniacid' => $uniacid)
				);
			}
			//获取默认模板的id
			$template = table('modules')->getTemplateByName('default');
			pdo_insert('site_styles', array(
				'uniacid' => $uniacid,
				'templateid' => $template['mid'],
				'name' => $template['title'] . '_' . random(4),
			));
			$styleid = pdo_insertid();
			//给公众号添加默认微站
			pdo_insert('site_multi', array(
				'uniacid' => $uniacid,
				'title' => $post['name'],
				'styleid' => $styleid,
			));
			$multi_id = pdo_insertid();
		}
		pdo_insert('uni_settings', array(
			'creditnames' => iserializer(array('credit1' => array('title' => '积分', 'enabled' => 1), 'credit2' => array('title' => '余额', 'enabled' => 1))),
			'creditbehaviors' => iserializer(array('activity' => 'credit1', 'currency' => 'credit2')),
			'uniacid' => $uniacid,
			'default_site' => empty($multi_id) ? 0 : $multi_id,
			'sync' => iserializer(array('switch' => 0, 'acid' => '')),
		));
	}
	if (in_array($sign, array(WXAPP_TYPE_SIGN, ALIAPP_TYPE_SIGN, BAIDUAPP_TYPE_SIGN, TOUTIAOAPP_TYPE_SIGN))) {
		$miniapp_data = array(
			'name' => $post['name'],
			'type' => $create_account_type,
			'description' => $post['description'],
			'headimg' => empty($headimg) ? '' : $headimg,
			'qrcode' => empty($qrcode) ? '' : $qrcode,
		);
		if (WXAPP_TYPE_SIGN == $sign) {
			$miniapp_data['original'] = safe_gpc_string($_GPC['original']);
			$miniapp_data['level'] = 1;
		}
		//由于各种小程序属性不同,故不同的单独写
		if (isset($_GPC['key']) && !empty($_GPC['key'])) {
			$miniapp_data['key'] = safe_gpc_string($_GPC['key']);
		}
		if (isset($_GPC['appid']) && !empty($_GPC['appid'])) {
			if (WXAPP_TYPE_SIGN == $sign || ALIAPP_TYPE_SIGN == $sign) {
				$miniapp_data['key'] = safe_gpc_string($_GPC['appid']);
			} else {
				$miniapp_data['appid'] = safe_gpc_string($_GPC['appid']);
			}
		}
		if (isset($_GPC['secret']) && !empty($_GPC['secret'])) {
			$miniapp_data['secret'] = safe_gpc_string($_GPC['secret']);
		}

		$uniacid = miniapp_create($miniapp_data);
		if (is_error($uniacid) || empty($uniacid)) {
			iajax(-1, empty($uniacid) ? '添加失败' : $uniacid['message']);
		}
	}

	//同步头像和二维码到当前平台下 暂定为只更新数据不移动图片
	if (!empty($attachment_url)) {
		pdo_update('core_attachment', array('uniacid' => $uniacid), array('attachment in' => array_unique($attachment_url)));
	}

	$module_expired_list = module_expired_list();
	if (is_error($module_expired_list)) {
		iajax(-1, $module_expired_list['message']);
	}

	//设置权限
	if ($_W['isfounder']) {
		if (!empty($post['owner_uid'])) {
			$user_modules_info = user_modules($post['owner_uid']);
			$user_modules = array_keys($user_modules_info);
			if (!empty($user_modules)) {
				$expired_modules_name = module_expired_diff($module_expired_list, $user_modules);
				if (!empty($expired_modules_name)) {
					iajax(-1, '主管理员的应用：' . $expired_modules_name . '，服务费到期，无法添加！');
				}
			}
			$owner = pdo_get('uni_account_users', array('uniacid' => $uniacid, 'role' => 'owner'));
			if (!empty($owner)) {
				pdo_update('uni_account_users', array('uid' => $post['owner_uid']), array('uniacid' => $uniacid, 'role' => 'owner'));
			} else {
				uni_account_user_role_insert($uniacid, $post['owner_uid'], ACCOUNT_MANAGE_NAME_OWNER);
			}
			
		}

		if (!empty($_GPC['endtime'])) {
			$account_end_time = strtotime($_GPC['endtime']);
			if (!empty($post['owner_uid'])) {
				$user_end_time = strtotime(user_end_time($post['owner_uid']));
				if ($user_end_time > 0 && $account_end_time > $user_end_time) {
					$account_end_time = $user_end_time;
				}
			}
			pdo_update('account', array('endtime' => $account_end_time), array('uniacid' => $uniacid));
		}

		//附加套餐组
		if (!empty($_GPC['groups'])) {
			$_GPC['groups'] = safe_gpc_array($_GPC['groups']);
			$modules = uni_groups($_GPC['groups']);
			$module_name = array_reduce(array_column($modules, 'modules_all'), 'array_merge', array());
			if (!empty($module_expired_list)) {
				$expired_modules_name = module_expired_diff($module_expired_list, array_keys($module_name));
				if (!empty($expired_modules_name)) {
					iajax('-1', '应用：' . $expired_modules_name . '，服务费到期，无法添加！');
				}
			}
			foreach ($_GPC['groups'] as $group_id) {
				$group_id = intval($group_id);
				if (!empty($group_id)) {
					pdo_insert('uni_account_group', array('uniacid' => $uniacid, 'groupid' => $group_id));
				}
			}
		}
		//附加权限
		if (!empty($_GPC['modules'])) {
			$modules = safe_gpc_array($_GPC['modules']);
			if (!empty($module_expired_list)) {
				$expired_modules_name = module_expired_diff($module_expired_list, $modules);
				if (!empty($expired_modules_name)) {
					iajax('-1', '应用：' . $expired_modules_name . '，服务费到期，无法添加！');
				}
			}
			$data = array(
				'modules' => array('modules' => array(), 'wxapp' => array(), 'webapp' => array(), 'phoneapp' => array()),
				'uniacid' => $uniacid,
			);
			$group_sign = 'account' == $sign ? 'modules' : $sign;
			$data['modules'][$group_sign] = $modules;
			$data['modules'] = iserializer($data['modules']);
			pdo_insert('uni_account_extra_modules', $data);
		}
		cache_delete(cache_system_key('uniaccount', array('uniacid' => $uniacid)));
		cache_delete(cache_system_key('unimodules', array('uniacid' => $uniacid)));
		cache_delete(cache_system_key('proxy_wechatpay_account'));
		$cash_index = 'account' == $sign ? 'app' : $sign;
		cache_delete(cache_system_key('user_accounts', array('type' => $cash_index, 'uid' => $_W['uid'])));
		if (!empty($post['owner_uid'])) {
			cache_delete(cache_system_key('user_accounts', array('type' => $cash_index, 'uid' => $post['owner_uid'])));
			cache_build_account_modules($uniacid, $post['owner_uid']);
		}
	}

	//step2 引导页面 or 进入新建的账号
	$next_url = '';
	if (ACCOUNT_TYPE_SIGN == $sign) {
		$next_url = url('account/post-step', array('uniacid' => $uniacid, 'step' => 4));
	} elseif (in_array($sign, array(PHONEAPP_TYPE_SIGN, WXAPP_TYPE_SIGN, ALIAPP_TYPE_SIGN, BAIDUAPP_TYPE_SIGN, TOUTIAOAPP_TYPE_SIGN))) {
		$next_url = '';
	} else {
		$next_url = url('account/display/switch', array('uniacid' => $uniacid, 'type' => $create_account_type));
	}
	if ($_W['isw7_request']) {
		if (in_array($sign, array(ACCOUNT_TYPE_SIGN))) {
			$next_url = url('account/display/switch', array('uniacid' => $uniacid, 'type' => $create_account_type), true);
		}
	}

	/*开通上级权限start*/
	if ($_W['uid']) {
		$founder_uid = pdo_getcolumn('users_founder_own_users', array('uid'=>$_W['uid']), 'founder_uid');
		if(!empty($founder_uid)){
			uni_account_user_role_insert($uniacid, $founder_uid, ACCOUNT_MANAGE_NAME_VICE_FOUNDER);
		}
	}
	/*开通上级权限end*/

	if (!empty($next_url)) {
		$result = array('next_url' => $next_url, 'uniacid' => $uniacid);
		iajax(0, $result, $next_url);
	}

	//step2 新建版本
	if (in_array($sign, array(PHONEAPP_TYPE_SIGN, WXAPP_TYPE_SIGN, ALIAPP_TYPE_SIGN, BAIDUAPP_TYPE_SIGN, TOUTIAOAPP_TYPE_SIGN))) {
		//打包单模块时，每添加一个模块算是一个版本
		$version = array(
			'uniacid' => $uniacid,
			'description' => safe_gpc_string($_GPC['version_description']),
			'version' => $post['version'],
			'modules' => '',
			'createtime' => TIMESTAMP,
		);
		//打包模块
		$module = module_fetch(safe_gpc_string($_GPC['version_module']));
		if (!empty($module)) {
			$modules = array($module['name']);
			if (!empty($module_expired_list)) {
				$expired_modules_name = module_expired_diff($module_expired_list, $modules);
				if (!empty($expired_modules_name)) {
					iajax('-1', '应用：' . $expired_modules_name . '，服务费到期，无法添加！');
				}
			}
			$version['modules'] = serialize(array($module['name'] => array(
				'name' => $module['name'],
				'version' => $module['version'],
			)));
		}
		if (WXAPP_TYPE_SIGN == $sign) {
			$version['design_method'] = WXAPP_MODULE;
			$version['quickmenu'] = '';
			$version['createtime'] = TIMESTAMP;
			$version['template'] = 0;
			$version['type'] = 0; //是否公众号应用 1 是 0 默认小程序
			$version['multiid'] = 0;
		}

		pdo_insert('wxapp_versions', $version);
		$version_id = pdo_insertid();

		if (empty($version_id)) {
			iajax(-1, '版本创建失败');
		} else {
			cache_delete(cache_system_key('user_accounts', array('type' => $sign, 'uid' => $_W['uid'])));
			$next_url = url('account/display/switch', array('uniacid' => $uniacid, 'version_id' => $version_id, 'type' => $create_account_type), true);
			if ($_W['isw7_request']) {
				$result = array('next_url' => $next_url, 'uniacid' => $uniacid);
				iajax(0, $result);
			}
			iajax(0, '创建成功', $next_url);
		}
	}
}

template('account/create');
