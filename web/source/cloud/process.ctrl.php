<?php
/**
 * [WeEngine System] Copyright (c) 2014 W7.CC.
 */
load()->func('communication');
load()->model('cloud');
$prepare = cloud_prepare();
if (is_error($prepare)) {
	itoast($prepare['message'], url('cloud/profile'), 'error');
}

$step = $_GPC['step'];
$steps = array('files', 'schemas', 'scripts');
$step = in_array($step, $steps) ? $step : 'files';

if ('files' == $step && $_W['ispost']) {
	$ret = cloud_download($_GPC['path'], $_GPC['type']);
	if (is_error($ret)) {
		exit($ret['message']);
	}
	exit('success');
}

if ('scripts' == $step && $_W['ispost']) {
	$fname = trim($_GPC['fname']);
	$tipversion = safe_gpc_string($_GPC['tipversion']);
	$entry = IA_ROOT . '/data/update/' . $fname;
	if (is_file($entry) && preg_match('/^update\(\d{12}\-\d{12}\)\.php$/', $fname)) {
		set_time_limit(0);
		$evalret = include $entry;
		if (!empty($evalret)) {
			cache_build_users_struct();
			cache_build_setting();
			@unlink($entry);
			$version_file = file_get_contents(IA_ROOT . '/framework/version.inc.php');
			if ($tipversion) {
				$match_version = strpos($version_file, $tipversion);
				if ($match_version) {
					exit('showtips');
				}
			}
			exit('success');
		}
	}
	exit('failed');
}

$has_new_support = intval($_GPC['has_new_support']);
if (!empty($_GPC['module_name'])) {
	$module_name = safe_gpc_string($_GPC['module_name']);
	$application_type = intval($_GPC['application_type']);
	$module_info = table('modules')->getByName($module_name);
	$type = 'module';
	if (APPLICATION_TYPE_TEMPLATES == $module_info['application_type'] || APPLICATION_TYPE_TEMPLATES == $application_type) {
		$is_upgrade = intval($_GPC['is_upgrade']);
		$packet = cloud_t_build($module_name);
	} else {
		$is_upgrade = intval($_GPC['is_upgrade']);
		$packet = cloud_m_build($module_name, $is_upgrade ? 'upgrade' : '');
		//检测模块升级脚本是否存在乱码
		if (!empty($packet) && !json_encode($packet['scripts'])) {
			itoast('模块安装脚本有代码错误，请联系开发者解决！', referer(), 'error');
		}
	}
	$application_type = $module_info ? $module_info['application_type'] : $application_type;
} elseif (!empty($_GPC['t'])) {
	$module_name = $_GPC['t'];
	$type = 'theme';
	$is_upgrade = intval($_GPC['is_upgrade']);
	$packet = cloud_t_build($_GPC['t']);
} elseif (!empty($_GPC['w'])) {
	$module_name = $_GPC['w'];
	$type = 'webtheme';
	$is_upgrade = intval($_GPC['is_upgrade']);
	$packet = cloud_w_build($_GPC['w']);
} else {
	$module_name = '';
	$packet = cloud_build();
}
if ('schemas' == $step && $_W['ispost']) {
	$tablename = $_GPC['table'];
	foreach ($packet['schemas'] as $schema) {
		if (substr($schema['tablename'], 4) == $tablename) {
			$remote = $schema;
			break;
		}
	}
	if (!empty($remote)) {
		load()->func('db');
		$local = db_table_schema(pdo(), $tablename);
		$sqls = db_table_fix_sql($local, $remote);
		$error = false;
		foreach ($sqls as $sql) {
			if (false === pdo_query($sql)) {
				$error = true;
				$errormsg .= pdo_debug(false);
				break;
			}
		}
		if (!$error) {
			exit('success');
		}
	}
	exit;
}
if (!empty($packet) && (!empty($packet['upgrade']) || !empty($packet['install']))) {
	$schemas = array();
	if (!empty($packet['schemas'])) {
		foreach ($packet['schemas'] as $schema) {
			$schemas[] = substr($schema['tablename'], 4);
		}
	}
	$scripts = array();
	if (empty($packet['install'])) {
		$updatefiles = array();
		if (!empty($packet['scripts']) && empty($packet['type'])) {
			$updatedir = IA_ROOT . '/data/update/';
			load()->func('file');
			rmdirs($updatedir, true);
			mkdirs($updatedir);
			$cversion = IMS_VERSION;
			$crelease = IMS_RELEASE_DATE;
			foreach ($packet['scripts'] as $script) {
				if ($script['release'] <= $crelease) {
					continue;
				}
				$fname = "update({$crelease}-{$script['release']}).php";
				$crelease = $script['release'];
				$script['script'] = @base64_decode($script['script']);
				if (empty($script['script'])) {
					$script['script'] = <<<DAT
<?php
load()->model('setting');
setting_upgrade_version('{$packet['family']}', '{$script['version']}', '{$script['release']}');
return true;
DAT;
				}
				$updatefile = $updatedir . $fname;
				file_put_contents($updatefile, $script['script']);
				$updatefiles[] = $updatefile;
				$s = array_elements(array('message', 'release', 'version'), $script);
				$s['fname'] = $fname;
				$scripts[] = $s;
			}
		}
	}
} else {
	if (is_error($packet)) {
		if ($packet['errno'] == -3) {
			$type = 'expired';
			$extend_button = array(
				array('url' => 'javascript:history.go(-1);', 'title' => '点击这里返回上一页', 'class' => 'btn btn-primary'),
				array('url' => "http://s.w7.cc/module-{$packet['cloud_id']}.html", 'title' => '去续费', 'class' => 'btn btn-primary', 'target' => '_blank'),
			);
		} else {
			$type = 'error';
			$extend_button = array();
		}
		message($packet['message'], '', $type, false, $extend_button);
	} else {
		cache_updatecache();
		if (ini_get('opcache.enable') || ini_get('opcache.enable_cli')) {
			opcache_reset();
		}
		itoast('更新已完成. ', url('cloud/upgrade'), 'success');
	}
}
template('cloud/process');