<?php

/**
 * 云服务诊断
 * [WeEngine System] Copyright (c) 2014 W7.CC.
 */
defined('IN_IA') or exit('Access Denied');

load()->classs('cloudapi');
load()->model('cloud');
load()->model('setting');

$dos = array('display', 'testapi');
$do = in_array($do, $dos) ? $do : 'display';
permission_check_account_user('system_cloud_diagnose');

if ('testapi' == $do) {
	$starttime = microtime(true);
	$response = cloud_request(CLOUD_API_DOMAIN);
	$endtime = microtime(true);
	iajax(0, '请求接口成功，耗时 ' . (round($endtime - $starttime, 5)) . ' 秒');
} else {
	if ($_W['ispost']){
		if ($_GPC['submit']) {
			$result = cloud_reset_siteinfo();
			$api = new CloudApi();
			$api->deleteCer();
			if (is_error($result)) {
				itoast($result['message'], '', 'error');
			} else {
				itoast('重置成功', 'refresh', 'success');
			}
		}
	}
	if (empty($_W['setting']['site'])) {
		$_W['setting']['site'] = array();
	}
	$checkips = array('openapi.w7.cc');
	$apiurl = CLOUD_API_DOMAIN . '/site/diagnose/ping?version=' . IMS_VERSION . '&siteurl=' . urlencode(trim($_W['siteroot'], '/')) . '&date=' . $_W['timestamp'];
	template('cloud/diagnose');
}
