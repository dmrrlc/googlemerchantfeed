<?php
/**
 * Google Merchant Feed Output - XML Generator
 * 
 * This file generates the product feed in Google Shopping XML format.
 * Access via: https://yourdomain.com/modules/googlemerchantfeed/feed.php?key=YOUR_SECRET_KEY
 */

// Initialize PrestaShop
$rootDir = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/config/config.inc.php';
require_once $rootDir . '/init.php';

// Security check
$secretKey = Configuration::get('GMFEED_SECRET_KEY');
$providedKey = Tools::getValue('key');

if (empty($secretKey) || $providedKey !== $secretKey) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied. Invalid or missing key.');
}

// Get configuration
$id_lang = (int) Configuration::get('GMFEED_LANG');
$currency_iso = Configuration::get('GMFEED_CURRENCY') ?: 'CHF';
$shipping_country = Configuration::get('GMFEED_SHIPPING_COUNTRY') ?: 'CH';
$shipping_price = (float) Configuration::get('GMFEED_SHIPPING_PRICE');

// Get currency
$currency = Currency::getIdByIsoCode($currency_iso);
if (!$currency) {
    $currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
    $currency_obj = new Currency($currency);
    $currency_iso = $currency_obj->iso_code;
}

// Get shop context
$context = Context::getContext();
$id_shop = (int) $context->shop->id;
$base_url = $context->shop->getBaseURL(true);

// Start XML output
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: max-age=3600');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
echo '<channel>' . "\n";
echo '<title>' . htmlspecialchars(Configuration::get('PS_SHOP_NAME')) . '</title>' . "\n";
echo '<link>' . htmlspecialchars($base_url) . '</link>' . "\n";
echo '<description>Product feed for Google Merchant Center</description>' . "\n";

// Get active products
$sql = new DbQuery();
$sql->select('p.id_product');
$sql->from('product', 'p');
$sql->innerJoin('product_shop', 'ps', 'p.id_product = ps.id_product AND ps.id_shop = ' . $id_shop);
$sql->where('ps.active = 1');
$sql->where('p.visibility IN ("both", "catalog")');

$products = Db::getInstance()->executeS($sql);

foreach ($products as $row) {
    $id_product = (int) $row['id_product'];
    $product = new Product($id_product, true, $id_lang, $id_shop);
    
    if (!Validate::isLoadedObject($product)) {
        continue;
    }
    
    // Get product combinations (variants)
    $combinations = $product->getAttributeCombinations($id_lang);
    
    if (empty($combinations)) {
        // Simple product without variants
        outputProductItem($product, null, $id_lang, $currency_iso, $shipping_country, $shipping_price, $base_url, $id_shop);
    } else {
        // Product with variants - group by id_product_attribute
        $grouped_combinations = [];
        foreach ($combinations as $combo) {
            $id_attr = $combo['id_product_attribute'];
            if (!isset($grouped_combinations[$id_attr])) {
                $grouped_combinations[$id_attr] = [
                    'id_product_attribute' => $id_attr,
                    'reference' => $combo['reference'],
                    'ean13' => $combo['ean13'],
                    'upc' => $combo['upc'],
                    'price' => $combo['price'],
                    'quantity' => $combo['quantity'],
                    'attributes' => [],
                ];
            }
            $grouped_combinations[$id_attr]['attributes'][] = [
                'group' => $combo['group_name'],
                'name' => $combo['attribute_name'],
            ];
        }
        
        foreach ($grouped_combinations as $combination) {
            outputProductItem($product, $combination, $id_lang, $currency_iso, $shipping_country, $shipping_price, $base_url, $id_shop);
        }
    }
}

echo '</channel>' . "\n";
echo '</rss>';

/**
 * Output a single product item in Google Shopping format
 */
