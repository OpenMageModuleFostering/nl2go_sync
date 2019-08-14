<?php

/**
 * NL2go newsletters subscribers list API extension
 *
 * @category   NL2go
 * @package    NL2go_Sync
 * @author     Anatoly V Koval
 */

class Nl2go_Sync_Model_Newsletter_Api_V2 extends Mage_Api_Model_Resource_Abstract 
{
	/**
	 * Update subsciber's status
	 *
	 * @param string $email
	 * @param int $status
	 * @return int
	 */
	public function setSubscriptionStatus($mail, $status){
		
		switch ($status) {
		    case 1:
		        $status = Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;
		        break;
		    case 2:
		        $status = Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE;
		        break;
		    case 3:
		        $status = Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED;
		        break;
		    case 4:
		        $status = Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED;
		        break;		        
		    default:
				$res = 400;
				return $res;		    
		}		
		
		$subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($mail);
		
		$subscrId = $subscriber->getId();
		if(empty($subscrId)){
			$res = 204;
			return $res;
		}
		
		# change status and save
//		$subscriber->setStatus($status);
//		$subscriber->save();

		// I decided to use direct sql requests, for the cases, when somebody want to create and intercept
		// event for changes of sucscribers
		/**
		* Get the resource model/write connection/table name
		*/
		$resource = Mage::getSingleton('core/resource');
     
		$writeConnection = $resource->getConnection('core_write');
 
		$table = $resource->getTableName('newsletter/subscriber');
     
		$query = "UPDATE {$table} SET subscriber_status = ".$status.",change_status_at=NOW() ".
					" WHERE "."subscriber_email = '".$mail."'";
     
		/**
		* Execute the query
		*/
		$writeConnection->query($query);		
		
		$res = 200;
		return $res;
	}
	/**
	 * Return subscriber list with the customer info
	 *
	 * @param string $file
	 * @return String
	 */
	public function getSubscribers($for_last_hours=0) {
try{
		$res = array();
		
		$customer = Mage::getModel('customer/customer');
		

		$customersList = $this->getCustomersList($for_last_hours);

		// get subscribers as registered customer
		foreach ($customersList as $k=>$v){
			if(!$this->customerExists($v["customer_id"])) continue;	// in some magento's store subscriber has non existing customer id

			$customerInfo = array();

			$customer = Mage::getModel('customer/customer')->load($v["customer_id"]);

			$gender_code = $customer->getGender();
			if(!is_null($gender_code))
				$customerInfo['gender'] = $customer->getAttribute("gender")->getSource()->getOptionText($gender_code);

						$customerInfo['customer_id'] = $customer->getId();
						$customerInfo['created_at'] = $customer->getCreatedAt();
						$customerInfo['updated_at'] = $customer->getUpdatedAt();
						$customerInfo['store_id'] = $customer->getStoreId();
						$customerInfo['website_id'] = $customer->getWebsiteId();
						$customerInfo['created_in'] = $customer->getCreatedIn();
						$customerInfo['email'] = $customer->getEmail();
						$customerInfo['firstname'] = $customer->getFirstname();
						$customerInfo['lastname'] = $customer->getLastname();
						$customerInfo['group_id'] = $customer->getGroupId();

			$customerTotals = $this->getCustomerTotals($v["customer_id"]);

			$customerLifetimeSales = $customerTotals['lifetime'];//->getLifetime();
			$customerNumberOfOrders = $customerTotals['num_orders'];//->getNumOrders();
			
			$subscriber = Mage::getModel('newsletter/subscriber')->load($v["subscriber_id"]);
			
			$res[] = array(
				"subscriber_id"=>$v["subscriber_id"],
				"subscriber_status"=>$v["subscriber_status"],
				"store_id"=>$subscriber->getData("store_id"),
				"change_status_at"=>$subscriber->getData("change_status_at"),
				"subscriber_email"=>$subscriber->getData("subscriber_email"),
				"total_sales"=>$customerLifetimeSales,
				"avg_sales"=>$customerNumberOfOrders!=0?$customerLifetimeSales/$customerNumberOfOrders:0,
				"total_orders"=>$customerNumberOfOrders,
				"customer_info"=>$customerInfo
				);

			if(method_exists($customer, "clearInstance"))
				$customer->clearInstance();
			unset($customer);
			if(method_exists($subscriber, "clearInstance"))
				$subscriber->clearInstance();
			unset($subscriber);
		}

		// get simple subscribers, as unregistered customers
		
		$subscribersList = $this->getSimpleSubscribers($for_last_hours);
		foreach ($subscribersList as $k=>$v){
			$subscriber = Mage::getModel('newsletter/subscriber')->load($v["subscriber_id"]);
			$res[] = array(
				"subscriber_id"=>$v["subscriber_id"],
				"subscriber_status"=>$v["subscriber_status"],
				"store_id"=>$subscriber->getData("store_id"),
				"change_status_at"=>$subscriber->getData("change_status_at"),
				"subscriber_email"=>$subscriber->getData("subscriber_email"),
				"total_sales"=>0,
				"avg_sales"=>0,
				"total_orders"=>0,
				"customer_info"=>null
				);

			if(method_exists($subscriber, "clearInstance"))
				$subscriber->clearInstance();
			unset($subscriber);
		}

}catch(Exception $e){
	Mage::log("Exception:\n".$e->getTraceAsString(), null, "mem.log");
}
		return $res;
	}
	/*
	 * return true, if customer with the given id exists in the store
	 */
	private function customerExists($cid){
		$resource = Mage::getSingleton('core/resource');
		/*		Retrieve the read connection */
		$readConnection = $resource->getConnection('core_read');

		$query = "SELECT * FROM `".$resource->getTableName('customer/entity')."` ".
			" WHERE entity_id=".$cid;
		/* 		Execute the query and store the results in $results 		*/
		$results = $readConnection->fetchAll($query);
		if(count($results)>0) return true;
		return false;
	}
	/*
	 * Return customer totals info
	 */
	private function getCustomerTotals($cid){
		$resource = Mage::getSingleton('core/resource');
		/*		Retrieve the read connection */
		$readConnection = $resource->getConnection('core_read');

		$query = "SELECT `sales`.`store_id`, SUM(sales.base_grand_total) AS `lifetime`, SUM(sales.base_grand_total * sales.base_to_global_rate) AS `base_lifetime`, AVG(sales.base_grand_total) AS `avgsale`, AVG(sales.base_grand_total * sales.base_to_global_rate) AS `base_avgsale`, COUNT(sales.base_grand_total) AS `num_orders` ".
//				"FROM `".$resource->getTableName('sales_flat_order')."` AS `sales` ".
				"FROM `sales_flat_order` AS `sales` ".
				"WHERE (sales.customer_id = '".$cid."') AND (state NOT IN('canceled')) ".
				"GROUP BY `sales`.`store_id`";
		/* 		Execute the query and store the results in $results 		*/
		$results = $readConnection->fetchAll($query);

		$res = array(
			'lifetime' => 0, 'base_lifetime' => 0, 'base_avgsale' => 0, 'num_orders' => 0, 'avgsale'=>0);

		foreach ($results as $k=>$v){
			foreach($res as $key=>$val){
				$res[$key] = $res[$key] + $v[$key];
			}
		}
		if ($res['num_orders']) {
			$res['avgsale'] = $res['base_lifetime'] / $res['num_orders'];
		}
		return $res;
	}
	/**
	 * Return simple subscribers list
	 * 
	 * @param int $hours
	 */
	private function getSimpleSubscribers($hours){
		$resource = Mage::getSingleton('core/resource');
		/*		Retrieve the read connection */
		$readConnection = $resource->getConnection('core_read');
		
		// it is impossible to filter subscribers by date, because there are cases when the dates are NULL
		// get all subscribers
		
		if($hours==0){
			$query = "SELECT * FROM newsletter_subscriber ".
				" WHERE subscriber_status=".Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED.
					" AND customer_id=0";
		} else {
		$query = "SELECT * FROM newsletter_subscriber ".
					" WHERE customer_id=0 AND (change_status_at IS NULL OR change_status_at BETWEEN TIMESTAMP(DATE_SUB(NOW(), INTERVAL ".$hours." HOUR)) AND TIMESTAMP(NOW()))";
		}
		
		/* 		Execute the query and store the results in $results 		*/
		$results = $readConnection->fetchAll($query); 	
		
		$res = array();
		foreach ($results as $k=>$v){
			$res[] = array("subscriber_id"=>$v["subscriber_id"], "subscriber_status"=>$v["subscriber_status"]);
		}
		return $res;
	}
	/**
	 * Return subscribers (customer) list for selected time interval
	 *
	 * @param int $hours
	 */
	private function getCustomersList($hours){
		$resource = Mage::getSingleton('core/resource');
		/*		Retrieve the read connection */
		$readConnection = $resource->getConnection('core_read');
		
		/*		Retrieve our data for customers	*/
		if($hours==0){
			$query = "SELECT * FROM ".$resource->getTableName('newsletter/subscriber')." ".
				" WHERE subscriber_status=".Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED.
					" AND customer_id!=0";
		} else {
			$query = "SELECT * FROM newsletter_subscriber ".
					" WHERE customer_id IN(".
						"SELECT entity_id FROM customer_entity ".
							" WHERE created_at BETWEEN TIMESTAMP(DATE_SUB(NOW(), INTERVAL ".$hours." HOUR)) AND TIMESTAMP(NOW()) OR ".
							" updated_at BETWEEN TIMESTAMP(DATE_SUB(NOW(), INTERVAL ".$hours." HOUR)) AND TIMESTAMP(NOW())".
					")";
		}
		
		/* 		Execute the query and store the results in $results 		*/
		$results = $readConnection->fetchAll($query); 	
		
		$res = array();
		foreach ($results as $k=>$v){
			$res[] = array("subscriber_id"=>$v["subscriber_id"], "subscriber_status"=>$v["subscriber_status"],"customer_id"=>$v["customer_id"]);
		}
		return $res;
	}
}
?>