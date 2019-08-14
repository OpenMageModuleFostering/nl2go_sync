<?php

class Newsletter2go_Apiextension_Model_Api2_Subscriber_Version_Rest_Admin_V1 extends Newsletter2go_Apiextension_Model_Api2_Subscriber_Version
{
    /**
     * Retrieve list of customers.
     *
     * @return mixed
     */
    protected function _retrieveCollection()
    {
        return array(array('version' =>3104));
    }
}