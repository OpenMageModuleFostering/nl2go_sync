<?php

class Newsletter2go_Apiextension_Model_Api2_Subscriber_Group_Rest_Admin_V1 extends Newsletter2go_Apiextension_Model_Api2_Subscriber_Group
{
    
    public function getGroups()
    {
        return $this->_retrieveCollection();
    }
    
    /**
     * Retrieve list of customers.
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $result = array();
        $groups = Mage::getModel('customer/group')->getCollection()->toArray();
        
        foreach ($groups['items'] as $group) {
            $customersCount = $collection = Mage::getResourceModel('customer/customer_collection')
                    ->addAttributeToSelect('entity_id')
                    ->addAttributeToFilter('group_id', $group['customer_group_id'])
                    ->load()->toArray();
            
            $result[] = array(
                'id' => $group['customer_group_id'],
                'name' => $group['customer_group_code'],
                'description' => '',
                'count' => count($customersCount),
            );
        }

        $result[] = array(
            'id' => 'subscribers-only',
            'name' => 'Subscribers only',
            'description' => 'Customers that are subscribed for newsletter but are not registered.',
            'count' => 0,
        );
        
        return $result;
    }
}