function outputProductItem($product, $combination, $id_lang, $currency_iso, $shipping_country, $shipping_price, $base_url, $id_shop)
{
    $id_product = (int) $product->id;
    $id_product_attribute = $combination ? (int) $combination['id_product_attribute'] : 0;
    
    // Build item ID
    $item_id = $id_product_attribute ? $id_product . '-' . $id_product_attribute : $id_product;
    
    // Get prices - regular and sale price
    $specific_price_output = null;
    
    // Get price WITHOUT any reduction (base price)
    $price_regular = Product::getPriceStatic(
        $id_product, 
        true,                    // with tax
        $id_product_attribute, 
        6,                       // decimals for precision
        null,                    // divisor
        false,                   // only_reduc
        false                    // use_reduc = false to get original price
    );
    
    // Get price WITH reduction (final price customer pays)
    $price_final = Product::getPriceStatic(
        $id_product, 
        true,                    // with tax
        $id_product_attribute, 
        6,                       // decimals
        null,                    // divisor
        false,                   // only_reduc
        true                     // use_reduc = true to apply discounts
    );
    
    // Round for comparison and output
    $price_regular = round($price_regular, 2);
    $price_final = round($price_final, 2);
    
    $has_discount = ($price_final < $price_regular);
    
    // Get specific price info for sale dates
    $sale_from = null;
    $sale_to = null;
    if ($has_discount) {
        $specific_price = SpecificPrice::getSpecificPrice(
            $id_product,
            $id_shop,
            0, // id_currency
            0, // id_country
            0, // id_group
            1, // quantity
            $id_product_attribute ?: 0,
            0, // id_customer
            0, // id_cart
            0  // real_quantity
        );
        
        if ($specific_price) {
            if (!empty($specific_price['from']) && $specific_price['from'] !== '0000-00-00 00:00:00') {
                $sale_from = date('c', strtotime($specific_price['from']));
            }
            if (!empty($specific_price['to']) && $specific_price['to'] !== '0000-00-00 00:00:00') {
                $sale_to = date('c', strtotime($specific_price['to']));
            }
        }
    }
    
    // Get availability
    if ($id_product_attribute) {
        $quantity = (int) StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);
    } else {
        $quantity = (int) StockAvailable::getQuantityAvailableByProduct($id_product);
    }
    
    // Check if product is available for order
    $available_for_order = $product->available_for_order;
    
    // Check if stock management is enabled for this product
    $stock_management = (bool) Configuration::get('PS_STOCK_MANAGEMENT');
    
    // Determine availability based on stock and order permissions
    if (!$available_for_order) {
        // Product not available for order - always out of stock
        $availability = 'out_of_stock';
    } elseif ($stock_management && $quantity <= 0) {
        // Stock management enabled and no stock available
        // Check if backorders are allowed
        $out_of_stock = (int) $product->out_of_stock;
        
        // out_of_stock: 0 = Deny orders, 1 = Allow orders, 2 = Use default
        if ($out_of_stock == 2) {
            // Use shop default
            $default_behavior = (int) Configuration::get('PS_ORDER_OUT_OF_STOCK');
            $availability = $default_behavior ? 'backorder' : 'out_of_stock';
        } elseif ($out_of_stock == 1) {
            // Allow orders when out of stock
            $availability = 'backorder';
        } else {
            // Deny orders when out of stock (value 0)
            $availability = 'out_of_stock';
        }
    } elseif ($stock_management && $quantity > 0) {
        // Stock management enabled and stock available
        $availability = 'in_stock';
    } elseif (!$stock_management) {
        // Stock management disabled - assume always in stock if available for order
        $availability = 'in_stock';
    } else {
        // Fallback: should not reach here, but default to out of stock
        $availability = 'out_of_stock';
    }
    
    // Get product URL
    $link = new Link();
    $product_url = $link->getProductLink($product, null, null, null, $id_lang, $id_shop, $id_product_attribute);
    
    // Get images - prioritize combination-specific images
    $image_url = '';
    $additional_images = [];
    $has_combination_images = false;
    
    if ($id_product_attribute) {
        // Get images associated with this specific combination
        $combination_images = Product::_getAttributeImageAssociations($id_product_attribute);
        
        if (!empty($combination_images)) {
            $has_combination_images = true;
            $first = true;
            $count = 0;
            foreach ($combination_images as $id_image) {
                $img_url = $link->getImageLink($product->link_rewrite, $id_image, 'large_default');
                if (strpos($img_url, 'http') !== 0) {
                    $img_url = 'https://' . $img_url;
                }
                
                if ($first) {
                    $image_url = $img_url;
                    $first = false;
                } elseif ($count < 10) {
                    $additional_images[] = $img_url;
                    $count++;
                }
            }
        }
    }
    
    // Fallback to all product images only if no combination-specific images
    if (!$has_combination_images) {
        $images = $product->getImages($id_lang);
        $first = true;
        $count = 0;
        foreach ($images as $img) {
            $img_url = $link->getImageLink($product->link_rewrite, $img['id_image'], 'large_default');
            if (strpos($img_url, 'http') !== 0) {
                $img_url = 'https://' . $img_url;
            }
            
            if ($first) {
                $image_url = $img_url;
                $first = false;
            } elseif ($count < 10) {
                $additional_images[] = $img_url;
                $count++;
            }
        }
    }
    
    // Get brand/manufacturer
    $brand = '';
    if ($product->id_manufacturer) {
        $manufacturer = new Manufacturer($product->id_manufacturer, $id_lang);
        $brand = $manufacturer->name;
    }
    
    // Get EAN/GTIN
    $gtin = '';
    if ($combination && !empty($combination['ean13'])) {
        $gtin = $combination['ean13'];
    } elseif (!empty($product->ean13)) {
        $gtin = $product->ean13;
    }
    
    // Get MPN (reference)
    $mpn = '';
    if ($combination && !empty($combination['reference'])) {
        $mpn = $combination['reference'];
    } elseif (!empty($product->reference)) {
        $mpn = $product->reference;
    }
    
    // Get category path
    $category_path = '';
    $default_category = new Category($product->id_category_default, $id_lang);
    if (Validate::isLoadedObject($default_category)) {
        $parents = $default_category->getParentsCategories($id_lang);
        $path_parts = [];
        foreach (array_reverse($parents) as $parent) {
            if ($parent['id_category'] > 2) { // Skip root and home
                $path_parts[] = $parent['name'];
            }
        }
        $category_path = implode(' > ', $path_parts);
    }
    
    // Build title with variant info
    $title = $product->name;
    if ($combination && !empty($combination['attributes'])) {
        $attr_names = [];
        foreach ($combination['attributes'] as $attr) {
            $attr_names[] = $attr['name'];
        }
        $title .= ' - ' . implode(' / ', $attr_names);
    }
    $title = mb_substr($title, 0, 150);
    
    // Get description
    $description = strip_tags($product->description_short);
    if (empty($description)) {
        $description = strip_tags($product->description);
    }
    $description = mb_substr(trim(preg_replace('/\s+/', ' ', $description)), 0, 5000);
    
    // Extract size and color from combination attributes
    $size = '';
    $color = '';
    if ($combination && !empty($combination['attributes'])) {
        foreach ($combination['attributes'] as $attr) {
            $group_lower = strtolower($attr['group']);
            if (in_array($group_lower, ['size', 'taille', 'größe', 'grösse'])) {
                $size = $attr['name'];
            }
            if (in_array($group_lower, ['color', 'colour', 'couleur', 'farbe'])) {
                $color = $attr['name'];
            }
        }
    }
    
    // Condition
    $condition = 'new';
    if ($product->condition == 'used') {
        $condition = 'used';
    } elseif ($product->condition == 'refurbished') {
        $condition = 'refurbished';
    }
    
    // Output item
    echo '<item>' . "\n";
    echo '  <g:id>' . xmlEscape($item_id) . '</g:id>' . "\n";
    echo '  <g:title>' . xmlEscape($title) . '</g:title>' . "\n";
    echo '  <g:description>' . xmlEscape($description) . '</g:description>' . "\n";
    echo '  <g:link>' . xmlEscape($product_url) . '</g:link>' . "\n";
    
    if ($image_url) {
        echo '  <g:image_link>' . xmlEscape($image_url) . '</g:image_link>' . "\n";
    }
    
    foreach ($additional_images as $add_img) {
        echo '  <g:additional_image_link>' . xmlEscape($add_img) . '</g:additional_image_link>' . "\n";
    }
    
    echo '  <g:availability>' . $availability . '</g:availability>' . "\n";
    
    // Price output - if discounted, show regular price as price and discounted as sale_price
    if ($has_discount) {
        echo '  <g:price>' . number_format($price_regular, 2, '.', '') . ' ' . $currency_iso . '</g:price>' . "\n";
        echo '  <g:sale_price>' . number_format($price_final, 2, '.', '') . ' ' . $currency_iso . '</g:sale_price>' . "\n";
        
        // Add sale price effective date if available
        if ($sale_from || $sale_to) {
            $date_range = ($sale_from ?: '') . '/' . ($sale_to ?: '');
            echo '  <g:sale_price_effective_date>' . xmlEscape($date_range) . '</g:sale_price_effective_date>' . "\n";
        }
    } else {
        echo '  <g:price>' . number_format($price_final, 2, '.', '') . ' ' . $currency_iso . '</g:price>' . "\n";
    }
    
    if ($brand) {
        echo '  <g:brand>' . xmlEscape($brand) . '</g:brand>' . "\n";
    }
    
    if ($gtin) {
        echo '  <g:gtin>' . xmlEscape($gtin) . '</g:gtin>' . "\n";
    }
    
    if ($mpn) {
        echo '  <g:mpn>' . xmlEscape($mpn) . '</g:mpn>' . "\n";
    }
    
    // Identifier exists - set to false if no GTIN/MPN/brand
    if (empty($gtin) && empty($mpn)) {
        echo '  <g:identifier_exists>false</g:identifier_exists>' . "\n";
    }
    
    echo '  <g:condition>' . $condition . '</g:condition>' . "\n";
    
    if ($category_path) {
        echo '  <g:product_type>' . xmlEscape($category_path) . '</g:product_type>' . "\n";
    }
    
    // Item group ID for variants
    if ($id_product_attribute) {
        echo '  <g:item_group_id>' . $id_product . '</g:item_group_id>' . "\n";
    }
    
    // Size
    if ($size) {
        echo '  <g:size>' . xmlEscape($size) . '</g:size>' . "\n";
    }
    
    // Color
    if ($color) {
        echo '  <g:color>' . xmlEscape($color) . '</g:color>' . "\n";
    }
    
    // Shipping
    echo '  <g:shipping>' . "\n";
    echo '    <g:country>' . $shipping_country . '</g:country>' . "\n";
    echo '    <g:price>' . number_format($shipping_price, 2, '.', '') . ' ' . $currency_iso . '</g:price>' . "\n";
    echo '  </g:shipping>' . "\n";
    
    echo '</item>' . "\n";
}

/**
 * Escape string for XML output
 */
function xmlEscape($string)
{
    return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
