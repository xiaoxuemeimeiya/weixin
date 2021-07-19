<?php
/**
 * [WeEngine System] Copyright (c) 2014 W7.CC
 */
namespace We7\Table\Account;

class Wechats extends \We7Table {
	protected $tableName = 'account_wechats';
	protected $primaryKey = 'acid';
	protected $field = array(
		'uniacid',
		'token',
		'encodingaeskey',
		'auth_refresh_token',
		'level',
		'name',
		'account',
		'original',
		'signature',
		'country',
		'province',
		'city',
		'username',
		'password',
		'lastupdate',
		'key',
		'secret',
		'styleid',
		'subscribeurl',
		'createtime',
	);
	protected $default = array(
		'uniacid' => '',
		'token' => '',
		'encodingaeskey' => '',
		'auth_refresh_token' => '',
		'level' => '0',
		'name' => '',
		'account' => '',
		'original' => '',
		'signature' => '',
		'country' => '',
		'province' => '',
		'city' => '',
		'username' => '',
		'password' => '',
		'lastupdate' => '0',
		'key' => '',
		'secret' => '',
		'styleid' => '1',
		'subscribeurl' => '',
		'createtime' => '',
	);
	public function getAccount($uniacid) {
		return $this->query->where('uniacid', $uniacid)->get();
	}
	public function searchWithAccount() {
		return $this->query->from($this->tableName, 't')
			->leftjoin('account', 'a')
			->on(array('t.uniacid' => 'a.uniacid'));
	}

	public function searchWithsearchWithAccountAndUniAccountUsers() {
		return $this->query->from($this->tableName, 't')
			->leftjoin('account', 'a')
			->on(array('t.uniacid' => 'a.uniacid'))
			->leftjoin('uni_account_users', 'u')
			->on(array('u.uniacid' => 't.uniacid'));
	}
}