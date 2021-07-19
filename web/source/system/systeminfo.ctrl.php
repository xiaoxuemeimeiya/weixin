<?php
/**
 * 系统信息
 * [WeEngine System] Copyright (c) 2014 W7.CC.
*/
defined('IN_IA') or exit('Access Denied');

load()->model('system');

$dos = array('display', 'get_attach_size');
$do = in_array($do, $dos) ? $do : 'display';

if ('display' == $do) {
	$info = array(
		'os' => php_uname(),
		'php' => PHP_VERSION,
		'ims_version' => IMS_VERSION,
		'ims_release_date' => IMS_RELEASE_DATE,
		'sapi' => $_SERVER['SERVER_SOFTWARE'] ? $_SERVER['SERVER_SOFTWARE'] : php_sapi_name(),
	);
	$info['family'] = '您的产品是';
	switch(IMS_FAMILY) {
		case 'v':
			$info['family'] .= '开源版, 没有购买商业授权, 不能用于商业用途';
			break;
		case 's':
			$info['family'] .= '授权版';
			break;
		case 'x':
			$info['family'] .= '商业版';
			break;
		default:
			$info['family'] .= '单版';
	}

	//上传许可
	$size = 0;
	$size = @ini_get('upload_max_filesize');
	if ($size) {
		$size = bytecount($size);
	}
	if ($size > 0) {
		$ts = @ini_get('post_max_size');
		if ($ts) {
			$ts = bytecount($size);
		}
		if ($ts > 0) {
			$size = min($size, $ts);
		}
		$ts = @ini_get('memory_limit');
		if ($ts) {
			$ts = bytecount($size);
		}
		if ($ts > 0) {
			$size = min($size, $ts);
		}
	}
	if (empty($size)) {
		$size = '';
	} else {
		$size = sizecount($size);
	}
	$info['limit'] = $size;

	//服务器 MySQL 版本
	$sql = 'SELECT VERSION();';
	$info['mysql']['version'] = pdo_fetchcolumn($sql);

	//当前数据库尺寸
	$tables = pdo_fetchall("SHOW TABLE STATUS LIKE '" . $_W['config']['db']['tablepre'] . "%'");
	$size = 0;
	foreach ($tables as &$table) {
		$size += $table['Data_length'] + $table['Index_length'];
	}
	if (empty($size)) {
		$size = '';
	} else {
		$size = sizecount($size);
	}
	//当前数据库尺寸
	$info['mysql']['size'] = $size;
	//当前附件根目录
	$info['attach']['url'] = empty($_W['setting']['remote_complete_info']['type']) ? $_W['siteroot'] . $_W['config']['upload']['attachdir'] . '/' : $_W['setting']['remote_complete_info'][attachment_get_type($_W['setting']['remote_complete_info']['type'])]['url'];

	$info['company'] = '宿 州 市 微 擎 云 计 算 有 限 公 司';
	$info['developers'] = array('袁文涛', '任超 (米粥)', '宋建君 (Gorden)', '赵波', '杨峰', '卜睿君', '张宏', '高建业', '葛海波', '马莉娜', '樊永康', '王玉', '张拯', '白志杰');
	$info['operators'] = array('侯琪琪 (琪琪)', '杨欣雨 (小雨)', '赵小雷 (擎擎)', '蔡帅帅 (小帅)', '朱传宝 (阿宝)', '蒋康康 (阿康)', '王鹏 (鹏鹏)');
	$info['exchange_group'] = array('link' => 'https://jq.qq.com/?_wv=1027&k=5NzXzQ3', 'title' => '547025486');
	if ($_W['isajax']) {
		iajax(0, $info);
	}
	template('system/systeminfo');
}

if ('get_attach_size' == $do) {
	//当前附件尺寸
	$path = IA_ROOT . '/' . $_W['config']['upload']['attachdir'];
	$size = dir_size($path);
	if (empty($size)) {
		$size = '';
	} else {
		$size = sizecount($size);
	}
	iajax(0, array('attach_size' => $size));
}
