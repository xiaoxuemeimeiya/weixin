<?php
/**
 * [WeEngine System] Copyright (c) 2014 W7.CC
 * $sn$
 */
defined('IN_IA') or exit('Access Denied');

function cloud_client_define() {
	return array(
		'/framework/function/communication.func.php',
		'/framework/model/cloud.mod.php',
		'/web/source/cloud/upgrade.ctrl.php',
		'/web/source/cloud/process.ctrl.php',
		'/web/source/cloud/dock.ctrl.php',
		'/web/themes/default/cloud/upgrade.html',
		'/web/themes/default/cloud/process.html'
	);
}

function cloud_not_must_authorization_method() {
	return array(
		'module/setting/index',
		'module/setting/save',
		'sms/info',
		'sms/sign',
		'wxapp/info',
		'wxapp/login/qr-code',
		'wxapp/login/qr-scan',
		'wxapp/publish',
		'wxapp/publish/download',
		'module/query'
	);
}

/**
 * @param bool $must_authorization_host 是否校验授权域名请求接口
 * @return array
 */
function _cloud_build_params($must_authorization_host = true) {
	global $_W;
	$pars = array();
	$pars['host'] = strexists($_SERVER['HTTP_HOST'], ':') ? parse_url($_SERVER['HTTP_HOST'], PHP_URL_HOST) : $_SERVER['HTTP_HOST'];
	if (is_array($_W['setting']['site']) && !empty($_W['setting']['site']['url']) && !$must_authorization_host) {
		$pars['host'] = parse_url($_W['setting']['site']['url'], PHP_URL_HOST);
	}
	$pars['https'] = $_W['ishttps'] ? 1 : 0;
	$pars['family'] = IMS_FAMILY;
	$pars['version'] = IMS_VERSION;
	$pars['php_version'] = PHP_VERSION;
	$pars['current_host'] = $_SERVER['HTTP_HOST'];
	$pars['release'] = IMS_RELEASE_DATE;
	if (!empty($_W['setting']['site'])) {
		$pars['key'] = $_W['setting']['site']['key'];
		$pars['password'] = md5($_W['setting']['site']['key'] . $_W['setting']['site']['token']);
	}
	$clients = cloud_client_define();
	$string = '';
	foreach ($clients as $cli) {
		$string .= md5_file(IA_ROOT . $cli);
	}
	$pars['client'] = md5($string);
	return $pars;
}

function _cloud_shipping_parse($dat, $file) {
	if (is_error($dat)) {
		return error(-1, '网络传输故障，详情： ' . (strpos($dat['message'], 'Connection reset by peer') ? '云服务瞬时访问过大而导致网络传输中断，请稍后重试。' : $dat['message']));
	}
	$tmp = iunserializer($dat['content']);
	if (is_array($tmp) && is_error($tmp)) {
		if ($tmp['errno'] == '-2') {
			file_put_contents(IA_ROOT . '/framework/version.inc.php', str_replace("'x'", "'v'", file_get_contents(IA_ROOT . '/framework/version.inc.php')));
		}
		return $tmp;
	}
	if ($dat['content'] == 'patching') {
		return error(-1, '补丁程序正在更新中，请稍后再试！');
	}
	if ($dat['content'] == 'frequent') {
		return error(-1, '更新操作太频繁，请稍后再试！');
	}
	if ($dat['content'] == 'blacklist') {
		return error(-1, '抱歉，您的站点已被列入云服务黑名单，云服务一切业务已被禁止，请联系微擎客服！');
	}
	if ($dat['content'] == 'install-theme-protect' || $dat['content'] == 'install-module-protect') {
		return error('-1', '此' . ($dat['content'] == 'install-theme-protect' ? '模板' : '模块') . '已设置版权保护，您只能通过云平台来安装，请先删除该模块的所有文件，购买后再行安装。');
	}
	$content = json_decode($dat['content'], true);
	if (!empty($content['error'])) {
		return error(-1, $content['error']);
	}
	if (!empty($content) && is_array($content)) {
		return $content;
	}

	if (strlen($dat['content']) != 32) {
		$dat['content'] = iunserializer($dat['content']);
		if (is_array($dat['content']) && isset($dat['content']['files'])) {
			if (!empty($dat['content']['manifest'])) {
				$dat['content']['manifest'] = base64_decode($dat['content']['manifest']);
			}
			if (!empty($dat['content']['scripts'])) {
				$dat['content']['scripts'] = base64_decode($dat['content']['scripts']);
			}
			return $dat['content'];
		}
		if (is_array($dat['content']) && isset($dat['content']['data'])) {
			$data = $dat['content'];
		} else {
			return error(-1, '云服务平台向您的服务器传输数据过程中出现错误,详情:' . $dat['content']);
		}
	} else {
		$data = @file_get_contents($file);
		@unlink($file);
	}
	
	$ret = @iunserializer($data);
	if (empty($data) || empty($ret)) {
		return error(-1, '云服务平台向您的服务器传输的数据校验失败,请稍后重试.');
	}
	$ret = iunserializer($ret['data']);
	if (is_array($ret) && is_error($ret)) {
		if ($ret['errno'] == '-2') {
			file_put_contents(IA_ROOT . '/framework/version.inc.php', str_replace("'x'", "'v'", file_get_contents(IA_ROOT . '/framework/version.inc.php')));
		}
		if ($ret['errno'] == '-3') { //模块升级服务到期
			return array(
				'errno' => $ret['errno'],
				'message' => $ret['message'],
				'cloud_id' => $ret['data'],
			);
		}
	}
	if (!is_error($ret) && is_array($ret)) {
		if (!empty($ret) && $ret['state'] == 'fatal') {
			return error($ret['errorno'], '发生错误: ' . $ret['message']);
		}
		return $ret;
	} else {
		return error($ret['errno'], "发生错误: {$ret['message']}");
	}
}

