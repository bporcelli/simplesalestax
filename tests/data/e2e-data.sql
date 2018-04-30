USE simplesalestax;

/**
 * Reset products.
 */
DELETE pm
FROM wp_postmeta pm, wp_posts p
WHERE pm.post_id = p.ID
AND p.post_type = 'product';

DELETE
FROM wp_posts
WHERE post_type = 'product';

INSERT INTO `wp_posts` (`ID`,`post_author`,`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_excerpt`,`post_status`,`comment_status`,`ping_status`,`post_password`,`post_name`,`to_ping`,`pinged`,`post_modified`,`post_modified_gmt`,`post_content_filtered`,`post_parent`,`guid`,`menu_order`,`post_type`,`post_mime_type`,`comment_count`) VALUES (14693,1,'2017-06-06 20:32:54','2017-06-06 20:32:54','','General Product','','publish','open','closed','','general-product','','','2018-01-19 21:02:21','2018-01-19 21:02:21','',0,'http://sst.test/?post_type=product&#038;p=14693',0,'product','',0);
INSERT INTO `wp_posts` (`ID`,`post_author`,`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_excerpt`,`post_status`,`comment_status`,`ping_status`,`post_password`,`post_name`,`to_ping`,`pinged`,`post_modified`,`post_modified_gmt`,`post_content_filtered`,`post_parent`,`guid`,`menu_order`,`post_type`,`post_mime_type`,`comment_count`) VALUES (14694,1,'2017-06-06 20:34:08','2017-06-06 20:34:08','','eBook','','publish','open','closed','','ebook','','','2018-01-15 15:44:30','2018-01-15 15:44:30','',0,'http://sst.test/?post_type=product&#038;p=14694',0,'product','',0);
INSERT INTO `wp_posts` (`ID`,`post_author`,`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_excerpt`,`post_status`,`comment_status`,`ping_status`,`post_password`,`post_name`,`to_ping`,`pinged`,`post_modified`,`post_modified_gmt`,`post_content_filtered`,`post_parent`,`guid`,`menu_order`,`post_type`,`post_mime_type`,`comment_count`) VALUES (14696,1,'2017-06-06 20:34:40','2017-06-06 20:34:40','','Lumber','','publish','closed','closed','','lumber','','','2017-07-21 20:42:39','2017-07-21 20:42:39','',0,'http://sst.test/?post_type=product&#038;p=14696',0,'product','',0);
INSERT INTO `wp_posts` (`ID`,`post_author`,`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_excerpt`,`post_status`,`comment_status`,`ping_status`,`post_password`,`post_name`,`to_ping`,`pinged`,`post_modified`,`post_modified_gmt`,`post_content_filtered`,`post_parent`,`guid`,`menu_order`,`post_type`,`post_mime_type`,`comment_count`) VALUES (90148,1,'2018-01-15 15:44:05','2018-01-15 15:44:05','','Variable Product','','publish','open','closed','','variable-product','','','2018-01-17 17:11:11','2018-01-17 17:11:11','',0,'http://sst.test/?post_type=product&#038;p=90148',0,'product','',0);
INSERT INTO `wp_posts` (`ID`,`post_author`,`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_excerpt`,`post_status`,`comment_status`,`ping_status`,`post_password`,`post_name`,`to_ping`,`pinged`,`post_modified`,`post_modified_gmt`,`post_content_filtered`,`post_parent`,`guid`,`menu_order`,`post_type`,`post_mime_type`,`comment_count`) VALUES (90161,1,'2018-01-17 18:08:47','2018-01-17 18:08:47','','Multi-origin Product','','publish','open','closed','','multi-origin-product','','','2018-01-17 18:08:47','2018-01-17 18:08:47','',0,'http://sst.test/?post_type=product&#038;p=90161',0,'product','',0);

INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297897,14693,'wootax_tic','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297898,14693,'_wootax_origin_addresses','a:1:{i:0;s:1:\"0\";}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297899,14693,'_edit_lock','1516395602:1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297900,14693,'_edit_last','1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297901,14693,'_visibility','visible');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297902,14693,'_stock_status','instock');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297903,14693,'total_sales','96');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297904,14693,'_downloadable','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297905,14693,'_virtual','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297906,14693,'_tax_status','taxable');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297907,14693,'_tax_class','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297908,14693,'_purchase_note','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297909,14693,'_featured','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297910,14693,'_weight','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297911,14693,'_length','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297912,14693,'_width','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297913,14693,'_height','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297914,14693,'_sku','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297915,14693,'_product_attributes','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297916,14693,'_regular_price','19.99');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297917,14693,'_sale_price','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297918,14693,'_sale_price_dates_from','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297919,14693,'_sale_price_dates_to','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297920,14693,'_price','19.99');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297921,14693,'_sold_individually','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297922,14693,'_manage_stock','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297923,14693,'_backorders','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297924,14693,'_stock',NULL);
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297925,14693,'_upsell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297926,14693,'_crosssell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297927,14693,'_product_version','3.2.6');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297928,14693,'_product_image_gallery','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (298001,14693,'_wc_rating_count','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (298002,14693,'_wc_average_rating','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (298260,14693,'_wc_review_count','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301955,14693,'_default_attributes','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301956,14693,'_download_limit','-1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301957,14693,'_download_expiry','-1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297929,14694,'wootax_tic','31100');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297930,14694,'_wootax_origin_addresses','a:1:{i:0;s:1:\"1\";}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297931,14694,'_edit_lock','1516031104:1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297934,14694,'_edit_last','1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297935,14694,'_visibility','visible');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297936,14694,'_stock_status','instock');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297937,14694,'total_sales','77');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297938,14694,'_downloadable','yes');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297939,14694,'_virtual','yes');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297940,14694,'_tax_status','taxable');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297941,14694,'_tax_class','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297942,14694,'_purchase_note','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297943,14694,'_featured','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297944,14694,'_weight','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297945,14694,'_length','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297946,14694,'_width','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297947,14694,'_height','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297948,14694,'_sku','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297949,14694,'_product_attributes','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297950,14694,'_regular_price','9.99');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297951,14694,'_sale_price','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297952,14694,'_sale_price_dates_from','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297953,14694,'_sale_price_dates_to','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297954,14694,'_price','9.99');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297955,14694,'_sold_individually','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297956,14694,'_manage_stock','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297957,14694,'_backorders','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297958,14694,'_stock',NULL);
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297959,14694,'_upsell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297960,14694,'_crosssell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297961,14694,'_downloadable_files','a:1:{s:32:\"9d8b31f64b685e09ad96eccc11d89f0d\";a:3:{s:2:\"id\";s:32:\"9d8b31f64b685e09ad96eccc11d89f0d\";s:4:\"name\";s:9:\"Test File\";s:4:\"file\";s:108:\"http://sst.test/wp-content/uploads/woocommerce_uploads/2017/06/27a721f04ff85c67eb9e8a50232b058a9beb1b52.jpeg\";}}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297962,14694,'_download_limit','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297963,14694,'_download_expiry','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297964,14694,'_download_type','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297965,14694,'_product_version','3.3.0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297966,14694,'_product_image_gallery','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297999,14694,'_wc_rating_count','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (298000,14694,'_wc_average_rating','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (298261,14694,'_wc_review_count','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302068,14694,'_default_attributes','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297967,14696,'wootax_tic','94002');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297968,14696,'_wootax_origin_addresses','a:1:{i:0;s:1:\"0\";}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297969,14696,'_edit_lock','1516031110:1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297970,14696,'_edit_last','1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297971,14696,'_visibility','visible');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297972,14696,'_stock_status','instock');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297973,14696,'total_sales','53');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297974,14696,'_downloadable','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297975,14696,'_virtual','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297976,14696,'_tax_status','taxable');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297977,14696,'_tax_class','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297978,14696,'_purchase_note','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297979,14696,'_featured','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297980,14696,'_weight','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297981,14696,'_length','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297982,14696,'_width','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297983,14696,'_height','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297984,14696,'_sku','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297985,14696,'_product_attributes','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297986,14696,'_regular_price','59.99');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297987,14696,'_sale_price','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297988,14696,'_sale_price_dates_from','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297989,14696,'_sale_price_dates_to','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297990,14696,'_price','59.99');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297991,14696,'_sold_individually','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297992,14696,'_manage_stock','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297993,14696,'_backorders','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297994,14696,'_stock','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297995,14696,'_upsell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297996,14696,'_crosssell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297997,14696,'_product_version','2.6.0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (297998,14696,'_product_image_gallery','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (298003,14696,'_wc_rating_count','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (298004,14696,'_wc_average_rating','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (298262,14696,'_wc_review_count','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300026,14696,'_default_attributes','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300027,14696,'_download_limit','-1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300028,14696,'_download_expiry','-1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301958,90148,'_wootax_origin_addresses','a:1:{i:0;s:1:\"1\";}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301959,90148,'_wc_review_count','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301960,90148,'_wc_rating_count','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301961,90148,'_wc_average_rating','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301962,90148,'_edit_lock','1516210355:1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301963,90148,'_edit_last','1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301964,90148,'_sku','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301965,90148,'_regular_price','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301966,90148,'_sale_price','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301967,90148,'_sale_price_dates_from','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301968,90148,'_sale_price_dates_to','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301969,90148,'total_sales','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301970,90148,'_tax_status','taxable');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301971,90148,'_tax_class','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301972,90148,'_manage_stock','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301973,90148,'_backorders','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301974,90148,'_sold_individually','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301975,90148,'_weight','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301976,90148,'_length','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301977,90148,'_width','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301978,90148,'_height','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301979,90148,'_upsell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301980,90148,'_crosssell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301981,90148,'_purchase_note','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301982,90148,'_default_attributes','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301983,90148,'_virtual','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301984,90148,'_downloadable','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301985,90148,'_product_image_gallery','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301986,90148,'_download_limit','-1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301987,90148,'_download_expiry','-1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301988,90148,'_stock',NULL);
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301989,90148,'_stock_status','instock');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301990,90148,'_product_attributes','a:1:{s:10:\"pa_version\";a:6:{s:4:\"name\";s:10:\"pa_version\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:1;s:11:\"is_taxonomy\";i:1;}}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (301991,90148,'_product_version','3.3.0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302067,90148,'wootax_tic','20070');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302209,90148,'_price','1.99');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302210,90148,'_price','2.99');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302211,90161,'_wootax_origin_addresses','a:2:{i:0;s:1:\"1\";i:1;s:1:\"2\";}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302212,90161,'_wc_review_count','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302213,90161,'_wc_rating_count','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302214,90161,'_wc_average_rating','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302215,90161,'_edit_lock','1516214064:1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302216,90161,'_edit_last','1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302217,90161,'wootax_tic','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302218,90161,'_sku','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302219,90161,'_regular_price','3.50');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302220,90161,'_sale_price','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302221,90161,'_sale_price_dates_from','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302222,90161,'_sale_price_dates_to','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302223,90161,'total_sales','1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302224,90161,'_tax_status','taxable');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302225,90161,'_tax_class','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302226,90161,'_manage_stock','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302227,90161,'_backorders','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302228,90161,'_sold_individually','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302229,90161,'_weight','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302230,90161,'_length','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302231,90161,'_width','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302232,90161,'_height','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302233,90161,'_upsell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302234,90161,'_crosssell_ids','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302235,90161,'_purchase_note','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302236,90161,'_default_attributes','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302237,90161,'_virtual','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302238,90161,'_downloadable','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302239,90161,'_product_image_gallery','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302240,90161,'_download_limit','-1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302241,90161,'_download_expiry','-1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302242,90161,'_stock',NULL);
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302243,90161,'_stock_status','instock');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302244,90161,'_product_version','3.3.0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (302245,90161,'_price','3.50');


