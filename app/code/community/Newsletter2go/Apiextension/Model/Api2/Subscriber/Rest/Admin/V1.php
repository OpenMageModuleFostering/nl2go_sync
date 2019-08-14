<?php

class Newsletter2go_Apiextension_Model_Api2_Subscriber_Rest_Admin_V1 extends Newsletter2go_Apiextension_Model_Api2_Subscriber
{

    /**
     * Retrieve list of customers.
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $prefix = Mage::getConfig()->getTablePrefix();

        $group = $this->getRequest()->getParam('group');
        $hours = $this->getRequest()->getParam('hours');
        $subscribed = $this->getRequest()->getParam('subscribed');
        $fields = $this->getRequest()->getParam('fields');
        $limit = $this->getRequest()->getParam('limit');
        $offset = $this->getRequest()->getParam('offset');
        $email_str = $this->getRequest()->getParam('emails');
        $debug = $this->getRequest()->getParam('debug');
        $emails = null;

        try {
            if (strlen($email_str) > 0) {
                $emails = explode(',', $email_str);
            }

            if ($group == 'subscribers-only') {
                $subscribers = $this->getSubscribersOnly($subscribed, $limit, $offset, $fields, $emails);
                return array('items' => array($subscribers['items']));
            }

            $subscribedCond = 1;
            if ($subscribed) {
                $subscribedCond = $prefix . 'newsletter_subscriber.subscriber_status = ' .
                    Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;
            }

            $fieldsCond = $this->arrangeFields($fields);
            $collection = Mage::getResourceModel('customer/customer_collection');
            $collection->getSelect()->reset(Zend_Db_Select::COLUMNS);
            $collection->getSelect()->columns($fieldsCond['select']);

            //Join with subscribers
            if ($subscribed || !empty($fieldsCond['subs'])) {
                $collection->getSelect()
                    ->joinLeft($prefix . 'newsletter_subscriber',
                        'e.entity_id =' . $prefix . 'newsletter_subscriber.customer_id', $fieldsCond['subs'])
                    ->where($subscribedCond);
            }

            //Join with order
//            if (!empty($fieldsCond['order'])) {
//                $collection->getSelect()
//                    ->joinLeft($prefix . 'sales_flat_order', 'e.entity_id =' . $prefix . 'sales_flat_order.customer_id',
//                        $fieldsCond['order']);
//            }

            if ($group !== null) {
                $collection->addAttributeToFilter('group_id', $group);
            }
            if ($emails !== null) {
                $collection->addAttributeToFilter('email', array('in' => $emails));
            }

            if ($hours && is_numeric($hours)) {
                $ts = date('Y-m-d H:i:s', time() - 3600 * $hours);
                $collection->addAttributeToFilter('updated_at', array('gteq' => $ts));
            }

            $collection->addAttributeToSelect($fieldsCond['custom']);
            $collection->getSelect()->group('e.entity_id');
            if ($limit) {
                $offset = $offset ? $offset : 0;
                $collection->getSelect()->limit($limit, $offset);
            }

            $customers = $collection->load()->toArray($fields);

            foreach ($customers as &$customer) {
                if (isset($customer['default_shipping'])) {
                    $customer['default_shipping'] =
                        json_encode(Mage::getModel('customer/address')->load($customer['default_shipping'])->toArray());
                }

                if (isset($customer['default_billing'])) {
                    $customer['default_billing'] =
                        json_encode(Mage::getModel('customer/address')->load($customer['default_billing'])->toArray());
                }
            }

            return array('items' => array($customers));
        }catch(Exception $e){
            if($debug == 1){
                echo $e->getMessage();
                echo $e->getTraceAsString();
                die();
            }else{
                return array('errorcode'=> 'int-0-600', 'message' => 'an error occurred: '. $e->getMessage() );
            }
        }
    }

    /**
     * @param array $data
     * @return string
     */
    protected function _update($data)
    {
        $email = $this->getRequest()->getParam('email');
        $status = $this->getRequest()->getParam('status');
        /** @var Mage_Newsletter_Model_Subscriber $subs */
        $subs = Mage::getModel('newsletter/subscriber');
        $subs = $subs->loadByEmail($email);
        try {
            if ($subs !== false && $subs->getData() != null) {
                $status = $status == 0 ? Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED :
                    Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;
                $subs->setStatus($status);
                $subs->save();
            } else {
                $this->getResponse()->setHttpResponseCode(400);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_INTERNAL_ERROR);
        }
    }

    private function arrangeFields(&$fields)
    {
        $prefix = Mage::getConfig()->getTablePrefix();
        $result = array(
            'subs' => array(),
            'custom' => array(),
            'order' => array(),
            'select' => array('e.entity_id'),
        );
        if (!$fields) {
            $fields = Mage::getModel('apiextension/api2_subscriber_fields_rest_admin_v1')->getFields();
            $fieldsArray = array();
            foreach ($fields as $field) {
                $fieldsArray[] = $field['id'];
            }
        } else {
            $fieldsArray = explode(',', $fields);
        }

        $fields = $fieldsArray;
        foreach ($fieldsArray as $field) {
            switch ($field) {
                case 'subscriber_status':
                    $result['subs'][] = $prefix . 'newsletter_subscriber.' . $field;
                    break;
//                case 'totalorders':
//                    $result['order'][] = 'count(' . $prefix . 'sales_flat_order.base_grand_total) as totalorders';
//                    break;
//                case 'totalrevenue':
//                    $result['order'][] = 'sum(' . $prefix . 'sales_flat_order.base_grand_total) as totalrevenue';
//                    break;
//                case 'averagecartsize':
//                    $result['order'][] = 'avg(' . $prefix . 'sales_flat_order.base_grand_total) as averagecartsize';
//                    break;
//                case 'lastorder':
//                    $result['order'][] = 'max(' . $prefix . 'sales_flat_order.created_at) as lastorder';
//                    break;
                case 'entity_type_id':
                case 'attribute_set_id':
                case 'website_id':
                case 'email':
                case 'group_id':
                case 'increment_id':
                case 'store_id':
                case 'created_at':
                case 'updated_at':
                case 'is_active':
                case 'disable_auto_group_change':
                    $result['select'][] = 'e.' . $field;
                    break;
                case 'entity_id':
                    break;
                default:
                    $result['custom'][] = $field;
            }
        }

        return $result;
    }

    /**
     * @param $subscribed
     * @param $limit
     * @param $offset
     * @param $fields
     * @param $emails
     * @return array
     */
    private function getSubscribersOnly($subscribed, $limit, $offset, $fields, $emails)
    {
        /** @var Mage_Newsletter_Model_Resource_Subscriber_Collection $collection */
        $collection = Mage::getResourceModel('newsletter/subscriber_collection');
        $collection->addFieldToFilter('main_table.customer_id', 0);
        $collection->addFieldToSelect('subscriber_email', 'email');
        $collection->addFieldToSelect('store_id');
        $collection->addFieldToSelect('subscriber_status');

        if ($subscribed) {
            $collection->addFieldToFilter('main_table.subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
        }

        if (!empty($emails)) {
            $collection->addFieldToFilter('main_table.subscriber_email', array('in' => $emails));
        }

        if ($limit) {
            $offset = $offset ? $offset : 0;
            $collection->getSelect()->limit($limit, $offset);
        }

        $customers = $collection->load()->toArray(strlen($fields) > 0 ? explode(',', $fields) : $fields);

        return $customers;
    }
}