function cloud_request($url, $post = '', $extra = array(), $timeout = 60) {
	global $_W;
	load()->func('communication');
	if (!empty($_W['setting']['cloudip']['ip']) && empty($extra['ip'])) {
		$extra['ip'] = $_W['setting']['cloudip']['ip'];
	}
	if (strexists($url, 's.w7.cc')) {
		$extra = array();
	}

	$response = ihttp_request($url, $post, $extra, $timeout);
	if (is_error($response)) {
		setting_save(array(), 'cloudip');
	}
	return $response;
}

function cloud_api($method, $data = array(), $extra = array(), $timeout = 60) {
	global $_W;
	$cache_key = cache_system_key('cloud_api', array('method' => md5($method . implode('', $data))));
	$cache = cache_load($cache_key);
	if (!empty($cache) && !$extra['nocache']) {
		return $cache;
	}
	$api_url = CLOUD_API_DOMAIN . '/%s';
	$not_must_authorization_method = cloud_not_must_authorization_method();
	$must_authorization_host = !in_array($method, $not_must_authorization_method);
	$pars = _cloud_build_params($must_authorization_host);
	if ($method != 'site/token/index') {
		$pars['token'] = cloud_build_transtoken();
	}
	$data = array_merge($pars, $data);
	if (starts_with($_SERVER['HTTP_USER_AGENT'], 'we7')) {
		$extra['CURLOPT_USERAGENT'] = $_SERVER['HTTP_USER_AGENT'];
	}
	if ($_W['config']['setting']['useragent'] && starts_with($_W['config']['setting']['useragent'], 'we7')) {
		$extra['CURLOPT_USERAGENT'] = $_W['config']['setting']['useragent'];
	}
	$extra['X-We7-Cache'] = cache_random();
	$response = ihttp_request(sprintf($api_url, $method), $data, $extra, $timeout);
	$file = IA_ROOT . '/data/' . (!empty($data['file']) ? $data['file'] : $data['method']);
	$file = $file . cache_random();
	$ret = _cloud_shipping_parse($response, $file);
	if (!is_error($ret) && !empty($ret)) {
		cache_write($cache_key, $ret, CACHE_EXPIRE_MIDDLE);
	}
	return $ret;
}

function cloud_prepare() {
	global $_W;
	setting_load();
	if (empty($_W['setting']['site']['key']) || empty($_W['setting']['site']['token'])) {
		return error('-1', '站点注册信息丢失, 请通过"重置站点ID和通信密钥"重新获取 !');
	}
	return true;
}

function cloud_build($nocache = false) {
	$pars['method'] = 'application.build4';
	$pars['file'] = 'application.build';
	$extra = $nocache ? array('nocache' => $nocache) : array();
	$ret = cloud_api('site/build/index', $pars, $extra);
	if (is_error($ret)) {
		return $ret;
	}

	if ($ret['state'] == 'warning') {
		$ret['files'] = cloud_client_define();
		unset($ret['schemas']);
		unset($ret['scripts']);
	} else {
		$patch_path = sprintf('%s/data/patch/upgrade/%s', IA_ROOT, date('Ymd'));
		if (!is_dir($patch_path)) {
			mkdirs($patch_path);
		}

		$files = $files_allowed = array();
		if (!empty($ret['files'])) {
			foreach ($ret['files'] as $file) {
				$entry = IA_ROOT . $file['path'];
				if (!is_file($entry) || md5_file($entry) != $file['checksum']) {
					$files_allowed[] = $file['path'];
				}

				$entry = $patch_path . $file['path'];
				if (!is_file($entry)) {
					$entry = IA_ROOT . $file['path'];
				}
				if (!is_file($entry) || md5_file($entry) != $file['checksum']) {
					$files[] = $file['path'];
				}
			}
		}
		$ret['files'] = $files;
		if (!empty($ret['files'])) {
			cloud_bakup_files($ret['files']);
		} else {
			if (!empty($files_allowed)) {
				foreach ($files_allowed as $file) {
					$dir = pathinfo(IA_ROOT . $file, PATHINFO_DIRNAME);
					if (!is_dir($dir)) {
						mkdirs($dir);
					}
					file_put_contents(IA_ROOT . $file, file_get_contents($patch_path . $file));
				}
				rmdirs($patch_path);
			}
		}
		$schemas = array();
		if (!empty($ret['schemas'])) {
			load()->func('db');
			foreach ($ret['schemas'] as $remote) {
				$name = substr($remote['tablename'], 4);
				$local = db_table_schema(pdo(), $name);
				unset($remote['increment']);
				unset($local['increment']);
				if (empty($local)) {
					$schemas[] = $remote;
				} else {
					$sqls = db_table_fix_sql($local, $remote);
					if (!empty($sqls)) {
						$schemas[] = $remote;
					}
				}
			}
		}
		$ret['schemas'] = $schemas;
	}

	if (IMS_FAMILY != $ret['family']) {
		$update_version_success = setting_upgrade_version($ret['family'], IMS_VERSION, IMS_RELEASE_DATE);
		if (empty($update_version_success)) {
			message('切换版本失败，请修改 /framework/version.inc.php 文件权限为 User 可写或是 777', 'refresh', 'error');
		} else {
			message('更新系统正在为您自动切换版本', 'refresh');
		}
	}
	$ret['upgrade'] = false;
	if (!empty($ret['files']) || !empty($ret['schemas']) || !empty($ret['scripts'])) {
		$ret['upgrade'] = true;
	}

	return $ret;
}

/**
 * 系统文件校验
 * @return array 服务器系统文件
 */
function cloud_check_file() {
	$pars['method'] = 'application.build4';
	$pars['file'] = 'application.build';
	$ret = cloud_api('site/build/index', $pars);
	if (is_error($ret)) {
		return $ret;
	}
	if ($ret['state'] == 'warning') {
		$ret['files'] = cloud_client_define();
	}
	return $ret;
}