/**
 * Reset tax rates.
 */
TRUNCATE TABLE wp_woocommerce_tax_rate_locations;
TRUNCATE TABLE wp_woocommerce_tax_rates;

INSERT INTO `wp_woocommerce_tax_rates` (`tax_rate_id`,`tax_rate_country`,`tax_rate_state`,`tax_rate`,`tax_rate_name`,`tax_rate_priority`,`tax_rate_compound`,`tax_rate_shipping`,`tax_rate_order`,`tax_rate_class`) VALUES (1,'WT','RATE','0','DO-NOT-REMOVE',0,1,1,0,'standard');
INSERT INTO `wp_woocommerce_tax_rates` (`tax_rate_id`,`tax_rate_country`,`tax_rate_state`,`tax_rate`,`tax_rate_name`,`tax_rate_priority`,`tax_rate_compound`,`tax_rate_shipping`,`tax_rate_order`,`tax_rate_class`) VALUES (2,'US','NY','9.0000','Sales Tax',1,1,1,0,'');

INSERT INTO `wp_woocommerce_tax_rate_locations` (`location_id`,`location_code`,`tax_rate_id`,`location_type`) VALUES (1,'11795',2,'postcode');
INSERT INTO `wp_woocommerce_tax_rate_locations` (`location_id`,`location_code`,`tax_rate_id`,`location_type`) VALUES (2,'WEST ISLIP',2,'city');


/**
 * Reset orders.
 */
TRUNCATE TABLE wp_woocommerce_order_itemmeta;
TRUNCATE TABLE wp_woocommerce_order_items;

DELETE pm
FROM wp_postmeta pm, wp_posts p
WHERE pm.post_id = p.ID
AND p.post_type = 'shop_order';

DELETE
FROM wp_posts
WHERE post_type = 'shop_order';


/**
 * Reset coupons.
 */
DELETE pm
FROM wp_postmeta pm, wp_posts p
WHERE pm.post_id = p.ID
AND p.post_type = 'shop_coupon';

DELETE
FROM wp_posts
WHERE post_type = 'shop_coupon';

