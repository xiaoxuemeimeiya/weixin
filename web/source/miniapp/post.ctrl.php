<?php
/**
 * 创建小程序
 * [WeEngine System] Copyright (c) 2014 W7.CC.
 */
defined('IN_IA') or exit('Access Denied');

load()->model('module');

$dos = array('post', 'save_post');
$do = in_array($do, $dos) ? $do : 'post';

$type = intval($_GPC['type']);
if (!in_array($type, array_keys($account_all_type))) {
	itoast('账号类型不存在,请重试.');
}
$type_sign = $account_all_type[$type]['type_sign'];
$uniacid = intval($_GPC['uniacid']);
$miniapp_info = miniapp_fetch($uniacid);
if (empty($miniapp_info)) {
	if ($_W['isajax']) {
		iajax(-1, '参数有误');
	}
	itoast('参数有误');
}
$mtype = '';
if ($type_sign == WXAPP_TYPE_SIGN) {
	$version_lists = miniapp_version_all($uniacid);
	if (!empty($version_lists)) {
		foreach ($version_lists as $row) {
			if ($row['type'] == WXAPP_CREATE_MODULE) {
				$mtype = 'account';
				break;
			}
		}
	}
}

if ('post' == $do) {
	template('miniapp/post');
}

if ('save_post' == $do) {
	if (!preg_match('/^[0-9]{1,2}\.[0-9]{1,2}(\.[0-9]{1,2})?$/', trim($_GPC['version']))) {
		iajax(-1, '版本号错误，只能是数字、点，数字最多2位，例如 1.1.1 或1.2');
	}
	
	$version = array(
		'uniacid' => $uniacid,
		'multiid' => '0',
		'description' => safe_gpc_string($_GPC['description']),
		'version' => safe_gpc_string($_GPC['version']),
		'modules' => '',
		'createtime' => TIMESTAMP,
	);
	if (WXAPP_TYPE_SIGN == $type_sign) {
		$version['design_method'] = WXAPP_MODULE;
	}
	$module = module_fetch($_GPC['module_name']);
	if (!empty($module)) {
		$module_expired_list = module_expired_list();
		if (is_error($module_expired_list)) {
			iajax(-1, $module_expired_list['message']);
		}
		if (!empty($module_expired_list)) {
			$expired_modules_name = module_expired_diff($module_expired_list, array($module['name']));
			if (!empty($expired_modules_name)) {
				iajax('-1', '应用：' . $expired_modules_name . '，服务费到期，无法添加！');
			}
		}
		$select_modules[$module['name']] = array(
			'name' => $module['name'],
			'version' => $module['version'],
		);
		$version['modules'] = serialize($select_modules);
	}
	
	table('wxapp_versions')->fill($version)->save();
	$version_id = pdo_insertid();
	if (empty($version_id)) {
		iajax(-1, '创建失败');
	} else {
		cache_delete(cache_system_key('user_accounts', array('type' => $account_all_type[$type]['type_sign'], 'uid' => $_W['uid'])));
		iajax(0, '创建成功', url('account/display/switch', array('uniacid' => $uniacid, 'version_id' => $version_id, 'type' => $type)));
	}
}