function cloud_schema() {
	$pars['method'] = 'application.schema';
	$pars['file'] = 'application.schema';
	$ret = cloud_api('site/schema/index', $pars);
	
	if (!is_error($ret)) {
		$schemas = array();
		if (!empty($ret['schemas'])) {
			load()->func('db');
			foreach ($ret['schemas'] as $remote) {
				$name = substr($remote['tablename'], 4);
				$local = db_table_schema(pdo(), $name);
				unset($remote['increment']);
				unset($local['increment']);
				if (empty($local)) {
					$schemas[] = $remote;
				} else {
					$diffs = db_schema_compare($local, $remote);
					if (!empty($diffs)) {
						$schemas[] = $remote;
					}
				}
			}
		}
		$ret['schemas'] = $schemas;
	}
	return $ret;
}

function cloud_download($path, $type = '') {
	global $_W;
	$pars = _cloud_build_params();
	$pars['method'] = 'application.shipping';
	$pars['path'] = $path;
	$pars['type'] = $type;
	$pars['gz'] = function_exists('gzcompress') && function_exists('gzuncompress') ? 'true' : 'false';
	$pars['download'] = 'true';
	$pars['token'] = cloud_build_transtoken();
	if (starts_with($_SERVER['HTTP_USER_AGENT'], 'we7')) {
		$extra['CURLOPT_USERAGENT'] = $_SERVER['HTTP_USER_AGENT'];
	} else {
		$extra = array();
	}
	$dat = ihttp_request(CLOUD_API_DOMAIN . '/util/shipping/index', $pars, $extra);
	if (is_error($dat)) {
		return error(-1, '网络存在错误， 请稍后重试。' . $dat['message']);
	}
	if ($dat['content'] == 'success') {
		return true;
	}
	$content = @json_decode($dat['content'], true);
	if (isset($content['error'])) {
		return error(1, $content['error']);
	}
	if (is_error($content)) {
		return $content;
	} else {
		$ret = iunserializer($dat['content']);
		$gz = function_exists('gzcompress') && function_exists('gzuncompress');
		$file = base64_decode($ret['file']);
		if ($gz) {
			$file = gzuncompress($file);
		}
		$_W['setting']['site']['token'] = authcode(cache_load(cache_system_key('cloud_transtoken')), 'DECODE');
		$string = (md5($file) . $ret['path'] . $_W['setting']['site']['token']);
		if (!empty($_W['setting']['site']['token']) && md5($string) === $ret['sign']) {
			$error_file_list = array();
			if (!cloud_file_permission_pass($error_file_list)) {
				return error(-1, '请修复下列文件读写权限 : ' . implode('; ', $error_file_list));
			}
			//模块和微官网模板无需先放在data下，系统文件需放在data下以防升级时文件没有更新完而报错
			if ($type == 'module' || $type == 'theme') {
				$patch_path = IA_ROOT;
			} else {
				$patch_path = sprintf('%s/data/patch/upgrade/%s', IA_ROOT, date('Ymd'));
			}
			$path = $patch_path . $ret['path'];
			load()->func('file');
			@mkdirs(dirname($path));
			if (file_put_contents($path, $file)) {
				return true;
			} else {
				return error(-1, '写入失败，请检查是否有写入权限或是否磁盘已满！');
			}
		}
		return error(-1, '与云服务校验失败，更新缓存后重试！');
	}
}

function cloud_m_prepare($name) {
	$ret = cloud_api('module/check', array(
		'method' => 'module.check',
		'module' => $name,
	));
	if (is_error($ret)) {
		return $ret;
	}
	return true;
}

/**
 * 获取云服务应用详情
 * @param string $modulename 应用名称
 * @param string $type 附加操作类型
 * /*
 *	'install' => 安装
 *	'upgrade' => 更新
 * 	'uninstall' => 卸载
 * 	默认为空，表示没有任何附加操作
 * /
 * @return array|mixed|string
 */
function cloud_m_build($modulename, $type = 'install') {
	$type = in_array($type, array('uninstall', 'upgrade', 'install')) ? $type : 'install';
	if (empty($modulename)) {
		return array();
	}
	$module_info = cloud_m_info($modulename);
	if (is_error($module_info)) {
		return $module_info['message'];
	}

	$pars['module'] = $modulename;
	$pars['type'] = $type;
	$pars['module_version'] = $module_info['version']['version'];
	$pars['file'] = 'module.build';
	$ret = cloud_api('module/build', $pars);

	if (!is_error($ret)) {
		$dir = IA_ROOT . '/addons/' . $modulename;
		$files = array();
		if (!empty($ret['files'])) {
			foreach ($ret['files'] as $file) {
				if ($file['path'] == '/map.json') {
					continue;
				}
				$entry = $dir . $file['path'];
				if (!is_file($entry) || md5_file($entry) != $file['checksum']) {
					$files[] = '/' . $modulename . $file['path'];
				}
			}
		}
		$ret['files'] = $files;
		$schemas = array();
		if (!empty($ret['schemas'])) {
			load()->func('db');
			foreach ($ret['schemas'] as $remote) {
				$name = substr($remote['tablename'], 4);
				$local = db_table_schema(pdo(), $name);
				unset($remote['increment']);
				unset($local['increment']);
				if (empty($local)) {
					$schemas[] = $remote;
				} else {
					$diffs = db_table_fix_sql($local, $remote);
					if (!empty($diffs)) {
						$schemas[] = $remote;
					}
				}
			}
		}
		$ret['upgrade'] = true;
		$ret['type'] = 'module';
		$ret['schemas'] = $schemas;
		//如果是安装模块,根据这个标志不处理script
		$module = table('modules')->getByName($modulename);
		if (empty($module)) {
			$ret['install'] = 1;
		}
	}
	return $ret;
}

/**
 * 获取当前站点云服务所有模块详细信息
 * @return array 应用或错误信息
 */
