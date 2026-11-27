-- Keep the demo catalog at 10 active products without deleting product rows.
-- This preserves any historical order-item references in an existing database.
USE redline_royale_assignment2;

-- Add any missing demo products for databases that were created before the
-- complete ten-product sample catalog was installed. Safe to rerun.
INSERT INTO products (vendor_id, category_id, title, description, price, stock, image_url, status, views)
SELECT u.user_id, c.category_id, demo.title, demo.description, demo.price, demo.stock, demo.image_url, 'active', demo.views
FROM (
  SELECT 'Kurtas' category, 'Heritage Cotton Kurta' title, 'A breathable cotton kurta with a clean, classic cut.' description, 3200.00 price, 12 stock, 'images/products/kurta.png' image_url, 44 views
  UNION ALL SELECT 'Jackets', 'Wool Blend Heritage Jacket', 'A structured everyday jacket from a local maker.', 7800.00, 5, 'images/products/heritage-jacket.png', 31
  UNION ALL SELECT 'Shirts', 'Linen Summer Shirt', 'A lightweight linen shirt for warm days, with a relaxed fit.', 2800.00, 15, 'images/products/linen-shirt.png', 22
  UNION ALL SELECT 'Waistcoats', 'Embroidered Waistcoat', 'A structured waistcoat with traditional embroidery detail.', 4500.00, 8, 'images/products/waistcoat.png', 15
  UNION ALL SELECT 'Accessories', 'Dhaka Topi', 'A traditional Nepali woven cap in colourful Dhaka pattern.', 1200.00, 25, 'images/products/dhaka-topi.png', 28
  UNION ALL SELECT 'Shirts', 'Nepali Dhaka Shirt', 'A shirt crafted from authentic Nepali Dhaka fabric.', 3500.00, 10, 'images/products/nepali-dhaka-shirt.png', 19
  UNION ALL SELECT 'Jackets', 'Silk Blend Blazer', 'A refined silk-blend blazer for formal occasions.', 9500.00, 4, 'images/products/silk-blazer.png', 7
  UNION ALL SELECT 'Accessories', 'Handcrafted Leather Belt', 'A hand-stitched leather belt with brass buckle.', 1800.00, 15, 'images/products/belt.png', 10
  UNION ALL SELECT 'Shoes', 'Leather Oxford Shoes', 'Handcrafted brown leather Oxford shoes with a classic silhouette.', 6800.00, 6, 'images/products/leather-shoes.png', 16
  UNION ALL SELECT 'Shoes', 'Canvas Sneakers', 'Comfortable everyday canvas sneakers in a clean, minimal style.', 3200.00, 14, 'images/products/sneakers.png', 24
) AS demo
JOIN categories c ON c.name = demo.category
JOIN users u ON u.email = 'vendor@redlineroyale.test' AND u.role = 'vendor'
WHERE NOT EXISTS (
  SELECT 1 FROM products existing
  WHERE existing.vendor_id = u.user_id AND existing.title = demo.title
);

UPDATE products
SET status = 'inactive'
WHERE title IN (
  'Handwoven Pocket Square',
  'Cotton Churidar Pyjama',
  'Hemp Slip-On Loafers'
)
AND status = 'active';
