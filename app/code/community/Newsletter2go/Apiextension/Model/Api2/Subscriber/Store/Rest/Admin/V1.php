<?php

class Newsletter2go_Apiextension_Model_Api2_Subscriber_Store_Rest_Admin_V1 extends Newsletter2go_Apiextension_Model_Api2_Subscriber_Store
{
    /**
     * Retrieve list of customers.
     *
     * @return mixed
     */
    protected function _retrieveCollection()
    {
        $iDefaultStoreId = Mage::app()
            ->getWebsite(true)
            ->getDefaultGroup()
            ->getDefaultStoreId();
        $result = array();
        $stores = Mage::app()->getStores();
        foreach ($stores as $store) {

            $tmp = $store->toArray();

            $tmp['id'] = $tmp['store_id'];
            $tmp['default'] = ($tmp['store_id'] == $iDefaultStoreId ? 1 : 0);

            $result[] = $tmp;
        }
        return $result;
    }
}