function cloud_m_query($module = array(), $page = 1) {
	$pars['method'] = 'module.query';
	if (empty($module)) {
		$module = cloud_extra_module();
	}
	if (!is_array($module)) {
		$module = array($module);
	}
	$pars['page'] = max(1, intval($page));
	$pars['module'] = base64_encode(iserializer($module));
	$ret = cloud_api('module/query', $pars);
	if (isset($ret['error'])) {
		return error(1, $ret['error']);
	}
	if (!is_error($ret)) {
		$pirate_apps = $ret['pirate_apps'];
		unset($ret['pirate_apps']);
		$support_names = array('app', 'wxapp', 'webapp', 'system_welcome', 'android', 'ios', 'aliapp', 'baiduapp', 'toutiaoapp');
		$record_module = array();
		foreach ($ret['data'] as $modulename => &$info) {
			if(!empty($info['name'])){
				$record_module[] = $info['name'];
			}
			if (empty($info['site_branch'])) {
				continue;
			}
			foreach ($support_names as $support) {
				if (is_array($info['site_branch']['bought']) && in_array($support, $info['site_branch']['bought']) && !empty($info['site_branch']["{$support}_support"]) && $info['site_branch']["{$support}_support"] == 2) {
					$info['site_branch']["{$support}_support"] = 2;
				} else {
					$info['site_branch']["{$support}_support"] = 1;
				}
			}
		}
		$ret['pirate_apps'] = $pirate_apps;
		table('modules')->where(array('name IN' => $record_module))->fill(array('cloud_record' => STATUS_ON))->save();
	}
	return $ret;
}

function cloud_m_info($name) {
	$pars['method'] = 'module.info';
	$pars['module'] = $name;
	$ret = cloud_api('module/info', $pars);
	return $ret;
}

/**
 * 获取云服务模块更新信息详情
 * @param string $name 应用名称
 * @return array|mixed|string
 */
function cloud_m_upgradeinfo($modulename) {
	load()->model('module');

	$module = module_fetch($modulename);

	$pars['method'] = 'module.info';
	$pars['module'] = $modulename;
	$pars['curversion'] = $module['version'];
	$pars['isupgrade'] = 1;
	$ret = cloud_api('module/info', $pars);

	if (empty($ret)) {
		return array();
	}
	if (is_error($ret)) {
		return $ret;
	}
	if (version_compare($ret['version']['version'], $module['version'], '>')) {
		$ret['upgrade'] = true;
	}

	$ret['site_branch'] = $ret['branches'][$ret['version']['branch_id']];
	$ret['from'] = 'cloud';
	foreach ($ret['branches'] as &$branch) {
		if ($branch['displayorder'] > $ret['site_branch']['displayorder'] || ($branch['displayorder'] == $ret['site_branch']['displayorder'] && $ret['site_branch']['id'] < intval($branch['id']))) {
			$ret['new_branch'] = true;
		}
		$branch['id'] = intval($branch['id']);
		$branch['version']['description'] = preg_replace('/\n/', '<br/>', htmlspecialchars_decode($branch['version']['description']));
		$branch['displayorder'] = intval($branch['displayorder']);
		$branch['day'] = intval(date('d', $branch['version']['createtime']));
		$branch['month'] = date('Y.m', $branch['version']['createtime']);
		$branch['hour'] = date('H:i', $branch['version']['createtime']);
	}
	unset($branch);
	return $ret;
}

function cloud_t_prepare($name) {
	$pars['method'] = 'theme.check';
	$pars['theme'] = $name;
	$dat = cloud_api('theme/check', $pars);
	if (is_error($dat)) {
		return $dat;
	}

	return true;
}

/**
 * 获取当前站点本地和云服务所有模板详细信息
 * @return array 应用或错误信息
 */
function cloud_t_query() {
	$pars['method'] = 'theme.query';
	$pars['theme'] = cloud_extra_theme();
	$ret = cloud_api('theme/query', $pars);
	return $ret;
}

function cloud_t_info($module_name) {
	$pars['method'] = 'theme.info';
	$pars['theme'] = $module_name;
	$ret = cloud_api('theme/info', $pars);
	return $ret;
}

function cloud_t_build($module_name) {
	if (empty($module_name)) {
		return array();
	}
	$theme = table('modules')->getTemplateByName(trim($module_name));
	$pars['method'] = 'theme.build';
	$pars['theme'] = $module_name;
	if (!empty($theme)) {
		$pars['themeversion'] = $theme['version'];
	}
	$ret = cloud_api('theme/build', $pars);
	if (!is_error($ret)) {
		$dir = IA_ROOT . '/app/themes/' . $module_name;
		$files = array();
		if (!empty($ret['files'])) {
			foreach ($ret['files'] as $file) {
				if ($file['path'] == '/map.json') {
					continue;
				}
				$entry = $dir . $file['path'];
				if (!is_file($entry) || md5_file($entry) != $file['checksum']) {
					$files[] = '/' . $module_name . $file['path'];
				}
			}
		}
		$ret['files'] = $files;
		if (!empty($theme) && version_compare($theme['version'], $ret['version']) == -1) {
			$ret['upgrade'] = true;
		} else {
			$ret['upgrade'] = false;
		}
		$ret['type'] = 'theme';
		//如果是安装模块,根据这个标志不处理script
		if (empty($theme)) {
			$ret['install'] = 1;
		}
	}
	return $ret;
}

/**
 * 获取云服务模板更新信息详情
 * @param string $name 模板名称
 * @return array|mixed|string
 */
function cloud_t_upgradeinfo($module_name) {
	if (empty($module_name)) {
		return array();
	}
	$theme = table('modules')->getTemplateByName(trim($module_name));
	if (empty($theme)) {
		return array();
	}
	return cloud_api('theme/upgrade', array(
		'method' => 'theme.upgrade',
		'theme' => $theme['name'],
		'version' => $theme['version'],
		'isupgrade' => 1,
	));
}

