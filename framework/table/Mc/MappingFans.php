<?php
/**
 * [WeEngine System] Copyright (c) 2014 W7.CC
 */
namespace We7\Table\Mc;

class MappingFans extends \We7Table {
	protected $tableName = 'mc_mapping_fans';
	protected $primaryKey = 'fanid';
	protected $field = array(
		'acid',
		'uniacid',
		'uid',
		'openid',
		'nickname',
		'groupid',
		'salt',
		'follow',
		'followtime',
		'unfollowtime',
		'tag',
		'updatetime',
		'unionid',
		'user_from',
	);
	protected $default = array(
		'acid' => '',
		'uniacid' => '0',
		'uid' => '0',
		'openid' => '',
		'nickname' => '',
		'groupid' => '',
		'salt' => '',
		'follow' => '1',
		'followtime' => '',
		'unfollowtime' => '',
		'tag' => '',
		'updatetime' => '0',
		'unionid' => '',
		'user_from' => '',
	);

	public function searchWithUniacid($uniacid) {
		$this->query->where('uniacid', $uniacid);
		return $this;
	}

	public function searchWithOpenid($openid) {
		$this->query->where('openid', $openid);
		return $this;
	}

	public function searchWithUnionid($unionid) {
		$this->query->where('unionid', $unionid);
		return $this;
	}
}