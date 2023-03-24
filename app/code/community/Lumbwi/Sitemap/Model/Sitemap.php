<?php

class Lumbwi_Sitemap_Model_Sitemap extends Mage_Sitemap_Model_Sitemap
{
    /**
     * Rows of sitemap
     * @var array
     */
    protected $_rows = [];

    /**
     * @var array
     */
    protected $_siteMapFileNames = [];

    /**
     * Generate XML file
     *
     * @return $this
     * @throws Throwable
     */
    public function generateXml()
    {
        $io = new Varien_Io_File();
        $io->setAllowCreateFolders(true);
        $io->open(['path' => $this->getPath()]);

        $storeId = $this->getStoreId();
        $date    = Mage::getSingleton('core/date')->gmtDate('Y-m-d');
        $baseUrl = Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);

        $this->setSiteMapRows($storeId);

        Mage::dispatchEvent('sitemap_urlset_generating_before', [
            'file'      => $io ,
            'base_url'  => $baseUrl ,
            'date'      => $date,
            'store_id'  => $storeId
        ]);

        if (!$this->_isSplitRequired()) {
            $this->generateNoSplitXml($io);
        } else {
            $this->generateSplitXml($io, $baseUrl, $date, $storeId);
        }

        $this->setSitemapTime(
            Mage::getSingleton('core/date')->gmtDate(Varien_Db_Adapter_Pdo_Mysql::TIMESTAMP_FORMAT)
        );
        $this->save();

        return $this;
    }

    /**
     * @param $storeId
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function setSiteMapRows($storeId)
    {
        $date    = Mage::getSingleton('core/date')->gmtDate('Y-m-d');
        $baseUrl = Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);

        /**
         * Generate categories sitemap data
         */
        $collection = Mage::getResourceModel('sitemap/catalog_category')->getCollection($storeId);
        $categories = new Varien_Object();
        $categories->setItems($collection);

        Mage::dispatchEvent('sitemap_categories_generating_before', [
            'collection' => $categories,
            'store_id' => $storeId
        ]);

        foreach ($categories->getItems() as $item) {
            $xml = $this->getSitemapRow(
                $baseUrl . $item->getUrl(),
                Mage::getStoreConfigFlag('sitemap/category/lastmod', $storeId) ? $date : '',
                (string)Mage::getStoreConfig('sitemap/category/changefreq', $storeId),
                (string)Mage::getStoreConfig('sitemap/category/priority', $storeId)
            );
            $this->_rows[] = $xml;
        }
        unset($collection);

        /**
         * Generate products sitemap data
         */
        $collection = Mage::getResourceModel('sitemap/catalog_product')->getCollection($storeId);
        $products = new Varien_Object();
        $products->setItems($collection);

        Mage::dispatchEvent('sitemap_products_generating_before', [
            'collection' => $products,
            'store_id' => $storeId
        ]);
        foreach ($products->getItems() as $item) {
            $xml = $this->getSitemapRow(
                $baseUrl . $item->getUrl(),
                Mage::getStoreConfigFlag('sitemap/product/lastmod', $storeId) ? $date : '',
                (string)Mage::getStoreConfig('sitemap/product/changefreq', $storeId),
                (string)Mage::getStoreConfig('sitemap/product/priority', $storeId)
            );
            $this->_rows[] = $xml;
        }
        unset($collection);

        /**
         * Generate cms pages sitemap data
         */
        $collection = Mage::getResourceModel('sitemap/cms_page')->getCollection($storeId);
        $pages = new Varien_Object();
        $pages->setItems($collection);
        Mage::dispatchEvent('sitemap_cms_pages_generating_before', [
            'collection' => $pages,
            'store_id' => $storeId
        ]);
        foreach ($pages->getItems() as $item) {
            $url = $item->getUrl();
            if ($url == (string)Mage::getStoreConfig('web/default/cms_home_page', $storeId)) {
                $url = '';
            }

            $xml = $this->getSitemapRow(
                $baseUrl . $url,
                Mage::getStoreConfigFlag('sitemap/page/lastmod', $storeId) ? $date : '',
                (string)Mage::getStoreConfig('sitemap/page/changefreq', $storeId),
                (string)Mage::getStoreConfig('sitemap/page/priority', $storeId)
            );
            $this->_rows[] = $xml;
        }
        unset($collection);
    }

    /**
     * @param $io
     * @throws Mage_Core_Exception
     * @throws Throwable
     */
    protected function generateNoSplitXml($io)
    {
        if ($io->fileExists($this->getSitemapFilename()) && !$io->isWriteable($this->getSitemapFilename())) {
            Mage::throwException(Mage::helper('sitemap')->__('File "%s" cannot be saved. Please, make sure the directory "%s" is writeable by web server.', $this->getSitemapFilename(), $this->getPath()));
        }

        $io->streamOpen($this->getSitemapFilename());
        $io->streamWrite('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        $io->streamWrite('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

        foreach ($this->_rows as $row) {
            $io->streamWrite($row);
        }

        $io->streamWrite('</urlset>');
        $io->streamClose();
    }

    /**
     * @param $io
     * @param $baseUrl
     * @param $date
     * @param $storeId
     * @throws Mage_Core_Exception
     */
    protected function generateSplitXml($io, $baseUrl, $date, $storeId)
    {
        $i = 1;
        $sitemaps = array_chunk($this->_rows, Mage::helper('sitemap')->getMaximumLinesNumber($storeId));

        foreach ($sitemaps as $sitemap) {
            $fileName = str_replace ('.xml', '-' . $i++ . '.xml', $this->getSitemapFilename());
            if ($io->fileExists($fileName) && !$io->isWriteable($fileName)) {
                Mage::throwException(Mage::helper('sitemap')->__('File "%s" cannot be saved. Please, make sure the directory "%s" is writeable by web server.', $fileName, $this->getPath()));
            }
            $this->_siteMapFileNames[] = $fileName;

            $io->streamOpen($fileName);
            $io->streamWrite('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
            $io->streamWrite('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

            foreach ($sitemap as $row) {
                $io->streamWrite($row);
            }

            $io->streamWrite('</urlset>');
            $io->streamClose();
        }

        if ($io->fileExists($this->getSitemapFilename()) && !$io->isWriteable($this->getSitemapFilename())) {
            Mage::throwException(Mage::helper('sitemap')->__('File "%s" cannot be saved. Please, make sure the directory "%s" is writeable by web server.', $this->getSitemapFilename(), $this->getPath()));
        }

        $io->streamOpen($this->getSitemapFilename());
        $io->streamWrite('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        $io->streamWrite('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

        foreach ($this->_siteMapFileNames as $siteMapFileName) {
            $row = '<sitemap><loc>' . $baseUrl . $siteMapFileName . '</loc><lastmod>' . $date . '</lastmod></sitemap>';
            $io->streamWrite($row);
        }

        $io->streamWrite('</sitemapindex>');
        $io->streamClose();
    }

    /**
     * Check is split required
     *
     * @return bool
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function _isSplitRequired()
    {
        $storeId = Mage::app()->getStore()->getStoreId();
        if (count($this->_rows) + 1 > Mage::helper('sitemap')->getMaximumLinesNumber($storeId)) {
            return true;
        }

        return false;
    }
}