/**
 * 发送短信
 * @param string $mobile 接收手机号
 * @param string $content 短信模板名称或短信内容
 * @param array $postdata 传输数据
 * @param string $custom_sign 用户签名
 * @param bool $use_system_balance 是否使用系统短信（短信分为系统短信和平台账号短信）
 * @return array|bool
 */
function cloud_sms_send($mobile, $content, $postdata = array(), $custom_sign = '', $use_system_balance = false) {
	global $_W;
	if (!preg_match('/^1\d{10}$/', $mobile) || empty($content)) {
		return error(1, '发送短信失败, 原因: 手机号错误或内容为空.');
	}
	$uniacid = empty($use_system_balance) ? $_W['uniacid'] : 0;

	if (empty($uniacid)) {
		$sms_info = cloud_sms_info();
		$setting_sms_blance = setting_load('system_sms_balance');
		$balance = !empty($setting_sms_blance['system_sms_balance']) ? $setting_sms_blance['system_sms_balance'] : 0;
		$setting_sms_sign = setting_load('site_sms_sign');
		$setting_sms_sign = !empty($setting_sms_sign['site_sms_sign']) ? $setting_sms_sign['site_sms_sign'] : array();
		$sign = !empty($setting_sms_sign['system_sms_sign']) ? $setting_sms_sign['system_sms_sign'] : '';
	} else {
		$row = pdo_get('uni_settings', array('uniacid' => $uniacid), array('notify'));
		$row['notify'] = @iunserializer($row['notify']);

		$config = $row['notify']['sms'];
		$balance = intval($config['balance']);

		$sign = empty($custom_sign) ? $config['signature'] : $custom_sign;
		$account_name = empty($_W['account']['type_name']) ? '' : $_W['account']['type_name'];
		$account_name .= empty($_W['account']['name']) ? '' : " [{$_W['account']['name']}] ";
	}
	if (empty($sign) || $sign == 'null') {
		$sign = '微擎';
	}
	if ($balance < 1) {
		return error(-1, '短信不足');
	}
	$pars = _cloud_build_params(false);
	$pars['method'] = 'sms.send';
	$pars['mobile'] = $mobile;
	$pars['uniacid'] = $uniacid;
	$pars['account_name'] = empty($account_name) ? '当前公众号' : $account_name;
	$pars['balance'] = $balance;
	$pars['sign'] = $sign;
	if (!empty($postdata)) {
		$pars['content'] = $content;
		$pars['postdata'] = $postdata;
	} else {
		$pars['content'] = "{$content} 【{$sign}】";
	}
	$extra = array();
	if (starts_with($_SERVER['HTTP_USER_AGENT'], 'we7')) {
		$extra['CURLOPT_USERAGENT'] = $_SERVER['HTTP_USER_AGENT'];
	}
	$response = cloud_request(CLOUD_API_DOMAIN . '/sms/send/index', $pars, $extra);
	if (is_error($response)) {
		return error($response['errno'], '短信发送失败, 原因:' . $response['message']);
	}

	$result = json_decode($response['content'], true);
	if (is_error($result)) {
		return error($result['errno'], $result['message']);
	}
	if (!empty($result['error'])) {
		return error(-1, $result['error']);
	}
	if (intval($result['errno']) != -1) {
		if (!empty($uniacid)) {
			$row['notify']['sms']['balance'] = $row['notify']['sms']['balance'] - 1;
			if ($row['notify']['sms']['balance'] < 0) {
				$row['notify']['sms']['balance'] = 0;
			}

			pdo_update('uni_settings', array('notify' => iserializer($row['notify'])), array('uniacid' => $uniacid));
			uni_setting_save('notify', $row['notify']);
		} else {
			$balance -= 1;
			if ($balance < 0) {
				$balance = 0;
			}
			setting_save($balance, 'system_sms_balance');
			$sms_info['sms_count'] = $sms_info['sms_count'] - 1;
			if ($sms_info['sms_count'] < 0) {
				$sms_info['sms_count'] = 0;
			}
			setting_save($sms_info, 'sms.info');
		}
	}
	return true;
}

/**
 * 获取当前站点可用短信签名.
 */
function cloud_sms_info() {
	return cloud_api('sms/info');
}

function cloud_sms_sign($page = 1, $start_time = 0, $end_time = 0, $status_audit = 0, $status_order = 0) {
	$data = array(
		'page' => max(1, intval($page))
	);
	if ($start_time) {
		$data['start_time'] = $start_time;
	}
	if ($end_time) {
		$data['end_time'] = $end_time;
	}
	if ($status_audit) {
		$data['status_audit'] = $status_audit;
	}
	if ($status_order) {
		$data['status_order'] = $status_order;
	}
	return cloud_api('sms/sign', $data);
}

function cloud_sms_log($mobile = 0, $time = array(), $page = 1, $page_size = 10, $status = -1) {
	$time = !empty($time) ? $time : array(strtotime('-7 days'), time());
	return cloud_api('sms/log', array(
		'mobile' => $mobile,
		'time' => $time,
		'page' => $page,
		'page_size' => $page_size,
		'status' => $status
	));
}

function cloud_sms_trade($page = 1, $start_time = 0, $end_time = 0, $status_order = 0) {
	$data = array(
		'page' => max(1, intval($page))
	);
	if ($start_time) {
		$data['start_time'] = $start_time;
	}
	if ($end_time) {
		$data['end_time'] = $end_time;
	}
	if ($status_order) {
		$data['status_order'] = $status_order;
	}
	if (empty($data['start_time']) && empty($data['end_time'])) {
		$data['start_time'] = strtotime('-1 year');
		$data['end_time'] = time();
	}
	$data['time'] = array($data['start_time'], $data['end_time']);
	return cloud_api('sms/trade', $data);
}