INSERT INTO `wp_posts` (`ID`,`post_author`,`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_excerpt`,`post_status`,`comment_status`,`ping_status`,`post_password`,`post_name`,`to_ping`,`pinged`,`post_modified`,`post_modified_gmt`,`post_content_filtered`,`post_parent`,`guid`,`menu_order`,`post_type`,`post_mime_type`,`comment_count`) VALUES (90054,1,'2017-07-17 23:04:47','2017-07-17 23:04:47','','ZERO','','publish','closed','closed','','zero','','','2017-07-17 23:05:15','2017-07-17 23:05:15','',0,'http://sst.test/?post_type=shop_coupon&#038;p=90054',0,'shop_coupon','',0);

INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300006,90054,'_edit_lock','1500332575:1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300007,90054,'_edit_last','1');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300008,90054,'discount_type','percent');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300009,90054,'coupon_amount','100');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300010,90054,'individual_use','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300011,90054,'product_ids','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300012,90054,'exclude_product_ids','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300013,90054,'usage_limit','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300014,90054,'usage_limit_per_user','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300015,90054,'limit_usage_to_x_items','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300016,90054,'usage_count','0');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300017,90054,'date_expires',NULL);
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300018,90054,'expiry_date','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300019,90054,'free_shipping','yes');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300020,90054,'product_categories','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300021,90054,'exclude_product_categories','a:0:{}');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300022,90054,'exclude_sale_items','no');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300023,90054,'minimum_amount','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300024,90054,'maximum_amount','');
INSERT INTO `wp_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (300025,90054,'customer_email','a:0:{}');


/**
 * Delete persistent carts.
 */
DELETE 
FROM wp_usermeta 
WHERE meta_key LIKE '_woocommerce_persistent_cart%'
AND umeta_id > 0;


/**
 * Reset plugin settings.
 */
DELETE
FROM wp_options
WHERE option_name LIKE '%wootax%'
AND option_id > 0;

INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES (54195,'wootax_rate_id','1','yes');
INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES (54260,'woocommerce_wootax_settings','a:25:{s:17:\"taxcloud_settings\";s:0:\"\";s:5:\"tc_id\";s:8:\"37058A70\";s:6:\"tc_key\";s:36:\"298E6D5B-5387-44A1-87E5-63670C94AE7B\";s:15:\"verify_settings\";s:0:\"\";s:27:\"business_addresses_settings\";s:0:\"\";s:9:\"addresses\";a:3:{i:0;s:138:\"{\"ID\":0,\"Default\":true,\"Address1\":\"Washington Ave And State St\",\"Address2\":null,\"City\":\"Albany\",\"State\":\"NY\",\"Zip5\":\"12242\",\"Zip4\":\"0001\"}\";i:1;s:131:\"{\"ID\":1,\"Default\":false,\"Address1\":\"111 Oak Neck Rd\",\"Address2\":null,\"City\":\"West Islip\",\"State\":\"NY\",\"Zip5\":\"11795\",\"Zip4\":\"4326\"}\";i:2;s:127:\"{\"ID\":2,\"Default\":false,\"Address1\":\"206 Washington St SW\",\"Address2\":\"\",\"City\":\"Atlanta\",\"State\":\"GA\",\"Zip5\":\"30334\",\"Zip4\":\"\"}\";}s:18:\"exemption_settings\";s:0:\"\";s:11:\"show_exempt\";s:4:\"true\";s:12:\"company_name\";s:16:\"Simple Sales Tax\";s:12:\"exempt_roles\";a:1:{i:0;s:15:\"exempt-customer\";}s:15:\"restrict_exempt\";s:2:\"no\";s:16:\"display_settings\";s:0:\"\";s:13:\"show_zero_tax\";s:5:\"false\";s:17:\"advanced_settings\";s:0:\"\";s:12:\"log_requests\";s:3:\"yes\";s:19:\"capture_immediately\";s:2:\"no\";s:12:\"tax_based_on\";s:10:\"item-price\";s:15:\"remove_all_data\";s:2:\"no\";s:19:\"download_log_button\";s:0:\"\";s:13:\"usps_settings\";s:0:\"\";s:7:\"usps_id\";s:12:\"514WOOTA4859\";s:14:\"exemption_text\";s:0:\"\";s:18:\"notification_email\";s:23:\"brettporcelli@gmail.com\";s:16:\"uninstall_button\";s:0:\"\";s:15:\"default_address\";N;}','yes');
INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES (54618,'wootax_last_update','1516028339','yes');
INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES (58092,'_transient_wootax_messages','a:1:{s:6:\"normal\";a:0:{}}','yes');
