<?php

class Lumbwi_Sitemap_Helper_Data extends Mage_Core_Helper_Data
{
    /**
     * Limits xpath config settings
     */
    const XML_PATH_MAX_LINES = 'sitemap/limit/max_lines';

    /**
     * @param $storeId
     * @return mixed
     */
    public function getMaximumLinesNumber($storeId)
    {
        return Mage::getStoreConfig(self::XML_PATH_MAX_LINES, $storeId);
    }
}