function cloud_sms_edit($sign) {
	return cloud_api('sms/re-audit-sign', array('sign_id' => $sign['sign_id'], 'sms_sign' => $sign['sign_content']));
}
function cloud_sms_count_remained() {
	$cache_key = cache_system_key('cloud_api', array('method' => md5('cloud_sms_count_remained')));
	$cache = cache_load($cache_key);
	if (!empty($cache) && $cache['expire'] > TIMESTAMP) {
		return $cache['cloud_sms_count_remained'];
	}
	$sms_info = cloud_sms_info();
	if (is_error($sms_info)) {
		return $sms_info;
	}
	$sms_count = $sms_info['sms_count'];
	$sms_accounts = table('uni_settings')->select(array('uniacid', 'notify'))->where(array('notify LIKE' => '%sms%'))->getall();

	$setting_sms_blance = setting_load('system_sms_balance');
	$system_sms_balance = !empty($setting_sms_blance['system_sms_balance']) ? $setting_sms_blance['system_sms_balance'] : 0;
	$sms_count -= $system_sms_balance;
	if (empty($sms_accounts)) {
		return $sms_count;
	}
	foreach ($sms_accounts as $sms_account) {
		$notify = iunserializer($sms_account['notify']);
		$sms_count -= $notify['sms']['balance'];
	}
	$sms_count = max(0, $sms_count);
	$cache_data = array(
		'cloud_sms_count_remained' => $sms_count
	);
	cache_write($cache_key, $cache_data, CACHE_EXPIRE_MIDDLE);
	return $sms_count;
}

/**
 * 获取当前站点所有公众号信息
 * @return string 公众号序列化
 */
function cloud_extra_account() {
	$data = array();
	$data['accounts'] = pdo_fetchall("SELECT name, account, original FROM " . tablename('account_wechats') . " GROUP BY account");
	return serialize($data);
}

/**
 * 获取当前站点所有已安装/未安装/回收站模块
 * @return array
 */
function cloud_extra_module() {
	load()->model('module');
	$result = array();
	$cloud = table('modules_cloud')->getUninstallModule(MODULE_CLOUD_UNINSTALL);
	if (empty($cloud)) {
		return $result;
	}
	$module_support_type = array_keys(module_support_type());
	$installed = table('modules')->getInstalled();
	$recycle = table('modules_recycle')->where('type', 2)->getall('name');
	foreach ($installed as $install_module) {
		if ($install_module['cloud_record']) {
			continue;
		}
		$result[$install_module['name']] = array(
			'name' => $install_module['name'],
			'version' => $install_module['version'],
		);
		$all_uninstall = true;
		foreach ($module_support_type as $support) {
			$type = str_replace('_support', '', $support);
			if ($install_module[$support] == 2) {
				$all_uninstall = false;
				$result[$install_module['name']]['support'][$type]['is_install'] = 2;
			}
		}
		if ($all_uninstall) {
			unset($result[$install_module['name']]);
		}
	}
	foreach ($recycle as $recycle_module) {
		if (empty($result[$recycle_module['name']])) {
			$result[$recycle_module['name']] = array(
				'name' => $recycle_module['name'],
			);
		}
		$in_recycle = false;
		foreach ($module_support_type as $support) {
			$type = str_replace('_support', '', $support);
			if ($recycle_module[$support]) {
				$in_recycle = true;
				$result[$recycle_module['name']]['support'][$type] = array(
					'is_recycle' => 2
				);
			}
		}
		if (!$in_recycle) {
			unset($result[$recycle_module['name']]);
		}
	}
	foreach ($cloud as $cloud_module) {
		if (empty($result[$cloud_module['name']])) {
			$result[$cloud_module['name']] = array(
				'name' => $cloud_module['name'],
				'version' => $cloud_module['version'],
			);
		}
		$in_cloud = false;
		foreach ($module_support_type as $support) {
			$type = str_replace('_support', '', $support);
			if ($cloud_module[$support] == 2) {
				$in_cloud = true;
				$result[$cloud_module['name']]['support'][$type] = array(
					'is_uninstall' => 2
				);
			}
		}
		if (!$in_cloud) {
			unset($result[$cloud_module['name']]);
		}
	}
	return $result;
}

/**
 * 获取当前站点所有本地模板
 * @return string 模板标识序列化
 */
function cloud_extra_theme() {
	$themes = pdo_getall('modules', array('application_type' => APPLICATION_TYPE_TEMPLATES, 'name !=' => 'default'), 'name', 'name');
	if (!empty($themes)) {
		return base64_encode(iserializer(array_keys($themes)));
	} else {
		return '';
	}
}

function cloud_module_setting($acid, $module) {
	$pars = array(
		'acid' => $acid,
		'module_name' => $module['name'],
		'module_version' => $module['version'],
	);
	return cloud_api('module/setting/index', $pars);
}

function cloud_module_setting_save($acid, $module_name, $setting) {
	$pars = array(
		'acid' => $acid,
		'module_name' => $module_name,
		'setting' => $setting,
	);
	return cloud_api('module/setting/save', $pars, array('nocache' => STATUS_ON));
}

function cloud_module_list($title, $support_type, $page = 1, $per_page = 20) {
	$pars = array(
		'title' => $title,
		'support_type' => $support_type,
		'page' => $page,
		'per_page' => $per_page,
	);
	return cloud_api('module/list', $pars);
}

function cloud_site_info() {
	return cloud_api('site/info');
}

function cloud_reset_siteinfo() {
	global $_W;
	return cloud_api('site/register/profile', array('url' => $_W['siteroot']));
}

/**
 * 小程序配置信息
 */
function cloud_wxapp_info($moduleinfo) {
	return cloud_api('wxapp/info', $moduleinfo);
}

/**
 * 获取二维码
 */
function cloud_wxapp_login_qrcode() {
	return cloud_api('wxapp/login/qr-code', array(), array('nocache' => true));
}

/**
 * 获取扫码结果
 */
function cloud_wxapp_login_qrscan($uuid) {
	return cloud_api('wxapp/login/qr-scan', $uuid);
}

