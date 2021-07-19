<?php
/**
 * [WeEngine System] Copyright (c) 2014 W7.CC
 */
namespace We7\Table\Site;

class Styles extends \We7Table {
	protected $tableName = 'site_styles';
	protected $primaryKey = 'id';
	protected $field = array(
		'uniacid',
		'templateid',
		'name',
	);
	protected $default = array(
		'uniacid' => '',
		'templateid' => '',
		'name' => '',
	);

	public function searchWithTemplates($fields = 'a.*') {
		return $this->query->from('site_styles', 'a')->select($fields)->leftjoin('modules', 'b')->on(array('a.templateid' => 'b.mid'));
	}

}