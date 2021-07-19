<?php
/**
 *
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */

defined('IN_IA') or exit('Access Denied');

class StoreTable extends We7Table {

	/**获取站内商城的商品展示列表
	 * @param $type string 1.模块 2.公众号 3.小程序
	 */
	public function searchGoodsList($type = '', $pageindex, $pagesize) {
		$this->query->from('site_store_goods');
		if (!empty($type)) {
			$this->query->where('type', $type);
		}
		$goods_list = array(
			'goods_list' => $this->searchWithPage($pageindex, $pagesize)->getall('id'),
			'total' => $this->getLastQueryTotal()
		);
		return $goods_list;
	}

	/**获取公众号在站内商城购买的物品
	 * @param $uniacid string 1.指定的公众号
	 * @param $type string 1.商品类型
	 */
	public function searchAccountBuyGoods($uniacid, $type) {
		$this->query->from('site_store_goods', 'g')
			->leftjoin('site_store_order', 'r')
			->on(array('g.id' => 'r.goodsid'))
			->where('g.type', $type)
			->where('r.type', STORE_ORDER_FINISH);

		if ($type == STORE_TYPE_API) {
			$number_list = $this->query->where('r.uniacid', $uniacid)->select('(g.api_num * r.duration) as number')->getall('number');
			return array_sum(array_keys($number_list));

		} else{
			$this->query->where(function ($query) use ($uniacid) {
				$query->where('r.uniacid', $uniacid)->whereor('r.wxapp', $uniacid);
			});

			load()->model('store');
			$all_type = store_goods_type_info();
			if ($all_type[$type]['group'] == 'module') {
				$keyfield = 'module';
			} else {
				$type_name = array(
					STORE_TYPE_PACKAGE => 'module_group',
				);
				$keyfield = empty($type_name[$type]) ? '' : $type_name[$type];
			}
			return $this->query->getall($keyfield);
		}
	}

	public function searchWithEndtime() {
		$this->query->where('r.endtime >=', time());
		return $this;
	}

	public function searchWithKeyword($title) {
		if (!empty($title)) {
			$this->query->where('title LIKE', "%{$title}%");
			return $this;
		}
	}


	public function searchWithStatus($status) {
		$status = intval($status) > 0 ? 1 : 0;
		$this->query->where('status', $status);
		return $this;
	}

	public function searchWithLetter($letter) {
		if (!empty($letter)) {
			$this->query->where('title_initial LIKE', "%{$letter}%");
			return $this;
		}
	}

	public function searchWithIsWish($is_wish) {
		$this->query->where('is_wish', $is_wish);
		return $this;
	}

	public function searchWithOrderid($orderid) {
		if (!empty($orderid)) {
			$this->query->where('orderid', $orderid);
			return $this;
		}
		return true;
	}

	public function goodsInfo($id) {
		return $this->query->from('site_store_goods')->where('id', $id)->getall('id');
	}

	public function searchOrderList($pageindex = 0, $pagesize = 0) {
		$this->query->from('site_store_order')->where('type <>', STORE_GOODS_STATUS_DELETE);

		if (!empty($pageindex) && !empty($pagesize)) {
			$this->searchWithPage($pageindex, $pagesize);
		}
		$lists = $this->query->orderby('id', 'desc')->getall();
		return $lists;
	}

	public function searchOrderType($type, $ortype = 0) {
		$type = intval($type);
		if (!empty($ortype)) {
			$ortype = intval($ortype);
		}
		if (!empty($ortype)) {
			$this->query->where('type in', array($type, $ortype));
		} else {
			$this->query->where('type', $type);
		}
		return $this;
	}

	public function searchOrderInfo($id) {
		return $this->query->from('site_store_order')->where('id', $id)->getall('id');
	}

	public function searchOrderWithUid($uid) {
		$uid = intval($uid);
		$this->query->where('buyerid', $uid);
		return $this;
	}

	public function searchHaveModule($type = STORE_TYPE_MODULE) {
		$this->query->from('site_store_goods');
		$result = $this->query->where('type', $type)->where('status !=', STORE_GOODS_STATUS_DELETE)->getall();
		return $result;
	}