/**
 * 发布小程序
 */
function cloud_wxapp_publish($data) {
	return cloud_api('wxapp/publish', $data);
}

/**
 * 下载小程序
 */
function cloud_miniapp_get_package($data) {
	return cloud_api('wxapp/publish/download', $data);
}

/**
 * 生成附件站点信息的云服务跳转地址
 * @param string $forward 回调地址
 * @param array $data 站点数据
 * @return string 跳转地址
 */
function cloud_auth_url($forward, $data = array()) {
	global $_W;
	if (!empty($_W['setting']['site']['url']) && !strexists($_W['siteroot'], $_W['setting']['site']['url'])) {
		$url = $_W['setting']['site']['url'];
	} else {
		$url = rtrim($_W['siteroot'], '/');
	}
	$auth = array();
	$auth['key'] = '';
	$auth['password'] = '';
	$auth['url'] = $url;
	$auth['referrer'] = intval($_W['config']['setting']['referrer']);
	$auth['version'] = IMS_VERSION;
	$auth['forward'] = $forward;
	$auth['family'] = IMS_FAMILY;

	if (!empty($_W['setting']['site']['key']) && !empty($_W['setting']['site']['token'])) {
		$auth['key'] = $_W['setting']['site']['key'];
		$auth['password'] = md5($_W['setting']['site']['key'] . $_W['setting']['site']['token']);
	}
	if ($data && is_array($data)) {
		$auth = array_merge($auth, $data);
	}
	$query = base64_encode(json_encode($auth));
	$auth_url = 'https://s.w7.cc/index.php?c=auth&a=passport&__auth=' . $query;
	return $auth_url;
}

/**
 * module setting cloud
 * @param array $module
 * @param string $bindings
 * @return string iframe
 */
function cloud_module_setting_prepare($module, $binding) {
	global $_W;
	$auth = _cloud_build_params();
	$auth['arguments'] = array(
		'binding' => $binding,
		'acid' => $_W['uniacid'],
		'type' => 'module',
		'module' => $module,
	);
	$iframe_auth_url = cloud_auth_url('module', $auth);

	return $iframe_auth_url;
}

/**
 * 云文件资源保存为本地资源
 * @param int $uniacid
 * @param string $type
 * @param string $url
 * @return array attachment
 */
function cloud_resource_to_local($uniacid, $type, $url) {
	global $_W;

	load()->func('file');

	$setting = $_W['setting']['upload'][$type];
	if (substr($url, 0, 2) == '//') {
		$url = 'http:' . $url;
	}

	if (!file_is_image($url)) {
		return error(1, '远程图片后缀非法,请重新上传');
	}
	$pathinfo = pathinfo($url);
	$extension = $pathinfo['extension'];

	if ($uniacid == 0) {
		$setting['folder'] = "{$type}s/global/" . date('Y/m/');
	} else {
		$setting['folder'] = "{$type}s/{$uniacid}/" . date('Y/m/');
	}

	$originname = pathinfo($url, PATHINFO_BASENAME);
	$filename = file_random_name(ATTACHMENT_ROOT . '/' . $setting['folder'], $extension);
	$pathname = $setting['folder'] . $filename;
	$fullname = ATTACHMENT_ROOT . $pathname;

	mkdirs(dirname($fullname));

	load()->func('communication');
	$response = ihttp_get($url);
	if (is_error($response)) {
		return error(1, $response['message']);
	}
	if (file_put_contents($fullname, $response['content']) == false) {
		return error(1, '提取文件失败');
	}

	if (!empty($_W['setting']['remote']['type'])) {
		$remotestatus = file_remote_upload($pathname);
		if (is_error($remotestatus)) {
			return error(1, '远程附件上传失败，请检查配置并重新上传');
		} else {
			file_delete($pathname);
		}
	}

	$data = array(
		'uniacid' => $uniacid,
		'uid' => intval($_W['uid']),
		'filename' => $originname,
		'attachment' => $pathname,
		'type' => $type == 'image' ? 1 : 2,
		'createtime' => TIMESTAMP,
	);
	pdo_insert('core_attachment', $data);

	$data['url'] = tomedia($pathname);
	$data['id'] = pdo_insertid();

	return $data;
}

function cloud_bakup_files($files) {
	global $_W;
	if (empty($files)) {
		return false;
	}
	$map = json_encode($files);
	$hash = md5($map . $_W['config']['setting']['authkey']);
	if ($handle = opendir(IA_ROOT . '/data/patch/backup/' . date('Ymd'))) {
		while (false !== ($patchpath = readdir($handle))) {
			if ($patchpath != '.' && $patchpath != '..') {
				if (strexists($patchpath, $hash)) {
					return false;
				}
			}
		}
	}

	$path = IA_ROOT . '/data/patch/backup/' . date('Ymd') . '/' . date('Hi') . '_' . $hash;
	load()->func('file');
	if (!is_dir($path) && mkdirs($path)) {
		foreach ($files as $file) {
			if (file_exists(IA_ROOT . $file)) {
				mkdirs($path . '/' . dirname($file));
				file_put_contents($path . '/' . $file, file_get_contents(IA_ROOT . $file));
			}
		}
		file_put_contents($path . '/' . 'map.json', $map);
	}
	return false;
}

function cloud_build_transtoken() {
	$pars['method'] = 'application.token';
	$pars['file'] = 'application.build';
	$ret = cloud_api('site/token/index', $pars);
	cache_write(cache_system_key('cloud_transtoken'), authcode($ret['token'], 'ENCODE'));
	return $ret['token'];
}
/**
 * 数据库更新信息
 * @param $schems array 云服务返回的数据库信息
 * @return array 数据库更新信息
 */
