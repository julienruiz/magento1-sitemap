# magento1-sitemap

Overloads the sitemap model to handle cases where one of the sitemaps exceeds the size limits.

The size limits are defined in configuration in Catalog > Google Sitemap > Sitemap File Limit.

In this case, a sitemap index is created, for example : 

<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc>https://www.example.com/sitemap1.xml</loc>
  </sitemap>
  <sitemap>
    <loc>https://www.example.com/sitemap2.xml</loc>
  </sitemap>
</sitemapindex>

Translated with www.DeepL.com/Translator (free version)
