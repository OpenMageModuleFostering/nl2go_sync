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
	 * Return subscriber list with the customer info
	 *
	 * @param string $file
	 * @return String
	 */
	public function getSubscribers($for_last_hours=0) {

		$res = array();
		
		$customer = Mage::getModel('customer/customer');
		
		$subscriber = Mage::getModel('newsletter/subscriber');
		
		$customersList = $this->getCustomersList($for_last_hours);

		// get subscribers as registered customer
		foreach ($customersList as $k=>$v){

			$customerInfo = Mage::getModel('customer/customer_api')->info($v["customer_id"]);

			$customer = Mage::getModel('customer/customer')->load($v["customer_id"]);
			$gender_code = $customer->getGender();
			if(!is_null($gender_code))
			$customerInfo['gender'] = $customer->getAttribute("gender")->getSource()->getOptionText($gender_code);

			$customerTotals = Mage::getResourceModel('sales/sale_collection')
				->setOrderStateFilter(Mage_Sales_Model_Order::STATE_CANCELED, true) 
     			->setCustomerFilter($customer)
     			->load()
     			->getTotals();
			$customerLifetimeSales = $customerTotals->getLifetime();
			$customerNumberOfOrders = $customerTotals->getNumOrders();
			
			$subscriber->load($v["subscriber_id"]);
			
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
			if(method_exists($customerTotals, "clearInstance"))
				$customerTotals->clearInstance();
			unset($customerTotals);
		}
		
		// get simple subscribers, as unregistered customers
		
		$subscribersList = $this->getSimpleSubscribers($for_last_hours);
		foreach ($subscribersList as $k=>$v){
			$subscriber->load($v["subscriber_id"]);
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
		
		$query = "SELECT * FROM newsletter_subscriber ".
//				" WHERE subscriber_status=".Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED.
//					" AND customer_id=0 AND (change_status_at IS NULL OR change_status_at BETWEEN TIMESTAMP(DATE_SUB(NOW(), INTERVAL ".$hours." HOUR)) AND TIMESTAMP(NOW()))";
					" WHERE customer_id=0 AND (change_status_at IS NULL OR change_status_at BETWEEN TIMESTAMP(DATE_SUB(NOW(), INTERVAL ".$hours." HOUR)) AND TIMESTAMP(NOW()))";
		
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
			$query = "SELECT * FROM newsletter_subscriber ".
				" WHERE subscriber_status=".Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED.
					" AND customer_id!=0";
		} else {
			$query = "SELECT * FROM newsletter_subscriber ".
//				" WHERE subscriber_status=".Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED.
//					" AND customer_id IN(".
// Unsubscribes over Magento user accounts are being synchronized - changes
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