function cloud_build_schemas($schemas) {
	$database = array();
	if (empty($schemas) || !is_array($schemas)) {
		return $database;
	}
	foreach ($schemas as $remote) {
		$row = array();
		$row['tablename'] = $remote['tablename'];
		$name = substr($remote['tablename'], 4);
		$local = db_table_schema(pdo(), $name);
		unset($remote['increment']);
		unset($local['increment']);
		if (empty($local)) {
			$row['new'] = true;
		} else {
			$row['new'] = false;
			$row['fields'] = array();
			$row['indexes'] = array();
			$diffs = db_schema_compare($local, $remote);
			if (!empty($diffs['fields']['less'])) {
				$row['fields'] = array_merge($row['fields'], $diffs['fields']['less']);
			}
			if (!empty($diffs['fields']['diff'])) {
				$row['fields'] = array_merge($row['fields'], $diffs['fields']['diff']);
			}
			if (!empty($diffs['indexes']['less'])) {
				$row['indexes'] = array_merge($row['indexes'], $diffs['indexes']['less']);
			}
			if (!empty($diffs['indexes']['diff'])) {
				$row['indexes'] = array_merge($row['indexes'], $diffs['indexes']['diff']);
			}
			$row['fields'] = implode($row['fields'], ' ');
			$row['indexes'] = implode($row['indexes'], ' ');
		}
		$database[] = $row;
	}
	return $database;
}

/**
 * 检测网站关键文件是否有写入权限，从而判断是否执行更新操作
 * @param array $error_file_list 存放权限有问题的文件名
 * @return boolean
 */
function cloud_file_permission_pass(&$error_file_list = array()) {
	$cache_key = cache_system_key('cloud_file_permission_pass');
	$cache = cache_load($cache_key);
	if ($cache) {
		return true;
	}
	$check_path = array(
		'/api',
		'/app/common',
		'/app/resource',
		'/app/source',
		'/app/themes/default',
		'/web/common',
		'/web/resource',
		'/web/source',
		'/web/themes/default',
		'/web/themes/black',
		'/web/themes/classical',
		'/web/themes/2.0',
		'/framework/builtin',
		'/framework/class',
		'/framework/model',
		'/framework/function',
		'/framework/table',
		'/framework/library',
		'/payment',
	);

	$check_file = array(
		'/web/index.php',
		'/framework/bootstrap.inc.php',
		'/framework/version.inc.php',
		'/framework/const.inc.php',
	);
	$sub_paths = array();
	foreach ($check_path as $path) {
		$file_list = cloud_file_tree(IA_ROOT . $path);
		if (!empty($file_list)) {
			foreach ($file_list as $file) {
				if (is_file($file)) {
					$sub_path = pathinfo($file, PATHINFO_DIRNAME);
					if (empty($sub_paths[$sub_path])) {
						if (!cloud_path_is_writable($sub_path)) {
							$error_file_list[] = str_replace(IA_ROOT, '', $sub_path);
						}
						$sub_paths[$sub_path] = $sub_path;
					}
				}
				if (!is_writable($file)) {
					$error_file_list[] = str_replace(IA_ROOT, '', $file);
				}
			}
		}
	}

	foreach ($check_file as $file) {
		if (!is_writable(IA_ROOT . $file)) {
			$error_file_list[] = str_replace(IA_ROOT, '', $file);
		}
	}
	if (empty($error_file_list)) {
		cache_write($cache_key, true, 600);
		return true;
	}
	return false;
}

function cloud_file_tree($path, $include = array()) {
	$files = array();
	if (!empty($include)) {
		$ds = glob($path . '/{' . implode(',', $include) . '}', GLOB_BRACE);
	} else {
		$ds = glob($path . '/*');
	}
	if (is_array($ds)) {
		foreach ($ds as $entry) {
			if (is_file($entry)) {
				$files[] = $entry;
			}
			if (is_dir($entry)) {
				$rs = cloud_file_tree($entry);
				foreach ($rs as $f) {
					$files[] = $f;
				}
			}
		}
	}
	return $files;
}

function cloud_path_is_writable($dir) {
	$writeable = false;
	if (!is_dir($dir)) {
		@mkdir($dir, 0755);
	}
	if (is_dir($dir)) {
		if ($fp = fopen("$dir/test.txt", 'w')) {
			fclose($fp);
			unlink("$dir/test.txt");
			$writeable = true;
		} else {
			$writeable = false;
		}
	}
	return $writeable;
}

/**
 * 从云服务获取推送的消息
 * @return array()
 */
function cloud_get_store_notice() {
	return cloud_api('we7/site/notify/system');
}

/**
 * 盗版检测
 * @param string $url 可请求的盗版的URL
 */
function cloud_v_to_xs($url) {
	if (empty($url)) {
		return false;
	}
	$pars = _cloud_build_params();
	$pars['method'] = 'module.query';
	$pars['url'] = urlencode($url);
	cloud_request(CLOUD_API_DOMAIN . '/site/pirate/index', $pars);
	return true;
}

function cloud_workorder() {
	$result = cloud_api('work-order/status/index');
	return $result;
}

function cloud_account_info() {
	$site_info = cloud_site_info();
	$account_num = max(0, intval($site_info['quantity']));
	return $account_num;
}

/**
 * @param $js_secret js密匙
 * @return boolean|string
 */
function cloud_w7_request_token($js_secret, $nocache = false) {
	global $_W;
	if (empty($_W['setting']['site']) || empty($_W['setting']['site']['key'])) {
		return '';
	}
	$cache_key = cache_system_key('cloud_w7_request_token');
	if (!$nocache) {
		$cache = cache_load($cache_key);
		if ($cache) {
			return $cache;
		}
	}
	$js_token = authcode($js_secret, 'ENCODE', $_W['setting']['site']['key']);
	$data = array('js_token' => $js_token);
	$ret = cloud_api('site/accesstoken/with-js-token', $data);
	if (is_error($ret)) {
		return $ret;
	}
	cache_write($cache_key, $ret['access_token'], $ret['expire_time']);
	return $ret['access_token'];
}
