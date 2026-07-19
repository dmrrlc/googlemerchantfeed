# Google Merchant Feed Module for PrestaShop 8 & 9

Free module to generate a product feed compatible with Google Merchant Center.

## Installation

1. **Download the `googlemerchantfeed` folder**

2. **Upload via FTP/SFTP**
   - Connect to your server
   - Navigate to `/modules/`
   - Upload the complete `googlemerchantfeed` folder

3. **Install the module**
   - PrestaShop Back-office → Modules → Module Manager
   - Search for "Google Merchant Feed"
   - Click "Install"

4. **Configure the module**
   - Click "Configure" after installation
   - Choose the feed language (FR or EN)
   - Set the currency (CHF)
   - Configure default shipping fees
   - Use **Preview Feed** (or the Admin preview URL) to inspect the XML while logged into the back office — no secret key required
   - Copy the secret-key **Feed URL** only into Google Merchant Center (not the admin preview URL)

## Google Merchant Center Configuration

1. Log in to [Google Merchant Center](https://merchants.google.com)

2. Go to **Products → Feeds**

3. Click **Add primary feed**

4. Select:
   - Country: Switzerland (or the configured one)
   - Language: French (or the configured one)
   - Destination: Shopping ads and free product listings

5. Choose **Scheduled fetch**

6. Enter the feed URL provided in the module configuration:
   ```
   https://airone.ch/modules/googlemerchantfeed/feed.php?key=YOUR_SECRET_KEY
   ```

7. Set the fetch frequency (daily recommended)

## Generated Attributes

The feed automatically includes:

| Attribute | PrestaShop Source |
|-----------|-------------------|
| id | Product ID + combination ID |
| title | Product name + attributes |
| description | Short or long description |
| link | Product URL |
| image_link | Main image |
| additional_image_link | Additional images (max 10) |
| availability | Stock available |
| price | Price including tax |
| brand | Manufacturer (ION, DUOTONE, etc.) |
| gtin | EAN13 code |
| mpn | Product reference |
| condition | Condition (new/used/refurbished) |
| product_type | Category path |
| item_group_id | Variant grouping |
| size | Size (if attribute defined) |
| color | Color (if attribute defined) |
| shipping | Shipping fees |



To improve the quality of your listings:

### EAN/GTIN codes
- Request EAN codes from your suppliers (Sideshore)
- Enter them in each product page or combination
- GTIN codes improve Google Shopping SEO

### Google Categories
Map your categories to Google taxonomy:
- Harnesses → `Sporting Goods > Outdoor Recreation > Water Sports > Kiteboarding`
- Kites → `Sporting Goods > Outdoor Recreation > Water Sports > Kiteboarding > Kiteboarding Kites`
- Boards → `Sporting Goods > Outdoor Recreation > Water Sports > Kiteboarding > Kiteboards`

### Images
- Use high-quality images (min 800x800px)
- White or neutral background preferred
- Multiple angles per product

## Troubleshooting

### Feed not displaying
- Verify that the secret key is correct in the URL
- Test the URL directly in your browser
- Check PHP logs

### Preview feed from the back office
- In module configuration, click **Preview Feed** or open the **Admin preview URL**
- You must be logged into the PrestaShop back office; the URL uses the AdminModules CSRF token and does **not** contain the secret key
- Logged-out users, invalid tokens, or direct access without the secret key receive HTTP 403
- Do **not** paste the admin preview URL into Google Merchant Center — use the secret-key Feed URL instead

### Google Merchant Errors
- "Missing identifier": Add EAN codes or set `identifier_exists` to false (automatic)
- "Invalid image": Verify that images are publicly accessible
- "Missing price": Verify that products have a defined price

### Refresh the feed
The feed is generated dynamically on each request. Google fetches updates according to the configured frequency.

### "N products will be removed within the next 3 days" / products expiring
Merchant Center expires a product **30 days after its last successful update**. If a
product stops appearing in the feed, Google can no longer refresh it and starts a
30-day removal countdown. Checklist:

- **Use a daily scheduled fetch** so every product's freshness clock resets each day.
- **Open the feed URL in a browser and scroll to the very end.** A healthy feed ends
  with `</rss>` and an XML comment such as `<!-- items: 512, skipped: 0 -->`. If the
  document is cut off, PHP is aborting mid-generation (timeout / memory) and Google is
  receiving a partial feed — the leading cause of mass "products will be removed"
  warnings. `feed.php` now suppresses partial output (returns HTTP 500 instead of a
  truncated 200) and raises PHP's time/memory limits to prevent this.
- Confirm the flagged products are still **active** and have **visibility** set to
  "Everywhere" or "Catalog only" — products set to "Search only"/"Nowhere" are excluded
  from the feed by design and will expire in Merchant Center.

## Security

- The Merchant Center URL contains an automatically generated secret key
- You can regenerate this key in the configuration if needed
- Do not share the secret-key URL publicly
- The admin preview URL works only for a logged-in employee with a valid back-office token

## Support

Module developed specifically for PrestaShop 8.1.5+ and 9.x, tested with the airone.ch configuration.

---

**Version**: 1.0.0  
**Compatibility**: PrestaShop 8.1.5+ / 9.0+  
**License**: MIT