	public function apiOrderWithUniacid($uniacid)
	{
		$this->query->from ('site_store_order', 'r')->leftjoin ('site_store_goods', 'g')->select ('r.duration, g.api_num, g.price')
			->on (array ('r.goodsid' => 'g.id'))->where ('g.type', STORE_TYPE_API)->where ('uniacid', $uniacid)->where ('type', STORE_ORDER_FINISH);
		$list = $this->query->getall ();
		return $list;
	}

	public function StoreCreateAccountInfo($uniacid) {
		return $this->query->from('site_store_create_account')->where('uniacid', $uniacid)->get();
	}

	public function searchUserBuyAccount($uid) {
		$sql = "SELECT SUM(b.account_num) FROM " . tablename('site_store_order') . " as a left join " . tablename('site_store_goods') . " as b on a.goodsid = b.id WHERE a.buyerid = :buyerid AND a.type = 3 AND b.type = 2" ;
		$count = pdo_fetchcolumn($sql, array(':buyerid' => $uid));
		$isdeleted_account_sql = "SELECT COUNT(*) FROM " . tablename('site_store_create_account') . " as a LEFT JOIN " . tablename('account') . " as b ON a.uniacid = b.uniacid WHERE a.uid = :uid AND a.type = 1 AND (b.isdeleted = 1 OR b.uniacid is NULL)";
		$deleted_account = pdo_fetchcolumn($isdeleted_account_sql, array(':uid' => $uid));
		return $count - $deleted_account;
	}

	public function searchUserBuyWxapp($uid) {
		$sql = "SELECT SUM(b.wxapp_num) FROM " . tablename('site_store_order') . " as a left join " . tablename('site_store_goods') . " as b on a.goodsid = b.id WHERE a.buyerid = :buyerid AND a.type = 3 AND b.type = 3" ;
		$count = pdo_fetchcolumn($sql, array(':buyerid' => $uid));
		$isdeleted_account_sql = "SELECT COUNT(*) FROM " . tablename('site_store_create_account') . " as a LEFT JOIN " . tablename('account') . " as b ON a.uniacid = b.uniacid WHERE a.uid = :uid AND a.type = 4 AND (b.isdeleted = 1 OR b.uniacid is NULL)";
		$deleted_account = pdo_fetchcolumn($isdeleted_account_sql, array(':uid' => $uid));
		return $count - $deleted_account;
	}

	public function searchUserBuyPackage($uniacid) {
		$sql = "SELECT * FROM " . tablename('site_store_order') . " as a left join " . tablename('site_store_goods') . " as b on a.goodsid = b.id WHERE (a.uniacid = :uniacid OR a.wxapp = :wxapp) AND a.type = 3 AND b.type = 5" ;
		return pdo_fetchall($sql, array(':uniacid' => $uniacid, ':wxapp' => $uniacid), 'module_group');
	}

	public function searchUserCreateAccountNum($uid) {
		$count = $this->query->from('site_store_create_account', 'a')->leftjoin('account', 'b')->on('a.uniacid', 'b.uniacid')->where('a.uid', $uid)->where('b.type', 1)->select('count(*) as count')->get('count');
		return $count['count'];
	}

	public function searchUserCreateWxappNum($uid) {
		$count = $this->query->from('site_store_create_account', 'a')->leftjoin('account', 'b')->on('a.uniacid', 'b.uniacid')->where('a.uid', $uid)->where('b.type', 4)->select('count(*) as count')->get('count');
		return $count['count'];
	}

	public function searchPaymentsOrder() {
		return  $this->query->from('site_store_order', 'a')->leftjoin('site_store_goods', 'b')->on('a.goodsid', 'b.id')->where('a.type', 3)->orderby('a.createtime', 'desc')->select(array('a.id', 'a.createtime', 'b.title', 'a.orderid', 'b.type', 'a.amount'))->getall();
	}

	public function getStatisticsOrderInfoByDate($starttime, $endtime) {
		$sql = "SELECT COUNT(id) AS total_orders, SUM(amount) AS total_amounts FROM " . tablename('site_store_order') . " WHERE type=3 AND createtime >= :starttime AND createtime <= :endtime";
		return pdo_fetch($sql, array(':starttime' => $starttime, ':endtime' => $endtime));
	}
}